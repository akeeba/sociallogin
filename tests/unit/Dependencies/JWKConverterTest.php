<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Akeeba\SocialLogin\Tests\Unit\Dependencies;

use Akeeba\Plugin\System\SocialLogin\Dependencies\CoderCat\JWKToPEM\Exception\Base64DecodeException;
use Akeeba\Plugin\System\SocialLogin\Dependencies\CoderCat\JWKToPEM\Exception\JWKConverterException;
use Akeeba\Plugin\System\SocialLogin\Dependencies\CoderCat\JWKToPEM\JWKConverter;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Configuration as JWTConfig;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer\Ecdsa\Sha256 as SignerES256;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer\Key\InMemory;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint\SignedWith;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Pins down the JWK → PEM conversion Sign in with Apple relies on to verify Apple's ID tokens
 * (AppleSocialLogin\Extension\Plugin::validateJWTSignature), across upgrades of codercat/jwk-to-pem and phpseclib.
 *
 * Three levels, from strictest to most essential:
 * 1. The PEM text is byte-identical to the golden output recorded in Fixtures/jwk-golden.json.
 * 2. The PEM describes the very same RSA public key (modulus and exponent) as the JWK.
 * 3. A PEM converted from a JWK verifies an RS256 token signed with the matching private key, through the same
 *    lcobucci/jwt calls the plugin makes, and rejects one signed with any other key.
 *
 * Level 1 may legitimately fail after a library upgrade (e.g. a different line ending) while levels 2 and 3 pass;
 * that is a reviewable change, not an automatic regression. Levels 2 and 3 must never fail.
 */
class JWKConverterTest extends TestCase
{
	public static function goldenProvider(): iterable
	{
		$golden = json_decode(
			file_get_contents(__DIR__ . '/../Fixtures/jwk-golden.json'), true, 512, JSON_THROW_ON_ERROR
		);

		foreach ($golden['cases'] as $case)
		{
			yield $case['name'] => [$case['jwk'], $case['pem']];
		}
	}

	#[DataProvider('goldenProvider')]
	public function testPemIsByteIdenticalToGoldenOutput(array $jwk, string $expectedPem): void
	{
		$this->assertSame($expectedPem, (new JWKConverter())->toPEM($jwk));
	}

	#[DataProvider('goldenProvider')]
	public function testPemDescribesTheSameRsaPublicKey(array $jwk, string $expectedPem): void
	{
		$pem = (new JWKConverter())->toPEM($jwk);
		$key = openssl_pkey_get_public($pem);

		$this->assertNotFalse($key, 'OpenSSL cannot parse the PEM produced by the converter');

		$details = openssl_pkey_get_details($key);

		$this->assertSame(OPENSSL_KEYTYPE_RSA, $details['type']);
		$this->assertSame(
			bin2hex(ltrim($this->base64UrlDecode($jwk['n']), "\0")),
			bin2hex(ltrim($details['rsa']['n'], "\0")),
			'Modulus mismatch'
		);
		$this->assertSame(
			bin2hex(ltrim($this->base64UrlDecode($jwk['e']), "\0")),
			bin2hex(ltrim($details['rsa']['e'], "\0")),
			'Public exponent mismatch'
		);
		$this->assertSame(strlen(ltrim($this->base64UrlDecode($jwk['n']), "\0")) * 8, (int) ceil($details['bits'] / 8) * 8);
	}

	public static function bigIntegerEngineProvider(): iterable
	{
		// phpseclib picks the first engine that works on the server; production servers may lack GMP or BCMath.
		yield 'GMP'    => ['GMP'];
		yield 'BCMath' => ['BCMath'];
		yield 'PHP64'  => ['PHP64'];
		yield 'PHP32'  => ['PHP32'];
	}

	/**
	 * Every BigInteger engine produces the golden output.
	 *
	 * phpseclib resolves its BigInteger engines (and their modular exponentiation engines) from class names built at
	 * runtime, which the scoping step has to patch. This makes sure every engine a server might end up with still
	 * resolves and converts correctly, not just the one picked on the machine running the tests.
	 *
	 * Runs in a separate process: phpseclib caches BigInteger constants made with the first engine it uses, so the
	 * engine cannot be switched once anything has been converted in the same process.
	 */
	#[DataProvider('bigIntegerEngineProvider')]
	#[RunInSeparateProcess]
	public function testEveryBigIntegerEngineProducesTheGoldenOutput(string $engine): void
	{
		$bigInteger = 'Akeeba\\Plugin\\System\\SocialLogin\\Dependencies\\phpseclib3\\Math\\BigInteger';

		try
		{
			$bigInteger::setEngine($engine);
		}
		catch (\Exception $e)
		{
			$this->markTestSkipped(sprintf('The %s engine is not available on this PHP: %s', $engine, $e->getMessage()));
		}

		$this->assertSame($engine, $bigInteger::getEngine()[0]);

		foreach (self::goldenProvider() as $name => [$jwk, $expectedPem])
		{
			$this->assertSame($expectedPem, (new JWKConverter())->toPEM($jwk), "Case $name with the $engine engine");
		}
	}

	public static function keySizeProvider(): iterable
	{
		yield '2048 bits' => [2048];
		yield '3072 bits' => [3072];
	}

	#[DataProvider('keySizeProvider')]
	public function testConvertedKeyVerifiesTokenSignedWithMatchingPrivateKey(int $bits): void
	{
		[$privatePem, $jwk] = $this->makeKeyPair($bits);

		$tokenString = $this->makeRs256Token($privatePem, $jwk['kid']);

		$this->assertTrue($this->validateLikeThePlugin($tokenString, [$jwk]));
	}

	public function testConvertedKeyRejectsTokenSignedWithAnotherKey(): void
	{
		[$attackerPrivatePem] = $this->makeKeyPair(2048);
		[, $appleJwk] = $this->makeKeyPair(2048);

		$tokenString = $this->makeRs256Token($attackerPrivatePem, $appleJwk['kid']);

		$this->assertFalse($this->validateLikeThePlugin($tokenString, [$appleJwk]));
	}

	public function testConvertedKeyRejectsTamperedToken(): void
	{
		[$privatePem, $jwk] = $this->makeKeyPair(2048);

		[$header, $payload, $signature] = explode('.', $this->makeRs256Token($privatePem, $jwk['kid']));

		$claims          = json_decode($this->base64UrlDecode($payload), true);
		$claims['email'] = 'attacker@example.com';
		$payload         = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');

		$this->assertFalse($this->validateLikeThePlugin("$header.$payload.$signature", [$jwk]));
	}

	public static function malformedJwkProvider(): iterable
	{
		$valid = ['kty' => 'RSA', 'kid' => 'x', 'n' => 'sXchDaQebHnPiGvyDOAT4saGEUetSyo9MKLOoWFsueri23bOdgWp4Dy1Wl', 'e' => 'AQAB'];

		yield 'missing n'          => [array_diff_key($valid, ['n' => 1]), JWKConverterException::class];
		yield 'missing e'          => [array_diff_key($valid, ['e' => 1]), JWKConverterException::class];
		yield 'missing kty'        => [array_diff_key($valid, ['kty' => 1]), JWKConverterException::class];
		yield 'EC key type'        => [['kty' => 'EC'] + $valid, JWKConverterException::class];
		yield 'private key'        => [$valid + ['d' => 'AQAB'], JWKConverterException::class];
		yield 'n is not base64url' => [['n' => '!!!!'] + $valid, Base64DecodeException::class];
		yield 'n is empty'         => [['n' => ''] + $valid, Base64DecodeException::class];
	}

	#[DataProvider('malformedJwkProvider')]
	public function testMalformedJwkThrowsTheExceptionsThePluginDeclares(array $jwk, string $exceptionClass): void
	{
		$this->expectException($exceptionClass);

		(new JWKConverter())->toPEM($jwk);
	}

	/**
	 * Mirrors the signature check in AppleSocialLogin\Extension\Plugin::validateJWTSignature().
	 *
	 * Keep it in step with the plugin. It is copied rather than called because the plugin method is bound to the
	 * Joomla plugin and application.
	 */
	private function validateLikeThePlugin(string $tokenString, array $jwkArray): bool
	{
		$config = JWTConfig::forSymmetricSigner(new SignerES256(null), InMemory::plainText('unused-key-material'));
		$token  = $config->parser()->parse($tokenString);

		if ($token->headers()->get('alg') !== 'RS256')
		{
			return false;
		}

		$signer       = new Signer\Rsa\Sha256();
		$keyID        = $token->headers()->get('kid');
		$jwkConverter = new JWKConverter();

		foreach ($jwkArray as $jwk)
		{
			if ($jwk['kid'] != $keyID)
			{
				continue;
			}

			$pemFile = $jwkConverter->toPEM($jwk);

			if ($config->validator()->validate($token, new SignedWith($signer, InMemory::plainText($pemFile))))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @return  array{0: string, 1: array}  The private key PEM and the public key as a JWK.
	 */
	private function makeKeyPair(int $bits): array
	{
		$key = openssl_pkey_new(['private_key_bits' => $bits, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

		$this->assertNotFalse($key, 'OpenSSL could not generate an RSA key');

		openssl_pkey_export($key, $privatePem);

		$rsa = openssl_pkey_get_details($key)['rsa'];
		$b64 = static fn(string $bin): string => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

		return [
			$privatePem,
			[
				'kty' => 'RSA',
				'kid' => bin2hex(random_bytes(5)),
				'use' => 'sig',
				'alg' => 'RS256',
				'n'   => $b64($rsa['n']),
				'e'   => $b64($rsa['e']),
			],
		];
	}

	private function makeRs256Token(string $privatePem, string $kid): string
	{
		$config = JWTConfig::forAsymmetricSigner(
			new Signer\Rsa\Sha256(),
			InMemory::plainText($privatePem),
			InMemory::plainText($privatePem)
		);

		$now = new DateTimeImmutable();

		return $config->builder()
			->issuedBy('https://appleid.apple.com')
			->permittedFor('com.example.service')
			->relatedTo('000000.0000000000000000000000000000.0000')
			->issuedAt($now)
			->expiresAt($now->modify('+5 minutes'))
			->withHeader('kid', $kid)
			->withClaim('email', 'user@example.com')
			->getToken($config->signer(), $config->signingKey())
			->toString();
	}

	private function base64UrlDecode(string $str): string
	{
		return base64_decode(strtr($str, '-_', '+/'), true);
	}
}
