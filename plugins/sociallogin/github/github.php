<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

use Akeeba\SocialLogin\GitHub\OAuth as GitHubOAuth;
use Akeeba\SocialLogin\GitHub\UserQuery;
use Akeeba\SocialLogin\Library\Data\UserData;
use Akeeba\SocialLogin\Library\Helper\Joomla;
use Akeeba\SocialLogin\Library\Plugin\AbstractPlugin;
use Joomla\Registry\Registry;

if (!class_exists('Akeeba\\SocialLogin\\Library\\Plugin\\AbstractPlugin', true))
{
	return;
}

/**
 * Akeeba Social Login plugin for GitHub integration
 */
class plgSocialloginGithub extends AbstractPlugin
{
	/**
	 * GitHub GitHubOAuth connector object
	 *
	 * @var   GitHubOAuth
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
		JLoader::registerNamespace('Akeeba\\SocialLogin\\GitHub', __DIR__ . '/GitHub', false, false, 'psr4');

		// Per-plugin customization
		$this->buttonImage = 'plg_sociallogin_github/gh_white_32.png';
		$this->customCSS = /** @lang CSS */
			<<< CSS
.akeeba-sociallogin-link-button-github, .akeeba-sociallogin-unlink-button-github, .akeeba-sociallogin-button-github { background-color: #000000; color: #ffffff; transition-duration: 0.33s; background-image: none; border-color: #333333; }
.akeeba-sociallogin-link-button-github:hover, .akeeba-sociallogin-unlink-button-github:hover, .akeeba-sociallogin-button-github:hover { background-color: #333333; color: #ffffff; transition-duration: 0.33s; border-color: #999999; }
.akeeba-sociallogin-link-button-github img, .akeeba-sociallogin-unlink-button-github img, .akeeba-sociallogin-button-github img { display: inline-block; width: 16px; height: 16px; margin: 0 0.33em 0.1em 0; padding: 0 }

CSS;
	}

	/**
	 * Returns a GitHubOAuth object
	 *
	 * @return  GitHubOAuth
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
				'redirecturi'  => JUri::base() . 'index.php?option=com_ajax&group=sociallogin&plugin=' . $this->integrationName . '&format=raw',
			);
			$app             = Joomla::getApplication();
			$httpClient      = Joomla::getHttpClient();
			$this->connector = new GitHubOAuth($options, $httpClient, $app->input, $app);
			$this->connector->setScope('user');
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
		$connector = $this->getConnector();

		return $connector->createUrl();
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
		$tokenArray   = $connector->getToken();

		$options      = new Registry(array(
			'userAgent' => 'Akeeba-Social-Login',
		));
		$client       = \Joomla\CMS\Http\HttpFactory::getHttp($options);
		$ghUserQuery  = new UserQuery($client, $tokenArray['access_token']);
		$ghUserFields = $ghUserQuery->getUserInformation();

		return (array)$ghUserFields;
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
		$userData->verified = true;

		return $userData;
	}

	/**
	 * Processes the authentication callback from GitHub.
	 *
	 * Note: this method is called from Joomla's com_ajax, not com_sociallogin itself
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 */
	public function onAjaxGithub()
	{
		$this->onSocialLoginAjax();
	}
}
