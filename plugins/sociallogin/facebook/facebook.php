<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

use Akeeba\SocialLogin\Facebook\OAuth as FacebookOAuth;
use Akeeba\SocialLogin\Facebook\User as FacebookUser;
use Akeeba\SocialLogin\Library\Data\UserData;
use Akeeba\SocialLogin\Library\Helper\Joomla;
use Akeeba\SocialLogin\Library\Plugin\AbstractPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

if (!class_exists('Akeeba\\SocialLogin\\Library\\Plugin\\AbstractPlugin', true))
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
	 * @param   array   $config    An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 */
	public function __construct($subject, array $config = array())
	{
		parent::__construct($subject, $config);

		// Register the autoloader
		JLoader::registerNamespace('Akeeba\\SocialLogin\\Facebook', __DIR__ . '/Facebook', false, false, 'psr4');

		// Per-plugin customization
		$this->buttonImage = 'plg_sociallogin_facebook/fb_white_29.png';
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
			$options = array(
				'clientid'     => $this->appId,
				'clientsecret' => $this->appSecret,
				'redirecturi'  => Uri::base() . 'index.php?option=com_ajax&group=sociallogin&plugin=' . $this->integrationName . '&format=raw',
			);
			$httpClient      = Joomla::getHttpClient();
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

		return (array)$fbUserFields;
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
		$userData           = new UserData();
		$userData->name     = isset($socialProfile['name']) ? $socialProfile['name'] : '';
		$userData->id       = isset($socialProfile['id']) ? $socialProfile['id'] : '';
		$userData->email    = isset($socialProfile['email']) ? $socialProfile['email'] : '';
		// DO NOT USE empty() SINCE $userData->email IS A MAGIC PROPERTY (fetched through __get).
		$userData->verified = $userData->email != '';

		return $userData;
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
}
