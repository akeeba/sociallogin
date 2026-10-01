<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Sociallogin\Apple\Extension;

// Protect from unauthorized access
defined('_JEXEC') || die();

use Akeeba\Plugin\Sociallogin\Apple\Util\RandomWords;
use Akeeba\Plugin\System\SocialLogin\Dependencies\CoderCat\JWKToPEM\Exception\Base64DecodeException;
use Akeeba\Plugin\System\SocialLogin\Dependencies\CoderCat\JWKToPEM\Exception\JWKConverterException;
use Akeeba\Plugin\System\SocialLogin\Dependencies\CoderCat\JWKToPEM\JWKConverter;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\Clock\SystemClock;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Configuration as JWTConfig;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer\Ecdsa\Sha256 as SignerES256;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer\Key\InMemory;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Token;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint\SignedWith;
use Akeeba\Plugin\System\SocialLogin\Library\Data\UserData;
use Akeeba\Plugin\System\SocialLogin\Library\OAuth\OAuth2Client;
use Akeeba\Plugin\System\SocialLogin\Library\Plugin\AbstractPlugin;
use DateInterval;
use DateTimeImmutable;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Cache\CacheControllerFactoryAwareTrait;
use Joomla\CMS\Cache\Controller\CallbackController;
use Joomla\CMS\Crypt\Crypt;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Http\HttpFactory;
use Joomla\Session\SessionInterface;
use JsonException;
use RuntimeException;

if (!class_exists(AbstractPlugin::class))
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
	use CacheControllerFactoryAwareTrait;

	/**
	 * The cache controller for caching Apple's JSON Web Key Set
	 *
	 * @var   CallbackController|null
	 * @since 4.11.1
	 */
	private ?CallbackController $jwksCacheController = null;

	/**
	 * The email address of the user logging in with Apple
	 *
	 * @var   string
	 * @since 3.2.0
	 */
	private string $email;

	/**
	 * The first name of the user logging in with Apple
	 *
	 * @var   string
	 * @since 3.2.0
	 */
	private string $firstName;

	/**
	 * The last name of the user logging in with Apple
	 *
	 * @var   string
	 * @since 3.2.0
	 */
	private string $lastName;

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

	/** @inheritDoc */
	public function init(): void
	{
		$this->fgColor = '#FFFFFF';
		$this->bgColor = '#000000';

		parent::init();

		// Per-plugin customization
		$this->buttonImage = 'plg_sociallogin_apple/apple-white.svg';
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

			/**
			 * The nonce for the authorize request is generated when the login button URL is created (see
			 * getLoginButtonURL) and bound to the user's session. Here we only read it back; on the callback request
			 * it is validated against the nonce claim in Apple's token before being removed from the session.
			 */
			/** @var SessionInterface $session */
			$session = $this->getApplication()->getSession();
			$nonce   = $session->get('plg_sociallogin_apple.nonce', '');

			if (empty($nonce))
			{
				$nonce = hash('sha1', random_bytes(64));
			}

			$options         = [
				'authurl'       => 'https://appleid.apple.com/auth/authorize',
				'tokenurl'      => 'https://appleid.apple.com/auth/token',
				'clientid'      => $this->appId,
				'clientsecret'  => $this->appSecret,
				'redirecturi'   => Uri::root() . 'index.php?option=com_ajax&group=sociallogin&plugin='
				                   . $this->integrationName . '&format=raw',
				'scope'         => 'name email',
				'requestparams' => [
					'nonce'         => $nonce,
					'response_mode' => 'form_post',
				],
			];
			$httpClient      = (new HttpFactory())->getHttp();
			$this->connector = new OAuth2Client(
				$options, $httpClient, $this->getapplication()->getInput(), $this->getApplication()
			);

		}

		return $this->connector;
	}

	/**
	 * Return the URL for the login button
	 *
	 * Each authorization request gets its own nonce, stored in the user's session. This binds the login response
	 * Apple sends back to the individual login attempt which generated it, instead of having a single nonce reused
	 * for every attempt in the same session.
	 *
	 * @return  string
	 *
	 * @throws  Exception
	 * @since   4.11.1
	 */
	protected function getLoginButtonURL(): string
	{
		// Generate a fresh nonce for this authorization request and bind it to the session.
		/** @var SessionInterface $session */
		$session = $this->getApplication()->getSession();
		$nonce   = hash('sha1', random_bytes(64));

		$session->set('plg_sociallogin_apple.nonce', $nonce);

		return parent::getLoginButtonURL();
	}

	/**
	 * Get the raw user profile information from Apple.
	 *
	 * @param   object  $connector  The internal connector object.
	 *
	 * @return  array|null
	 *
	 * @throws  Exception
	 * @since   3.2.0
	 *
	 * @see     https://developer.apple.com/documentation/sign_in_with_apple/generate_and_validate_tokens
	 */
	protected function getSocialNetworkProfileInformation(object $connector): ?array
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
			return null;
		}

		// Parse the JWT token
		$keyMaterial = $this->params->get('keyMaterial', '');
		$config      = JWTConfig::forSymmetricSigner(new SignerES256(null), InMemory::plainText($keyMaterial));
		$token       = $config->parser()->parse($jwt);

		// Verify the token's signature against Apple's JSON Web Key Set. If Apple's keys cannot be retrieved the
		// login fails closed: we never validate a signature without keys.
		try
		{
			$jwkArray = $this->getAppleSigningKeys();
		}
		catch (Exception $e)
		{
			Log::add(
				sprintf('Could not retrieve Apple\'s signing keys: %s', $e->getMessage()),
				Log::ERROR,
				'sociallogin.apple'
			);

			throw new RuntimeException('The login response received cannot be verified at this time.');
		}

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
		if (!$config->validator()->validate(
			$token,
			new LooseValidAt(SystemClock::fromUTC(), new DateInterval('PT30S')),
			new IssuedBy('https://appleid.apple.com'),
			new PermittedFor($this->appId)
		))
		{
			throw new RuntimeException('The login response received lacks the necessary fields set by Apple.');
		}

		// Verify the nonce (Joomla's anti-CSRF token). This check fails closed: it rejects the login response when
		// the nonce claim is missing or unsupported, when there is no reference nonce in the session, or when the
		// two nonces do not match.
		/** @var SessionInterface $session */
		$session        = $this->getApplication()->getSession();
		$claims         = $token->claims();
		$nonceSupported = $claims->get('nonce_supported', false);
		$incomingNonce  = (string) $claims->get('nonce', '');
		$referenceNonce = $session->get('plg_sociallogin_apple.nonce', null);

		if (
			!$nonceSupported
			|| empty($referenceNonce)
			|| empty($incomingNonce)
			|| !Crypt::timingSafeCompare($referenceNonce, $incomingNonce)
		)
		{
			throw new RuntimeException('Invalid request.');
		}

		$session->remove('plg_sociallogin_apple.nonce');

		// Pass through information from the JWT. Note that the name is NEVER passed through the JWT (Apple doesn't have it)
		$ret['id']       = $claims->get('sub', '');
		$ret['email']    = $claims->get('email', '');
		$ret['verified'] = ($claims->get('email_verified', 'false') === 'true')
			|| $claims->get('email_verified', 'false') === true;

		return $ret;
	}

	/**
	 * Get the OAuth / OAuth2 token from the social network. Used in the onAjax* handler.
	 *
	 * At this point we have a code and possibly the user's name and email address. So we need to save this optional
	 * information which will be used when getSocialNetworkProfileInformation is called later on.
	 *
	 * @return  array|bool  False if we could not retrieve it. Otherwise, [$token, $connector]
	 *
	 * @throws  Exception
	 * @since   3.2.0
	 *
	 * @see     https://developer.apple.com/documentation/sign_in_with_apple/sign_in_with_apple_js/incorporating_sign_in_with_apple_into_other_platforms
	 */
	protected function getToken()
	{
		$input = $this->getapplication()->getInput();

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
				->relatedTo($this->appId)
				->getToken($config->signer(), $config->signingKey());

			return $token->toString();
		}
		catch (Exception $e)
		{
			// Guards against bad configuration leading into internal error in the JWT library.
			return '';
		}
	}

	/**
	 * Validates the signature of a JSON Web Token.
	 *
	 * Apple only ever issues RS256 tokens. The signer is pinned to RSA with SHA-256 and tokens carrying any other
	 * algorithm in their header are rejected outright, instead of deriving the verification method from the
	 * unverified token header.
	 *
	 * @param   Token  $token     The parsed JWT token to verify the signature for
	 * @param   array  $jwkArray  An array of one or more JSON Web Keys (JWKs)
	 *
	 * @return bool
	 *
	 * @throws Base64DecodeException
	 * @throws JWKConverterException
	 * @since   3.2.0
	 */
	private function validateJWTSignature(Token $token, array $jwkArray): bool
	{
		// No keys? The signature cannot be verified, therefore the login fails closed.
		if (empty($jwkArray))
		{
			return false;
		}

		// Apple only ever signs its tokens with RS256. Never trust the algorithm in the unverified token header.
		if ($token->headers()->get('alg') !== 'RS256')
		{
			return false;
		}

		$signer = new Signer\Rsa\Sha256();

		$keyMaterial = $this->params->get('keyMaterial', '');
		$config      = JWTConfig::forSymmetricSigner(new SignerES256(null), InMemory::plainText($keyMaterial));

		$keyID        = $token->headers()->get('kid');
		$jwkConverter = new JWKConverter();

		foreach ($jwkArray as $jwk)
		{
			// Make sure we have the correct Key ID
			if ($jwk['kid'] != $keyID)
			{
				continue;
			}

			// Convert the JSON Web Key to PEM-encoded PKCS#8 format and validate the JWT's signature.
			$pemFile = $jwkConverter->toPEM($jwk);

			if ($config->validator()->validate($token, new SignedWith($signer, InMemory::plainText($pemFile))))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Get Apple's JSON Web Key Set (JWKS), cached for up to 24 hours so that a transient connectivity problem
	 * towards Apple's servers does not block logins.
	 *
	 * @return  array  The keys in Apple's JSON Web Key Set
	 *
	 * @throws  RuntimeException  When the key set cannot be retrieved, or is empty
	 * @since   4.11.1
	 */
	private function getAppleSigningKeys(): array
	{
		$callbackController = $this->getJWKSCacheController();

		if (!$callbackController instanceof CallbackController)
		{
			// No cache available. Fetch the key set directly; it still fails closed on error.
			return $this->fetchAppleSigningKeys();
		}

		return $callbackController->get(
			fn() => $this->fetchAppleSigningKeys(), []
		);
	}

	/**
	 * Get, possibly creating afresh, the cache controller for Apple's JSON Web Key Set
	 *
	 * @return  CallbackController|null
	 * @since   4.11.1
	 */
	private function getJWKSCacheController(): ?CallbackController
	{
		if ($this->jwksCacheController instanceof CallbackController)
		{
			return $this->jwksCacheController;
		}

		$application = $this->getApplication();

		if (!$application instanceof CMSApplication)
		{
			return null;
		}

		$options = [
			'defaultgroup' => 'plg_sociallogin_apple',
			'cachebase'    => $application->get('cache_path', JPATH_CACHE),
			'lifetime'     => 86400,
			'language'     => $application->get('language', 'en-GB'),
			'storage'      => $application->get('cache_handler', 'file'),
			'locking'      => true,
			'locktime'     => 15,
			'checkTime'    => true,
			'caching'      => true,
		];

		/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
		$this->jwksCacheController = $this->getCacheControllerFactory()
			->createCacheController('callback', $options);

		return $this->jwksCacheController;
	}

	/**
	 * Fetches Apple's JSON Web Key Set over HTTP, using the same transport as the rest of the plugin.
	 *
	 * @return  array  The keys in Apple's JSON Web Key Set
	 *
	 * @throws  RuntimeException  When the key set cannot be retrieved, or is empty
	 * @since   4.11.1
	 */
	private function fetchAppleSigningKeys(): array
	{
		try
		{
			$http     = (new HttpFactory())->getHttp();
			$response = $http->get('https://appleid.apple.com/auth/keys');
		}
		catch (Exception $e)
		{
			throw new RuntimeException(
				sprintf('Could not connect to Apple: %s', $e->getMessage()), 0, $e
			);
		}

		if ($response->getStatusCode() !== 200)
		{
			throw new RuntimeException(
				sprintf('Apple returned HTTP status %d when retrieving the signing keys.', $response->getStatusCode())
			);
		}

		try
		{
			$appleKeys = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
		}
		catch (JsonException $e)
		{
			throw new RuntimeException('Apple returned a malformed JSON Web Key Set.', 0, $e);
		}

		if (!is_array($appleKeys))
		{
			throw new RuntimeException('Apple returned a malformed JSON Web Key Set.');
		}

		$jwkArray = $appleKeys['keys'] ?? [];

		if (empty($jwkArray))
		{
			throw new RuntimeException('Apple returned an empty JSON Web Key Set.');
		}

		return $jwkArray;
	}
}
