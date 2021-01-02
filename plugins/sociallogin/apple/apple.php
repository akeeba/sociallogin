<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') || die();

use Akeeba\SocialLogin\Library\Data\UserData;
use Akeeba\SocialLogin\Library\Helper\Joomla;
use Akeeba\SocialLogin\Library\OAuth\OAuth2Client;
use Akeeba\SocialLogin\Library\Plugin\AbstractPlugin;
use Joomla\CMS\Crypt\Crypt;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Lcobucci\JWT\Builder as JWTBuilder;
use Lcobucci\JWT\Parser as JWTParser;
use Lcobucci\JWT\Signer\Ecdsa\Sha256 as SignerE256;
use Lcobucci\JWT\Signer\Key as SignerKey;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\ValidationData;

if (!class_exists('Akeeba\\SocialLogin\\Library\\Plugin\\AbstractPlugin', true))
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
class plgSocialloginApple extends AbstractPlugin
{
	/**
	 * The first name of the user logging in with Apple
	 *
	 * @var   string
	 * @since 3.2.0
	 */
	private $firstName;

	/**
	 * The last name of the user logging in with Apple
	 *
	 * @var   string
	 * @since 3.2.0
	 */
	private $lastName;

	/**
	 * The email address of the user logging in with Apple
	 *
	 * @var   string
	 * @since 3.2.0
	 */
	private $email;

	/**
	 * Constructor. Loads the language files as well.
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array    $config   An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 *
	 * @since   3.2.0
	 */
	public function __construct($subject, array $config = [])
	{
		parent::__construct($subject, $config);

		// Register the autoloader
		JLoader::register('plgSocialloginAppleRandomWords', __DIR__ . '/random_words.php');

		// Per-plugin customization
		$this->buttonImage = 'plg_sociallogin_apple/apple-white.png';
	}

	/**
	 * Processes the authentication callback from Apple.
	 *
	 * Note: this method is called from Joomla's com_ajax, not com_sociallogin itself
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 * @since   3.2.0
	 */
	public function onAjaxApple()
	{
		$this->onSocialLoginAjax();
	}

	/**
	 * Returns an OAuth2Client object
	 *
	 * @return  OAuth2Client
	 *
	 * @throws  Exception
	 * @since   3.2.0
	 */
	protected function getConnector()
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
			$httpClient      = Joomla::getHttpClient();
			$this->connector = new Akeeba\SocialLogin\Library\OAuth\OAuth2Client($options, $httpClient, $this->app->input, $this->app);

		}

		return $this->connector;
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
	 * Get the raw user profile information from Apple.
	 *
	 * @param   OAuth2Client  $connector  The internal connector object.
	 *
	 * @return  array
	 *
	 * @throws  Exception
	 * @since   3.2.0
	 *
	 * @see     https://developer.apple.com/documentation/sign_in_with_apple/generate_and_validate_tokens
	 */
	protected function getSocialNetworkProfileInformation($connector)
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
		$token = (new JWTParser())->parse($jwt);

		// Verify the token's signature – if we can connect to Apple's servers to retrieve the valid keys.
		$keyJson   = @file_get_contents('https://appleid.apple.com/auth/keys');
		$appleKeys = @json_decode($keyJson, true);
		$appleKeys = $appleKeys ?? [];
		$jwkArray  = $appleKeys['keys'] ?? [];

		if (!$this->validateJWTSignature($token, $jwkArray))
		{
			Joomla::log('apple', 'Invalid signature in received JWT: ' . $jwt, Log::ERROR);

			throw new RuntimeException('The login response received is not signed properly by Apple.');
		}

		// Validate the issuer, audience and time of the token
		$data = new ValidationData(time(), 30);
		$data->setIssuer('https://appleid.apple.com');
		$data->has('sub');
		$data->setAudience($this->appId);

		if (!$token->validate($data))
		{
			throw new RuntimeException('The login response received lacks the necessary fields set by Apple.');
		}

		// Verify the nonce (Joomla's anti-CSRF token).
		$nonceSupported = $token->getClaim('nonce_supported', false);
		$nonce          = $token->getClaim('nonce', '');

		if ($nonceSupported && !Crypt::timingSafeCompare($this->app->getSession()->getToken(), $nonce))
		{
			throw new RuntimeException('Invalid request.');
		}

		// Pass through information from the JWT. Note that the name is NEVER passed through the JWT (Apple doesn't have it)
		$ret['id']       = $token->getClaim('sub', '');
		$ret['email']    = $token->getClaim('email', '');
		$ret['verified'] = ($token->getClaim('real_user_status', 0) == 2) || ($token->getClaim('email_verified', 'false') === 'true');

		return $ret;
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
	protected function mapSocialProfileToUserData(array $socialProfile)
	{
		/**
		 * It is possible that no name was passed to me by Apple. In this case I need to create a fake name since it
		 * may be used for creating a new user. I use a random English adjective-noun pair, e.g. "Lunar Mood". You can
		 * change your name later and possibly your username (if the site admin allows it).
		 */
		$name = $socialProfile['name'] ?? '';

		if (empty($name))
		{
			$name = implode(' ', array_map('ucfirst', plgSocialloginAppleRandomWords::randomPair()));
		}

		$userData           = new UserData();
		$userData->name     = $name;
		$userData->id       = $socialProfile['id'] ?? '';
		$userData->email    = $socialProfile['email'] ?? '';
		$userData->verified = $socialProfile['verified'] ?? false;

		return $userData;
	}

	/**
	 * Is this integration properly set up and ready for use?
	 *
	 * @return  bool
	 * @since   3.2.0
	 */
	protected function isProperlySetUp()
	{
		$keyMaterial = $this->params->get('keyMaterial', '');
		$keyID       = $this->params->get('keyID', '');
		$teamID      = $this->params->get('teamID', '');

		return !(empty($this->appId) || empty($keyMaterial) || empty($keyID) || empty($teamID));
	}

	/**
	 * Creates the JWT which will serve as a secret key for the Apple OAuth2 implementation.
	 *
	 * They key is derived from the Services ID, Team ID, Key ID and the PEM-encoded private key. All of that
	 * information comes from the Apple Developer site and is part of your setup of Login with Apple.
	 *
	 * @return  string
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

		$signer     = new SignerE256();
		$privateKey = new SignerKey($keyMaterial);

		$time       = time();
		$expiration = new DateTimeImmutable('@' . ($time + 3600));
		$issuedAt   = new DateTimeImmutable('@' . $time);

		try
		{
			$token = (new JWTBuilder())->issuedBy($teamID)
				->withHeader('kid', $keyID)
				->permittedFor('https://appleid.apple.com')
				->issuedAt($issuedAt)
				->expiresAt($expiration)
				->relatedTo($this->appId)
				->getToken($signer, $privateKey);

			return (string) $token;
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
		if (empty($jwtArray))
		{
			return true;
		}

		// Get the correct signer based on the algorithm set in the JWT
		switch ($token->getHeader('alg'))
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
				$signer = new \Lcobucci\JWT\Signer\Ecdsa\Sha256();
				break;

			case 'ES384':
				$signer = new \Lcobucci\JWT\Signer\Ecdsa\Sha384();
				break;

			case 'ES512':
				$signer = new \Lcobucci\JWT\Signer\Ecdsa\Sha512();
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

		$keyID        = $token->getHeader('kid');
		$jwkConverter = new \CoderCat\JWKToPEM\JWKConverter();

		foreach ($jwkArray as $jwk)
		{
			// Make sure we have the correct Key ID
			if ($jwk['kid'] != $keyID)
			{
				continue;
			}

			// Convert the JSON Web Key to PEM-encoded PKCS#8 format and validate the JWT's signature.
			$pemFile  = $jwkConverter->toPEM($jwk);
			$validKey = $token->verify($signer, $pemFile);

			if ($validKey)
			{
				return true;
			}
		}

		return false;
	}
}
