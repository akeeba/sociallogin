<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') || die();

use Akeeba\SocialLogin\Facebook\OAuth as FacebookOAuth;
use Akeeba\SocialLogin\Facebook\User as FacebookUser;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;
use Joomla\Plugin\System\SocialLogin\Library\Data\UserData;
use Joomla\Plugin\System\SocialLogin\Library\Plugin\AbstractPlugin;
use Joomla\Registry\Registry;

if (!class_exists(AbstractPlugin::class, true))
{
	return;
}

/**
 * Akeeba Social Login plugin for Facebook integration
 */
class plgSocialloginFacebook extends AbstractPlugin
{
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
		JLoader::registerNamespace('Akeeba\\SocialLogin\\Facebook', __DIR__ . '/Facebook');

		// Per-plugin customization
		$this->buttonImage = 'plg_sociallogin_facebook/facebook_logo.svg';
	}

	/**
	 * Processes the authentication callback from Facebook.
	 *
	 * Note: this method is called from Joomla's com_ajax, not com_sociallogin itself
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 */
	public function onAjaxFacebook()
	{
		$this->onSocialLoginAjax();
	}

	/**
	 * Returns a FacebookOAuth object
	 *
	 * @return  FacebookOAuth
	 *
	 * @throws  Exception
	 */
	protected function getConnector()
	{
		if (is_null($this->connector))
		{
			$options         = [
				'clientid'     => $this->appId,
				'clientsecret' => $this->appSecret,
				'redirecturi'  => Uri::base() . 'index.php?option=com_ajax&group=sociallogin&plugin=' . $this->integrationName . '&format=raw',
			];
			$httpClient      = HttpFactory::getHttp();
			$this->connector = new FacebookOAuth($options, $httpClient, $this->app->input, $this->app);
			$this->connector->setScope('public_profile,email');
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
		$options = new Registry();

		$options->set('api.url', 'https://graph.facebook.com/v2.7/');

		$fbUserApi    = new FacebookUser($options, null, $connector);
		$fbUserFields = $fbUserApi->getUser('me?fields=id,name,email');

		return (array) $fbUserFields;
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
		$userData        = new UserData();
		$userData->name  = $socialProfile['name'] ?? '';
		$userData->id    = $socialProfile['id'] ?? '';
		$userData->email = $socialProfile['email'] ?? '';
		// DO NOT USE empty() SINCE $userData->email IS A MAGIC PROPERTY (fetched through __get).
		$userData->verified = $userData->email != '';

		return $userData;
	}
}
