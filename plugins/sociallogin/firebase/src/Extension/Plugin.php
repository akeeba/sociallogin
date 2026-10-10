<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Sociallogin\Firebase\Extension;

defined('_JEXEC') || die();

use Akeeba\Plugin\Sociallogin\Firebase\Integration\OAuth as FirebaseOAuth;
use Akeeba\Plugin\System\SocialLogin\Dependencies\CoderCat\JWKToPEM\Exception\Base64DecodeException;
use Akeeba\Plugin\System\SocialLogin\Dependencies\CoderCat\JWKToPEM\Exception\JWKConverterException;
use Akeeba\Plugin\System\SocialLogin\Dependencies\CoderCat\JWKToPEM\JWKConverter;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\Clock\SystemClock;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Configuration as JWTConfig;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer\Key\InMemory;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Token;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint\SignedWith;
use Akeeba\Plugin\System\SocialLogin\Library\Data\PluginConfiguration;
use Akeeba\Plugin\System\SocialLogin\Library\Data\UserData;
use Akeeba\Plugin\System\SocialLogin\Library\Exception\Login\LoginError;
use Akeeba\Plugin\System\SocialLogin\Library\Plugin\AbstractPlugin;
use DateInterval;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Cache\CacheControllerFactoryAwareTrait;
use Joomla\CMS\Cache\Controller\CallbackController;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\Event;
use Joomla\Http\HttpFactory;
use JsonException;
use RuntimeException;

if (!class_exists(AbstractPlugin::class))
{
	return;
}

/**
 * Akeeba Social Login plugin for Firebase Authentication
 *
 * @since 4.12.0
 */
class Plugin extends AbstractPlugin
{
	use CacheControllerFactoryAwareTrait;

	/**
	 * Cache controller for Google's Firebase JSON Web Key Set (JWKS)
	 *
	 * @var   CallbackController|null
	 */
	private ?CallbackController $jwksCacheController = null;

	/**
	 * Firebase Project ID
	 *
	 * @var   string
	 */
	protected string $projectId = '';

	/**
	 * Firebase Web API Key
	 *
	 * @var   string
	 */
	protected string $apiKey = '';

	/**
	 * Firebase Auth Domain
	 *
	 * @var   string
	 */
	protected string $authDomain = '';

	/**
	 * Enabled sign-in providers for the buttons
	 *
	 * @var   array
	 */
	protected array $enabledProviders = [];

	/** @inheritDoc */
	public static function getSubscribedEvents(): array
	{
		return array_merge(
			parent::getSubscribedEvents(),
			[
				'onAjaxFirebase' => 'onSocialLoginAjax',
			]
		);
	}

	/** @inheritDoc */
	public function init(): void
	{
		$this->bgColor     = '#FFCA28';
		$this->fgColor     = '#2F2F2F';
		$this->buttonImage = 'plg_sociallogin_firebase/firebase.svg';
		$this->icon        = 'fa fa-fire fa-fw me-1';

		parent::init();

		$this->projectId  = trim((string) $this->params->get('project_id', ''));
		$this->apiKey     = trim((string) $this->params->get('api_key', ''));
		$this->authDomain = trim((string) $this->params->get('auth_domain', ''));

		$this->appId     = $this->projectId;
		$this->appSecret = $this->apiKey;

		if (empty($this->authDomain) && !empty($this->projectId))
		{
			$this->authDomain = $this->projectId . '.firebaseapp.com';
		}

		$providers = $this->params->get('providers', ['google', 'facebook', 'password']);
		$this->enabledProviders = is_array($providers) ? $providers : explode(',', (string) $providers);
		$this->enabledProviders = array_filter(array_map('trim', $this->enabledProviders));

		if (empty($this->enabledProviders))
		{
			$this->enabledProviders = ['google', 'facebook', 'password'];
		}
	}

	/**
	 * Returns an OAuth connector object for the integration
	 *
	 * @return  FirebaseOAuth
	 */
	protected function getConnector(): FirebaseOAuth
	{
		if (!is_null($this->connector))
		{
			return $this->connector;
		}

		/** @var CMSApplication $application */
		$application = $this->getApplication();
		$options     = [
			'clientid'     => $this->projectId,
			'clientsecret' => $this->apiKey,
			'authurl'      => sprintf('https://%s/__/auth/handler', $this->authDomain),
			'tokenurl'     => sprintf('https://%s/__/auth/handler', $this->authDomain),
			'redirecturi'  => sprintf(
				'%sindex.php?option=com_ajax&group=sociallogin&plugin=%s&format=raw',
				Uri::root(),
				$this->integrationName
			),
		];
		$httpClient      = (new HttpFactory())->getHttp();
		$this->connector = new FirebaseOAuth($options, $httpClient, $application->getInput(), $application);

		return $this->connector;
	}

	/**
	 * Injects Firebase login buttons and registers frontend assets
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 * @throws  Exception
	 */
	public function onSocialLoginGetLoginButton(Event $event)
	{
		if (!$this->isAllowedInThisApplication() || !$this->isProperlySetUp())
		{
			return;
		}

		/**
		 * @var   string $loginURL   The URL to be redirected to upon successful login / account link
		 * @var   string $failureURL The URL to be redirected to on error
		 */
		[$loginURL, $failureURL] = array_values($event->getArguments());
		$result = $event->getArgument('result') ?: [];
		$result = is_array($result) ? $result : [$result];

		if (empty($loginURL))
		{
			$loginURL = Uri::getInstance()->toString([
				'scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'fragment',
			]);
		}

		if (empty($failureURL))
		{
			$failureURL = $loginURL;
		}

		$session = $this->getApplication()->getSession();
		$session->set('plg_sociallogin_' . $this->integrationName . '.loginUrl', $loginURL);
		$session->set('plg_sociallogin_' . $this->integrationName . '.failureUrl', $failureURL);

		$ajaxUrl = Uri::root() . 'index.php?option=com_ajax&group=sociallogin&plugin=' . $this->integrationName . '&format=raw';

		// Inject client-side Firebase SDK, stylesheet, and script options
		$doc = $this->getApplication()->getDocument();
		if ($doc instanceof HtmlDocument)
		{
			$doc->addScriptOptions('plg_sociallogin_firebase', [
				'projectId'  => $this->projectId,
				'apiKey'     => $this->apiKey,
				'authDomain' => $this->authDomain,
				'ajaxUrl'    => $ajaxUrl,
				'providers'  => array_values($this->enabledProviders),
			]);

			$doc->addStyleSheet(Uri::root(true) . '/media/plg_sociallogin_firebase/css/firebase.css');
			$doc->addScript('https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js', [], ['defer' => true]);
			$doc->addScript('https://www.gstatic.com/firebasejs/10.12.0/firebase-auth-compat.js', [], ['defer' => true]);
			$doc->addScript(Uri::root(true) . '/media/plg_sociallogin_firebase/js/firebase.js', [], ['defer' => true]);
		}

		// Single Firebase button that launches the provider selection popup modal
		$result[] = [
			'slug'      => $this->integrationName,
			'link'      => $ajaxUrl,
			'tooltip'   => Text::_('PLG_SOCIALLOGIN_FIREBASE_LOGIN_DESC'),
			'label'     => Text::_('PLG_SOCIALLOGIN_FIREBASE_LOGIN_LABEL'),
			'img'       => HTMLHelper::image($this->buttonImage, '', [], true),
			'rawimage'  => $this->buttonImage,
			'icon'      => $this->icon,
			'bgColor'   => $this->useCustomCSS ? $this->bgColor : '#FFCA28',
			'fgColor'   => $this->useCustomCSS ? $this->fgColor : '#2F2F2F',
			'customCSS' => $this->useCustomCSS,
		];

		$event->setArgument('result', $result);
	}

	/**
	 * Handles the AJAX callback and token validation from the Firebase Web SDK
	 *
	 * @param   Event|null  $e
	 *
	 * @return  void
	 * @throws  Exception
	 */
	public function onSocialLoginAjax(?Event $e = null): void
	{
		if (!$this->isAllowedInThisApplication())
		{
			return;
		}

		Log::add('Begin handling of Firebase authentication callback', Log::DEBUG, 'sociallogin.' . $this->integrationName);

		$session    = $this->getApplication()->getSession();
		$returnURL  = $session->get('plg_system_sociallogin.returnUrl', Uri::base());
		$loginUrl   = $session->get('plg_sociallogin_' . $this->integrationName . '.loginUrl', $returnURL);
		$failureUrl = $session->get('plg_sociallogin_' . $this->integrationName . '.failureUrl', $loginUrl);

		$session->set('plg_sociallogin_' . $this->integrationName . '.loginUrl', null);
		$session->set('plg_sociallogin_' . $this->integrationName . '.failureUrl', null);

		$input = $this->getApplication()->getInput();

		// Read token and return URL from raw JSON or form-encoded POST
		$rawInput = file_get_contents('php://input');
		$jsonData = json_decode($rawInput, true) ?: [];

		// Handle resolve_identifier helper action (translates Joomla username to email for Firebase login)
		if (($jsonData['action'] ?? '') === 'resolve_identifier')
		{
			$identifier = trim((string) ($jsonData['identifier'] ?? ''));
			if (empty($identifier))
			{
				$this->sendAjaxResponse(false, 'Identifier cannot be empty.');
				return;
			}

			if (strpos($identifier, '@') !== false)
			{
				$this->sendAjaxResponse(true, '', '', ['email' => $identifier]);
				return;
			}

			$db = $this->getDatabase();
			$query = $db->getQuery(true)
				->select($db->quoteName('email'))
				->from($db->quoteName('#__users'))
				->where($db->quoteName('username') . ' = :username')
				->bind(':username', $identifier);
			$db->setQuery($query);
			$email = (string) $db->loadResult();

			if (!empty($email))
			{
				$this->sendAjaxResponse(true, '', '', ['email' => $email]);
				return;
			}

			$this->sendAjaxResponse(false, 'No user found with username: ' . $identifier);
			return;
		}

		$tokenStr = $jsonData['token'] ?? $input->getString('token', '');
		$rawReturn = $jsonData['return'] ?? $input->getString('return', '');

		if (empty($tokenStr))
		{
			$this->sendAjaxResponse(false, Text::_('PLG_SOCIALLOGIN_FIREBASE_ERROR_NO_TOKEN'), $failureUrl);
			return;
		}

		try
		{
			$socialProfile = $this->verifyFirebaseToken($tokenStr);

			if (!is_array($socialProfile) || empty($socialProfile['email']))
			{
				throw new RuntimeException('Firebase token verification failed or missing email address.');
			}

			$userData = $this->mapSocialProfileToUserData($socialProfile);

			$pluginConfiguration                   = new PluginConfiguration();
			$pluginConfiguration->canLoginUnlinked = $this->canLoginUnlinked;
			$pluginConfiguration->canCreateAlways  = $this->canCreateAlways;
			$pluginConfiguration->canCreateNewUsers = $this->canCreateNewUsers;
			$pluginConfiguration->canBypassValidation = $this->canBypassValidation;

			$userProfileData = [
				'userid'     => $userData->id,
				'pictureUrl' => $socialProfile['picture'] ?? null,
			];

			$this->handleSocialLogin($this->integrationName, $pluginConfiguration, $userData, $userProfileData);

			// Resolve redirection URL
			$destination = $this->resolveRedirectUrl($rawReturn, $loginUrl);

			$this->sendAjaxResponse(true, '', $destination);
		}
		catch (LoginError $le)
		{
			Log::add('Firebase login error: ' . $le->getMessage(), Log::ERROR, 'sociallogin.' . $this->integrationName);
			$this->sendAjaxResponse(false, $le->getMessage(), $failureUrl);
		}
		catch (Exception $e)
		{
			Log::add('Firebase authentication error: ' . $e->getMessage(), Log::ERROR, 'sociallogin.' . $this->integrationName);
			$publicMessage = (defined('JDEBUG') && JDEBUG)
				? $e->getMessage()
				: Text::_('PLG_SYSTEM_SOCIALLOGIN_ERR_LOGINFAILED');

			$this->sendAjaxResponse(false, $publicMessage, $failureUrl);
		}
	}

	/**
	 * Verifies the RS256 signature and claims of a Firebase ID Token using Google's public JWKS
	 *
	 * @param   string  $jwtString  The raw ID token string
	 *
	 * @return  array  Decoded claims array
	 * @throws  Exception
	 */
	private function verifyFirebaseToken(string $jwtString): array
	{
		$config = JWTConfig::forUnsecuredSigner();
		$token  = $config->parser()->parse($jwtString);

		// Pin algorithm to RS256
		if ($token->headers()->get('alg') !== 'RS256')
		{
			throw new RuntimeException('Invalid token algorithm.');
		}

		// Fetch and cache Google's public signing keys
		$jwkArray = $this->getFirebaseSigningKeys();

		if (!$this->validateJWTSignature($token, $jwkArray))
		{
			throw new RuntimeException('Firebase token signature verification failed.');
		}

		// Validate standard Firebase claims
		$clock     = SystemClock::fromSystemTimezone();
		$skew      = new DateInterval('PT60S');
		$validator = $config->validator();

		$constraints = [
			new IssuedBy('https://securetoken.google.com/' . $this->projectId),
			new PermittedFor($this->projectId),
			new LooseValidAt($clock, $skew),
		];

		foreach ($constraints as $constraint)
		{
			if (!$validator->validate($token, $constraint))
			{
				throw new RuntimeException('Firebase token validation failed: ' . get_class($constraint));
			}
		}

		$claims = $token->claims();
		$sub    = (string) $claims->get('sub', '');

		if (empty($sub))
		{
			throw new RuntimeException('Missing token subject (UID).');
		}

		$email = (string) $claims->get('email', '');
		$name  = (string) $claims->get('name', '');

		if (empty($name) && !empty($email))
		{
			$name = explode('@', $email)[0];
		}

		$firebaseClaim  = $claims->get('firebase', []);
		$signInProvider = is_array($firebaseClaim) ? ($firebaseClaim['sign_in_provider'] ?? '') : '';
		$isVerified     = (bool) $claims->get('email_verified', false);

		if ($this->canBypassValidation || $signInProvider === 'password' || $isVerified)
		{
			$isVerified = true;
		}

		return [
			'id'       => $sub,
			'email'    => $email,
			'name'     => $name,
			'picture'  => (string) $claims->get('picture', ''),
			'verified' => $isVerified,
		];
	}

	/**
	 * Validates the signature of a Firebase JWT using Google's JWKS
	 *
	 * @param   Token  $token     The parsed JWT token
	 * @param   array  $jwkArray  Array of JWKs
	 *
	 * @return  bool
	 * @throws  Base64DecodeException
	 * @throws  JWKConverterException
	 */
	private function validateJWTSignature(Token $token, array $jwkArray): bool
	{
		if (empty($jwkArray))
		{
			return false;
		}

		$signer       = new Signer\Rsa\Sha256();
		$keyID        = $token->headers()->get('kid');
		$jwkConverter = new JWKConverter();
		$config       = JWTConfig::forUnsecuredSigner();

		foreach ($jwkArray as $jwk)
		{
			if (($jwk['kid'] ?? null) !== $keyID)
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
	 * Retrieves and caches Google's Firebase JSON Web Key Set (JWKS)
	 *
	 * @return  array
	 * @throws  RuntimeException
	 */
	private function getFirebaseSigningKeys(): array
	{
		$callbackController = $this->getJWKSCacheController();

		if (!$callbackController instanceof CallbackController)
		{
			return $this->fetchFirebaseSigningKeys();
		}

		return $callbackController->get(
			fn() => $this->fetchFirebaseSigningKeys(), []
		);
	}

	/**
	 * Fetches the active JWKS from Google's securetoken public endpoint
	 *
	 * @return  array
	 * @throws  RuntimeException
	 */
	private function fetchFirebaseSigningKeys(): array
	{
		$url  = 'https://www.googleapis.com/service_accounts/v1/jwk/securetoken@system.gserviceaccount.com';
		$http = (new HttpFactory())->getHttp();

		try
		{
			$response = $http->get($url);
		}
		catch (Exception $e)
		{
			throw new RuntimeException(sprintf('Failed to connect to Google JWKS: %s', $e->getMessage()), 500, $e);
		}

		if ($response->getStatusCode() !== 200)
		{
			throw new RuntimeException(sprintf('Google JWKS endpoint returned HTTP %d', $response->getStatusCode()), 500);
		}

		try
		{
			$data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
		}
		catch (JsonException $e)
		{
			throw new RuntimeException(sprintf('Invalid JSON received from Google JWKS: %s', $e->getMessage()), 500, $e);
		}

		if (!isset($data['keys']) || !is_array($data['keys']) || empty($data['keys']))
		{
			throw new RuntimeException('Google JWKS did not contain any signing keys', 500);
		}

		return $data['keys'];
	}

	/**
	 * Gets the cache controller for the Firebase JWKS
	 *
	 * @return  CallbackController|null
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
			'defaultgroup' => 'plg_sociallogin_firebase',
			'cachebase'    => $application->get('cache_path', JPATH_CACHE),
			'lifetime'     => 86400,
			'storage'      => $application->get('cache_handler', 'file'),
			'checkTime'    => true,
			'caching'      => true,
		];

		/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
		$this->jwksCacheController = $this->getCacheControllerFactory()
			->createCacheController('callback', $options);

		return $this->jwksCacheController;
	}

	/** @inheritDoc */
	protected function getSocialNetworkProfileInformation(object $connector): ?array
	{
		return null;
	}

	/** @inheritDoc */
	protected function mapSocialProfileToUserData(array $socialProfile): UserData
	{
		$userData           = new UserData();
		$userData->name     = $socialProfile['name'] ?? '';
		$userData->id       = $socialProfile['id'] ?? '';
		$userData->email    = $socialProfile['email'] ?? '';
		$userData->verified = !empty($socialProfile['verified']);

		return $userData;
	}

	/** @inheritDoc */
	protected function getPictureUrl(array $socialProfile): ?string
	{
		return $socialProfile['picture'] ?? null;
	}

	/** @inheritDoc */
	protected function isProperlySetUp(): bool
	{
		return !empty($this->projectId) && !empty($this->apiKey);
	}

	/**
	 * Resolves the destination URL from a return parameter or fallback URL
	 *
	 * @param   string  $rawReturn
	 * @param   string  $fallback
	 *
	 * @return  string
	 */
	private function resolveRedirectUrl(string $rawReturn, string $fallback): string
	{
		if (!empty($rawReturn))
		{
			$decoded = base64_decode($rawReturn, true);
			if ($decoded && (str_starts_with($decoded, 'index.php') || str_starts_with($decoded, '/') || filter_var($decoded, FILTER_VALIDATE_URL)))
			{
				$rawReturn = $decoded;
			}

			if (!filter_var($rawReturn, FILTER_VALIDATE_URL))
			{
				$fallback = Route::_($rawReturn, false);
			}
			else
			{
				$fallback = $rawReturn;
			}
		}

		if (!str_starts_with($fallback, 'http://') && !str_starts_with($fallback, 'https://'))
		{
			$fallback = rtrim(Uri::base(), '/') . '/' . ltrim($fallback, '/');
		}

		return $fallback;
	}

	/**
	 * Sends a JSON AJAX response back to the client
	 *
	 * @param   bool    $success
	 * @param   string  $message
	 * @param   string  $redirect
	 * @param   array   $extra
	 *
	 * @return  void
	 */
	private function sendAjaxResponse(bool $success, string $message = '', string $redirect = '', array $extra = []): void
	{
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(array_merge([
			'success'  => $success,
			'message'  => $message,
			'redirect' => $redirect,
		], $extra));
		$this->getApplication()->close($success ? 200 : 400);
	}
}
