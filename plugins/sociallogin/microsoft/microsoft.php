<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

use Akeeba\SocialLogin\Library\Data\UserData;
use Akeeba\SocialLogin\Library\Helper\Joomla;
use Akeeba\SocialLogin\Library\Plugin\AbstractPlugin;
use Akeeba\SocialLogin\Microsoft\OAuth as MicrosoftOAuth;
use Akeeba\SocialLogin\Microsoft\UserQuery;
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
	 * Constructor. Loads the language files as well.
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 */
	public function __construct($subject, array $config = array())
	{
		parent::__construct($subject, $config);

		// Register the autoloader
		JLoader::registerNamespace('Akeeba\\SocialLogin\\Microsoft', __DIR__ . '/Microsoft', false, false, 'psr4');

		// Per-plugin customization
		$this->buttonImage = 'plg_sociallogin_microsoft/microsoft_mark.png';
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
			$options = array(
				'clientid'     => $this->appId,
				'clientsecret' => $this->appSecret,
				'redirecturi'  => JUri::base() . 'index.php?option=com_ajax&group=sociallogin&plugin=' . $this->integrationName . '&format=raw',
			);
			$httpClient      = Joomla::getHttpClient();
			$this->connector = new MicrosoftOAuth($options, $httpClient, $this->app->input, $this->app);
			$this->connector->setScope('wl.basic wl.emails wl.signin');
		}

		return $this->connector;
	}

	/**
	 * Get the raw user profile information from the social network.
	 *
	 * @param   object  $connector  The internal connector object.
	 *
	 * @return  array
	 *
	 * @throws  Exception
	 */
	protected function getSocialNetworkProfileInformation($connector)
	{
		$tokenArray   = $connector->getToken();

		$options      = new Registry(array(
			'userAgent' => 'Akeeba-Social-Login',
		));
		$client       = \Joomla\CMS\Http\HttpFactory::getHttp($options);
		$msUserQuery  = new UserQuery($client, $tokenArray['access_token']);
		$msUserFields = $msUserQuery->getUserInformation();

		return json_decode(json_encode($msUserFields), true);
	}

	/**
	 * Maps the raw social network profile fields retrieved with getSocialNetworkProfileInformation() into a UserData
	 * object we use in the Social Login library.
	 *
	 * @param   array $socialProfile The raw social profile fields
	 *
	 * @return  UserData
	 */
	protected function mapSocialProfileToUserData(array $socialProfile)
	{
		$nameParts = [];

		if (isset($socialProfile['first_name']))
		{
			$nameParts[] = $socialProfile['first_name'];
		}

		if (isset($socialProfile['last_name']))
		{
			$nameParts[] = $socialProfile['last_name'];
		}

		$userData           = new UserData();
		$userData->name     = implode(' ', $nameParts);
		$userData->id       = $socialProfile['id'];
		$userData->email    = isset($socialProfile['emails']) && isset($socialProfile['emails']['account']) ? $socialProfile['emails']['account'] : '';
		$userData->verified = true;

		return $userData;
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
}
