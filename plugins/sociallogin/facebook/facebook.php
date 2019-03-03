<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
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
	 * Facebook App ID
	 *
	 * @var   string
	 */
	private $appId = '';

	/**
	 * Facebook App Secret
	 *
	 * @var   string
	 */
	private $appSecret = '';

	/**
	 * Facebook FacebookOAuth connector object
	 *
	 * @var   FacebookOAuth
	 */
	private $connector;

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
		$this->customCSS = /** @lang CSS */
			<<< CSS
.akeeba-sociallogin-link-button-facebook, .akeeba-sociallogin-unlink-button-facebook, .akeeba-sociallogin-button-facebook { background-color: #3B5998; color: #ffffff; transition-duration: 0.33s; background-image: none; border-color: #23355b; }
.akeeba-sociallogin-link-button-facebook:hover, .akeeba-sociallogin-unlink-button-facebook:hover, .akeeba-sociallogin-button-facebook:hover { background-color: #8B9DC3; color: #ffffff; transition-duration: 0.33s; border-color: #3B5998; }
.akeeba-sociallogin-link-button-facebook img, .akeeba-sociallogin-unlink-button-facebook img, .akeeba-sociallogin-button-facebook img { display: inline-block; width: 16px; height: 16px; margin: 0 0.33em 0.1em 0; padding: 0 }

CSS;

		// Load the plugin options into properties
		$this->appId               = $this->params->get('appid', '');
		$this->appSecret           = $this->params->get('appsecret', '');
	}

	/**
	 * Is this integration properly set up and ready for use?
	 *
	 * @return  bool
	 */
	protected function isProperlySetUp()
	{
		return !(empty($this->appId) || empty($this->appSecret));
	}

	/**
	 * Returns a FacebookOAuth object
	 *
	 * @return  FacebookOAuth
	 *
	 * @throws  Exception
	 */
	private function getConnector()
	{
		if (is_null($this->connector))
		{
			$options = array(
				'clientid'     => $this->appId,
				'clientsecret' => $this->appSecret,
				'redirecturi'  => Uri::base() . 'index.php?option=com_ajax&group=sociallogin&plugin=' . $this->integrationName . '&format=raw',
			);
			$app             = Joomla::getApplication();
			$httpClient      = Joomla::getHttpClient();
			$this->connector = new FacebookOAuth($options, $httpClient, $app->input, $app);
			$this->connector->setScope('public_profile,email');
		}

		return $this->connector;
	}

	/**
	 * Return the URL for the login button
	 *
	 * @return  string
	 *
	 * @throws  Exception
	 */
	protected function getLoginButtonURL()
	{
		// Get a Facebook OAUth2 connector object and retrieve the URL
		$connector = $this->getConnector();

		return $connector->createUrl();
	}

	/**
	 * Return the URL for the link button
	 *
	 * @return  string
	 *
	 * @throws  Exception
	 */
	protected function getLinkButtonURL()
	{
		return $this->getLoginButtonURL();
	}

	/**
	 * Get the OAuth / OAuth2 token from the social network. Used in the onAjax* handler.
	 *
	 * @return  array|bool  False if we could not retrieve it. Otherwise [$token, $connector]
	 *
	 * @throws  Exception
	 */
	protected function getToken()
	{
		$oauthConnector = $this->getConnector();

		return [$oauthConnector->authenticate(), $oauthConnector];
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
		$fbUserFields = $fbUserApi->getUser('me?fields=id,name,email,verified,timezone');

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
		$userData->verified = isset($socialProfile['verified']) ? $socialProfile['verified'] : false;
		$userData->timezone = isset($socialProfile['timezone']) ? $socialProfile['timezone'] : 'GMT';

		return $userData;
	}

	/**
	 * Return the user's profile picture URL given the social network profile fields retrieved with
	 * getSocialNetworkProfileInformation(). Return null if no such thing is supported.
	 *
	 * @param   array $socialProfile The raw social profile fields
	 *
	 * @return  string|null
	 */
	protected function getPictureUrl(array $socialProfile)
	{
		return null;
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
