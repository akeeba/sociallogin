<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') || die();

use Akeeba\SocialLogin\Library\Data\UserData;
use Akeeba\SocialLogin\Library\Helper\Joomla;
use Akeeba\SocialLogin\Library\Plugin\AbstractPlugin;
use Akeeba\SocialLogin\Microsoft\OAuth as MicrosoftOAuth;
use Akeeba\SocialLogin\Microsoft\UserGraphQuery;
use Akeeba\SocialLogin\Microsoft\UserQuery;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

if (!class_exists('Akeeba\\SocialLogin\\Library\\Plugin\\AbstractPlugin', true))
{
	return;
}

/**
 * Akeeba Social Login plugin for Microsoft Live integration
 */
class plgSocialloginMicrosoft extends AbstractPlugin
{
	/**
	 * Is this an Azure AD application? False for Live SDK.
	 *
	 * @var bool
	 */
	protected $isAzure = false;

	/**
	 * Constructor. Loads the language files as well.
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array    $config   An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 */
	public function __construct($subject, array $config = [])
	{
		parent::__construct($subject, $config);

		// Register the autoloader
		if (version_compare(JVERSION, '3.99999.99999', 'le'))
		{
			JLoader::registerNamespace('Akeeba\\SocialLogin\\Microsoft', __DIR__ . '/Microsoft', false, false, 'psr4');
		}
		else
		{
			JLoader::registerNamespace('Akeeba\\SocialLogin\\Microsoft', __DIR__ . '/Microsoft');
		}

		// Per-plugin customization
		$this->buttonImage = 'plg_sociallogin_microsoft/microsoft_mark.png';

		// Customization for Microsoft Azure AD vs Live SDK applications
		$this->isAzure   = $this->params->get('apptype', 'live') === 'azure';
		$this->appId     = $this->params->get($this->isAzure ? 'azappid' : 'appid', '');
		$this->appSecret = $this->params->get($this->isAzure ? 'azappsecret' : 'appsecret', '');
	}

	/**
	 * Processes the authentication callback from Microsoft.
	 *
	 * Note: this method is called from Joomla's com_ajax, not com_sociallogin itself
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 */
	public function onAjaxMicrosoft()
	{
		$this->onSocialLoginAjax();
	}

	/**
	 * Returns a MicrosoftOAuth object
	 *
	 * @return  MicrosoftOAuth
	 *
	 * @throws  Exception
	 */
	protected function getConnector()
	{
		if (is_null($this->connector))
		{
			$appType = $this->params->get('apptype', 'live');

			$options = [
				'clientid'     => $this->appId,
				'clientsecret' => $this->appSecret,
				'redirecturi'  => Uri::base() . 'index.php?option=com_ajax&group=sociallogin&plugin=' . $this->integrationName . '&format=raw',
				'scope'        => 'wl.basic wl.emails wl.signin',
			];


			if ($appType === 'azure')
			{
				$options = [
					'clientid'      => $this->appId,
					'clientsecret'  => $this->appSecret,
					'redirecturi'   => Uri::base() . 'index.php/aksociallogin_finishLogin/microsoft.raw',
					'authurl'       => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
					'tokenurl'      => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
					'scope'         => 'user.read',
					'grant_scope'   => 'user.read',
					'requestparams' => [
						'response_mode' => 'query',
					],
				];
			}

			$httpClient      = Joomla::getHttpClient();
			$this->connector = new MicrosoftOAuth($options, $httpClient, $this->app->input, $this->app);
		}

		return $this->connector;
	}

	/**
	 * Get the raw user profile information from the social network.
	 *
	 * @param   MicrosoftOAuth  $connector  The internal connector object.
	 *
	 * @return  array
	 *
	 * @throws  Exception
	 */
	protected function getSocialNetworkProfileInformation($connector)
	{
		$tokenArray = $connector->getToken();

		$options = new Registry([
			'userAgent' => 'Akeeba-Social-Login',
		]);
		$client  = HttpFactory::getHttp($options);

		$className    = $this->isAzure ? UserGraphQuery::class : UserQuery::class;
		$msUserQuery  = new $className($client, $tokenArray['access_token']);
		$msUserFields = $msUserQuery->getUserInformation();

		return json_decode(json_encode($msUserFields), true);
	}

	/**
	 * Maps the raw social network profile fields retrieved with getSocialNetworkProfileInformation() into a UserData
	 * object we use in the Social Login library.
	 *
	 * @param   array  $socialProfile  The raw social profile fields
	 *
	 * @return  UserData
	 */
	protected function mapSocialProfileToUserData(array $socialProfile)
	{
		$nameParts = [];

		if (!$this->isAzure)
		{
			if (isset($socialProfile['first_name']))
			{
				$nameParts[] = $socialProfile['first_name'];
			}

			if (isset($socialProfile['last_name']))
			{
				$nameParts[] = $socialProfile['last_name'];
			}

			$name = implode(' ', $nameParts);

			$email = isset($socialProfile['emails']) && isset($socialProfile['emails']['account']) ? $socialProfile['emails']['account'] : '';
		}
		else
		{
			if (isset($socialProfile['givenName']))
			{
				$nameParts[] = $socialProfile['givenName'];
			}

			if (isset($socialProfile['surname']))
			{
				$nameParts[] = $socialProfile['surname'];
			}

			$name = implode(' ', $nameParts);

			if (isset($socialProfile['displayName']))
			{
				$name = $socialProfile['displayName'];
			}

			$email = $socialProfile['mail'] ?? '';

			if (empty($email) && !empty($socialProfile['userPrincipalName'] ?? ''))
			{
				$email = is_string($socialProfile['userPrincipalName']) ? $socialProfile['userPrincipalName'] : '';
			}
		}

		$userData           = new UserData();
		$userData->id       = $socialProfile['id'];
		$userData->name     = $name;
		$userData->email    = $email;
		$userData->verified = true;

		return $userData;
	}
}
