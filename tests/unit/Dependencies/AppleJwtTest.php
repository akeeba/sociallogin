<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Akeeba\SocialLogin\Tests\Unit\Dependencies;

use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\Clock\SystemClock;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Configuration as JWTConfig;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer\Ecdsa\Sha256 as SignerES256;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer\Key\InMemory;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint\SignedWith;
use DateInterval;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers the other uses of the bundled lcobucci/jwt in the Sign in with Apple plugin
 * (AppleSocialLogin\Extension\Plugin): the ES256 client secret sent to Apple, and the claims checks on Apple's ID
 * token. The signature check on the ID token is covered by JWKConverterTest.
 */
class AppleJwtTest extends TestCase
{
	private const APP_ID = 'com.example.service';

	public function testClientSecretIsAnEs256TokenApplesServersCanVerify(): void
	{
		$key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);

		$this->assertNotFalse($key, 'OpenSSL could not generate a P-256 key');

		// Apple hands out the key as a PKCS#8 PEM (.p8 file), which is what openssl_pkey_export() produces.
		openssl_pkey_export($key, $keyMaterial);
		$publicPem = openssl_pkey_get_details($key)['key'];

		$secret = $this->getSecretKeyLikeThePlugin($keyMaterial, 'ABC123DEFG', 'TEAM123456');

		$this->assertNotSame('', $secret, 'The client secret could not be created');

		$config = JWTConfig::forSymmetricSigner(new SignerES256(null), InMemory::plainText($keyMaterial));
		$token  = $config->parser()->parse($secret);

		$this->assertSame('ES256', $token->headers()->get('alg'));
		$this->assertSame('ABC123DEFG', $token->headers()->get('kid'));
		$this->assertSame('TEAM123456', $token->claims()->get('iss'));
		$this->assertSame(['https://appleid.apple.com'], $token->claims()->get('aud'));
		$this->assertSame(self::APP_ID, $token->claims()->get('sub'));
		$this->assertTrue(
			$config->validator()->validate($token, new SignedWith(new SignerES256(null), InMemory::plainText($publicPem))),
			'The ES256 signature does not verify with the public key'
		);
	}

	public function testClientSecretIsEmptyWhenTheKeyMaterialIsBroken(): void
	{
		$this->assertSame('', $this->getSecretKeyLikeThePlugin('this is not a key', 'ABC123DEFG', 'TEAM123456'));
	}

	public static function idTokenProvider(): iterable
	{
		yield 'valid token'           => ['https://appleid.apple.com', self::APP_ID, '-1 minute', '+5 minutes', true];
		yield 'just expired (leeway)' => ['https://appleid.apple.com', self::APP_ID, '-10 minutes', '-10 seconds', true];
		yield 'expired'               => ['https://appleid.apple.com', self::APP_ID, '-10 minutes', '-5 minutes', false];
		yield 'issued in the future'  => ['https://appleid.apple.com', self::APP_ID, '+5 minutes', '+10 minutes', false];
		yield 'wrong issuer'          => ['https://evil.example.com', self::APP_ID, '-1 minute', '+5 minutes', false];
		yield 'wrong audience'        => ['https://appleid.apple.com', 'com.example.other', '-1 minute', '+5 minutes', false];
	}

	/**
	 * Mirrors the issuer, audience and time checks in AppleSocialLogin\Extension\Plugin::getSocialNetworkProfileInformation().
	 */
	#[DataProvider('idTokenProvider')]
	public function testIdTokenClaimsAreValidatedLikeThePlugin(
		string $issuer, string $audience, string $issuedAt, string $expiresAt, bool $expected
	): void
	{
		$privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
		openssl_pkey_export($privateKey, $privatePem);

		$signingConfig = JWTConfig::forAsymmetricSigner(
			new Signer\Rsa\Sha256(), InMemory::plainText($privatePem), InMemory::plainText($privatePem)
		);

		$now     = new DateTimeImmutable();
		$idToken = $signingConfig->builder()
			->issuedBy($issuer)
			->permittedFor($audience)
			->relatedTo('000000.0000000000000000000000000000.0000')
			->issuedAt($now->modify($issuedAt))
			->expiresAt($now->modify($expiresAt))
			->getToken($signingConfig->signer(), $signingConfig->signingKey())
			->toString();

		// As in the plugin: parse with a configuration built on the ES256 client-secret key material.
		$config = JWTConfig::forSymmetricSigner(new SignerES256(null), InMemory::plainText('unused-key-material'));
		$token  = $config->parser()->parse($idToken);

		$this->assertSame(
			$expected,
			$config->validator()->validate(
				$token,
				new LooseValidAt(SystemClock::fromUTC(), new DateInterval('PT30S')),
				new IssuedBy('https://appleid.apple.com'),
				new PermittedFor(self::APP_ID)
			)
		);
	}

	/**
	 * Mirrors AppleSocialLogin\Extension\Plugin::getSecretKey(). Keep it in step with the plugin.
	 */
	private function getSecretKeyLikeThePlugin(string $keyMaterial, string $keyID, string $teamID): string
	{
		$config = JWTConfig::forSymmetricSigner(new SignerES256(null), InMemory::plainText($keyMaterial));

		$time       = time();
		$expiration = new DateTimeImmutable('@' . ($time + 3600));
		$issuedAt   = new DateTimeImmutable('@' . $time);

		try
		{
			$token = $config->builder()
				->issuedBy($teamID)
				->withHeader('kid', $keyID)
				->permittedFor('https://appleid.apple.com')
				->issuedAt($issuedAt)
				->expiresAt($expiration)
				->relatedTo(self::APP_ID)
				->getToken($config->signer(), $config->signingKey());

			return $token->toString();
		}
		catch (Exception $e)
		{
			return '';
		}
	}
}
