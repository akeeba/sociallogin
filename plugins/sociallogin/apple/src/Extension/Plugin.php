<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\Sociallogin\Apple\Extension;

// Protect from unauthorized access
defined('_JEXEC') || die();

use DateTimeImmutable;
use Exception;
use Joomla\CMS\Crypt\Crypt;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Sociallogin\Apple\Util\RandomWords;
use Joomla\Plugin\System\SocialLogin\Library\Data\UserData;
use Joomla\Plugin\System\SocialLogin\Library\Helper\Joomla;
use Joomla\Plugin\System\SocialLogin\Library\OAuth\OAuth2Client;
use Joomla\Plugin\System\SocialLogin\Library\Plugin\AbstractPlugin;
use Lcobucci\JWT\Configuration as JWTConfig;
use Lcobucci\JWT\Signer\Ecdsa\Sha256 as SignerES256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token;
use RuntimeException;

if (!class_exists(AbstractPlugin::class, true))
{
	return;
}

/**
 * Akeeba Social Login plugin for Login with Apple integration
 *
 * @see   https://developer.okta.com/blog/2019/06/04/what-the-heck-is-sign-in-with-apple
 *
 * @since 3.2.0
 */
class Plugin extends AbstractPlugin
{
	/**
	 * The email address of the user logging in with Apple
	 *
	 * @since 3.2.0
	 * @var   string
	 */
	private string $email;

	/**
	 * The first name of the user logging in with Apple
	 *
	 * @since 3.2.0
	 * @var   string
	 */
	private string $firstName;

	/**
	 * The last name of the user logging in with Apple
	 *
	 * @since 3.2.0
	 * @var   string
	 */
	private string $lastName;

	/**
	 * Constructor. Loads the language files as well.
	 *
	 * @param   DispatcherInterface  &$subject  The object to observe
	 * @param   array                 $config   An optional associative array of configuration settings.
	 *                                          Recognized key values include 'name', 'group', 'params', 'language'
	 *                                          (this list is not meant to be comprehensive).
	 *
	 * @since   3.2.0
	 */
	public function __construct($subject, array $config = [])
	{
		$this->fgColor = '#FFFFFF';
		$this->bgColor = '#000000';

		parent::__construct($subject, $config);

		// Per-plugin customization
		$this->buttonImage = 'plg_sociallogin_apple/apple-white.svg';
	}

	/** @inheritDoc */
	public static function getSubscribedEvents(): array
	{
		return array_merge(
			parent::getSubscribedEvents(),
			[
				'onAjaxApple' => 'onSocialLoginAjax',
			]
		);
	}

	/**
	 * Returns an OAuth2Client object
	 *
	 * @return  OAuth2Client
	 *
	 * @throws  Exception
	 * @since   3.2.0
	 */
	protected function getConnector(): OAuth2Client
	{
		if (is_null($this->connector))
		{
			$this->appSecret = $this->getSecretKey();

			$options         = [
				'authurl'       => 'https://appleid.apple.com/auth/authorize',
				'tokenurl'      => 'https://appleid.apple.com/auth/token',
				'clientid'      => $this->appId,
				'clientsecret'  => $this->appSecret,
				'redirecturi'   => Uri::base() . 'index.php?option=com_ajax&group=sociallogin&plugin=' . $this->integrationName . '&format=raw',
				'scope'         => 'name email',
				'requestparams' => [
					'nonce'         => $this->app->getSession()->getToken(),
					'response_mode' => 'form_post',
				],
			];
			$httpClient      = HttpFactory::getHttp();
			$this->connector = new OAuth2Client($options, $httpClient, $this->app->input, $this->app);

		}

		return $this->connector;
	}

	/**
	 * Get the raw user profile information from Apple.
	 *
	 * @param   object  $connector  The internal connector object.
	 *
	 * @return  array
	 *
	 * @throws  Exception
	 * @since   3.2.0
	 *
	 * @see     https://developer.apple.com/documentation/sign_in_with_apple/generate_and_validate_tokens
	 */
	protected function getSocialNetworkProfileInformation(object $connector): array
	{
		$token = $connector->getToken();
		$jwt   = $token['id_token'] ?? null;

		$ret = [
			'id'       => '',
			'name'     => trim($this->firstName . ' ' . $this->lastName),
			'email'    => $this->email,
			'verified' => '',
		];

		if (empty($jwt))
		{
			return $ret;
		}

		// Parse the JWT token
		$keyMaterial = $this->params->get('keyMaterial', '');
		/** @noinspection PhpParamsInspection */
		$config = version_compare(JVERSION, '4.2.0', 'lt')
			? JWTConfig::forSymmetricSigner(SignerES256::create(), InMemory::plainText($keyMaterial))
			: JWTConfig::forSymmetricSigner(new SignerES256(null), InMemory::plainText($keyMaterial));
		$token  = $config->parser()->parse($jwt);

		// Verify the token's signature – if we can connect to Apple's servers to retrieve the valid keys.
		$keyJson   = @file_get_contents('https://appleid.apple.com/auth/keys');
		$appleKeys = @json_decode($keyJson ?: '[]', true);
		$appleKeys = $appleKeys ?? [];
		$jwkArray  = $appleKeys['keys'] ?? [];

		// We don't use the validator directly because we need to check against ANY of the valid signatures.
		if (!$this->validateJWTSignature($token, $jwkArray))
		{
			Log::add(
				sprintf(
					'Invalid signature in received JWT: %s',
					$jwt
				),
				Log::ERROR,
				'sociallogin.apple'
			);

			throw new RuntimeException('The login response received is not signed properly by Apple.');
		}

		// Validate the issuer, audience and time of the token
		/** @noinspection PhpUndefinedClassInspection */
		/** @noinspection PhpUndefinedNamespaceInspection */
		/** @noinspection PhpParamsInspection */
		if (!$config->validator()->validate(
			$token,
			new \Lcobucci\JWT\Validation\Constraint\LooseValidAt(Lcobucci\Clock\SystemClock::fromUTC(), new DateInterval('PT30S')),
			new \Lcobucci\JWT\Validation\Constraint\IssuedBy('https://appleid.apple.com'),
			new \Lcobucci\JWT\Validation\Constraint\PermittedFor($this->appId)
		))
		{
			throw new RuntimeException('The login response received lacks the necessary fields set by Apple.');
		}

		// Verify the nonce (Joomla's anti-CSRF token).
		$claims         = $token->claims();
		$nonceSupported = $claims->get('nonce_supported', false);
		$nonce          = $claims->get('nonce', '');

		if ($nonceSupported && !Crypt::timingSafeCompare($this->app->getSession()->getToken(), $nonce))
		{
			throw new RuntimeException('Invalid request.');
		}

		// Pass through information from the JWT. Note that the name is NEVER passed through the JWT (Apple doesn't have it)
		$ret['id']       = $claims->get('sub', '');
		$ret['email']    = $claims->get('email', '');
		$ret['verified'] = ($claims->get('real_user_status', 0) == 2) || ($claims->get('email_verified', 'false') === 'true');

		return $ret;
	}

	/**
	 * Get the OAuth / OAuth2 token from the social network. Used in the onAjax* handler.
	 *
	 * At this point we have a code and possibly the user's name and email address. So we need to save this optional
	 * information which will be used when getSocialNetworkProfileInformation is called later on.
	 *
	 * @return  array|bool  False if we could not retrieve it. Otherwise [$token, $connector]
	 *
	 * @throws  Exception
	 * @since   3.2.0
	 *
	 * @see     https://developer.apple.com/documentation/sign_in_with_apple/sign_in_with_apple_js/incorporating_sign_in_with_apple_into_other_platforms
	 */
	protected function getToken()
	{
		$input = $this->app->input;

		$userJson = $input->post->get('user', '{}', 'raw');
		$userData = @json_decode($userJson, true);
		$userData = $userData ?? [];

		$nameData        = $userData['name'] ?? ['firstName' => '', 'lastName' => ''];
		$this->firstName = $nameData['firstName'] ?? '';
		$this->lastName  = $nameData['lastName'] ?? '';
		$this->email     = $nameData['email'] ?? '';

		return parent::getToken();
	}

	/**
	 * Is this integration properly set up and ready for use?
	 *
	 * @return  bool
	 * @since   3.2.0
	 */
	protected function isProperlySetUp(): bool
	{
		$keyMaterial = $this->params->get('keyMaterial', '');
		$keyID       = $this->params->get('keyID', '');
		$teamID      = $this->params->get('teamID', '');

		return !(empty($this->appId) || empty($keyMaterial) || empty($keyID) || empty($teamID));
	}

	/**
	 * Maps the raw social network profile fields retrieved with getSocialNetworkProfileInformation() into a UserData
	 * object we use in the Social Login library.
	 *
	 * @param   array  $socialProfile  The raw social profile fields
	 *
	 * @return  UserData
	 * @since   3.2.0
	 */
	protected function mapSocialProfileToUserData(array $socialProfile): UserData
	{
		/**
		 * It is possible that no name was passed to me by Apple. In this case I need to create a fake name since it
		 * may be used for creating a new user. I use a random English adjective-noun pair, e.g. "Lunar Mood". You can
		 * change your name later and possibly your username (if the site admin allows it).
		 */
		$name = $socialProfile['name'] ?? '';

		if (empty($name))
		{
			$name = implode(' ', array_map('ucfirst', RandomWords::randomPair()));
		}

		$userData           = new UserData();
		$userData->name     = $name;
		$userData->id       = $socialProfile['id'] ?? '';
		$userData->email    = $socialProfile['email'] ?? '';
		$userData->verified = $socialProfile['verified'] ?? false;

		return $userData;
	}

	/**
	 * Creates the JWT which will serve as a secret key for the Apple OAuth2 implementation.
	 *
	 * They key is derived from the Services ID, Team ID, Key ID and the PEM-encoded private key. All of that
	 * information comes from the Apple Developer site and is part of your setup of Login with Apple.
	 *
	 * @return  string
	 * @throws  Exception
	 * @since   3.2.0
	 */
	private function getSecretKey(): string
	{
		$keyMaterial = $this->params->get('keyMaterial', '');
		$keyID       = $this->params->get('keyID', '');
		$teamID      = $this->params->get('teamID', '');

		if (empty($keyMaterial) || empty($keyID) || empty($teamID))
		{
			return '';
		}

		/** @noinspection PhpParamsInspection */
		$config = version_compare(JVERSION, '4.2.0', 'lt')
			? JWTConfig::forSymmetricSigner(SignerES256::create(), InMemory::plainText($keyMaterial))
			: JWTConfig::forSymmetricSigner(new SignerES256(null), InMemory::plainText($keyMaterial));

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
			                ->relatedTo($this->appId)
			                ->getToken($config->signer(), $config->signingKey());

			return $token->toString();
		}
		catch (Exception $e)
		{
			// Guards against bad configuration leading into internal error in the JWT library
			return '';
		}
	}

	/**
	 * Validates the signature of a JSON Web Token.
	 *
	 * Caveat: due to third party library implementation it will only work with RS256 keys which incidentally is what
	 * Apple is using at the time of this writing (August 2020).
	 *
	 * @param   Token  $token     The parsed JWT token to verify the signature for
	 * @param   array  $jwkArray  An array of one or more JSON Web Keys (JWKs)
	 *
	 * @return bool
	 *
	 * @throws \CoderCat\JWKToPEM\Exception\Base64DecodeException
	 * @throws \CoderCat\JWKToPEM\Exception\JWKConverterException
	 * @since   3.2.0
	 */
	private function validateJWTSignature(Token $token, array $jwkArray): bool
	{
		// No keys? I will say it's valid.
		if (empty($jwkArray))
		{
			return true;
		}

		// Get the correct signer based on the algorithm set in the JWT
		switch ($token->headers()->get('alg'))
		{
			case 'RS256':
			default:
				$signer = new \Lcobucci\JWT\Signer\Rsa\Sha256();
				break;

			case 'RS384':
				$signer = new \Lcobucci\JWT\Signer\Rsa\Sha384();
				break;

			case 'RS512':
				$signer = new \Lcobucci\JWT\Signer\Rsa\Sha512();
				break;

			case 'ES256':
				$signer = \Lcobucci\JWT\Signer\Ecdsa\Sha256::create();
				break;

			case 'ES384':
				$signer = \Lcobucci\JWT\Signer\Ecdsa\Sha384::create();
				break;

			case 'ES512':
				$signer = \Lcobucci\JWT\Signer\Ecdsa\Sha512::create();
				break;

			case 'HS256':
				$signer = new \Lcobucci\JWT\Signer\Hmac\Sha256();
				break;

			case 'HS384':
				$signer = new \Lcobucci\JWT\Signer\Hmac\Sha384();
				break;

			case 'HS512':
				$signer = new \Lcobucci\JWT\Signer\Hmac\Sha512();
				break;
		}

		$keyMaterial = $this->params->get('keyMaterial', '');
		/** @noinspection PhpParamsInspection */
		$config = version_compare(JVERSION, '4.2.0', 'lt')
			? JWTConfig::forSymmetricSigner(SignerES256::create(), InMemory::plainText($keyMaterial))
			: JWTConfig::forSymmetricSigner(new SignerES256(null), InMemory::plainText($keyMaterial));

		$keyID        = $token->headers()->get('kid');
		$jwkConverter = new \CoderCat\JWKToPEM\JWKConverter();

		foreach ($jwkArray as $jwk)
		{
			// Make sure we have the correct Key ID
			if ($jwk['kid'] != $keyID)
			{
				continue;
			}

			// Convert the JSON Web Key to PEM-encoded PKCS#8 format and validate the JWT's signature.
			$pemFile = $jwkConverter->toPEM($jwk);

			if ($config->validator()->validate($token, new \Lcobucci\JWT\Validation\Constraint\SignedWith($signer, InMemory::plainText($pemFile))))
			{
				return true;
			}
		}

		return false;
	}
}
