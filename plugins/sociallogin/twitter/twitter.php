<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

use Akeeba\SocialLogin\Library\Data\UserData;
use Akeeba\SocialLogin\Library\Exception\Login\LoginError;
use Akeeba\SocialLogin\Library\Helper\Joomla;
use Akeeba\SocialLogin\Library\Plugin\AbstractPlugin;
use Akeeba\SocialLogin\Twitter\OAuth;
use Joomla\CMS\Uri\Uri;

if (!class_exists('Akeeba\\SocialLogin\\Library\\Plugin\\AbstractPlugin', true))
{
	return;
}

/**
 * Akeeba Social Login plugin for Twitter integration
 */
class plgSocialloginTwitter extends AbstractPlugin
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
		JLoader::registerNamespace('Akeeba\\SocialLogin\\Twitter', __DIR__ . '/Twitter', false, false, 'psr4');

		// Per-plugin customization
		$this->buttonImage = 'plg_sociallogin_twitter/twitter.png';
	}

	/**
	 * Returns an OAuth object
	 *
	 * @return  OAuth
	 *
	 * @throws Exception
	 */
	protected function getConnector()
	{
		if (is_null($this->connector))
		{
			$options = array(
				'callback'        => JUri::base() . 'index.php?option=com_ajax&group=sociallogin&plugin=' . $this->integrationName . '&format=raw',
				'consumer_key'    => $this->appId,
				'consumer_secret' => $this->appSecret,
				'sendheaders'     => true,
			);

			$httpClient      = Joomla::getHttpClient();
			$this->connector = new OAuth($options, $httpClient, $this->app->input, $this->app);
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
		/**
		 * Authentication has to go through a special com_ajax URL since the OAuth1 client needs to performs a Twitter
		 * server query and then a redirection to authenticate the user.
		 */
		$token = Joomla::getToken();

		return Uri::base() . 'index.php?option=com_ajax&group=system&plugin=sociallogin&format=raw&akaction=authenticate&encoding=redirect&slug=' . $this->integrationName . '&' . $token . '=1';
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
		/** @var OAuth $connector */
		$token = $connector->getToken();

		$parameters    = [
			'oauth_token' => $token['key'],
		];
		$data          = [
			'skip_status'   => 'true',
			'include_email' => 'true',
		];
		$path          = 'https://api.twitter.com/1.1/account/verify_credentials.json';
		$response      = $connector->oauthRequest($path, 'GET', $parameters, $data);
		$twitterFields = json_decode($response->body, true);

		if ($response->code != 200)
		{
			throw new LoginError(Joomla::_('PLG_SOCIALLOGIN_TWITTER_ERROR_NOT_LOGGED_IN_TWITTER'));
		}

		return $twitterFields;
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
		$userData->name     = $socialProfile['name'];
		$userData->id       = $socialProfile['id'];
		$userData->timezone = $socialProfile['utc_offset'] / 3600;
		$userData->email    = '';
		$userData->verified = false;

		if (isset($socialProfile['email']) && !empty($socialProfile['email']))
		{
			$userData->email    = $socialProfile['email'];
			$userData->verified = true;
		}

		return $userData;
	}

	/**
	 * Initiate the user authentication (steps 1 & 2 per the Twitter documentation). Step 3 in the documentation is
	 * Twitter calling back our site, i.e. the call to the onAjaxTwitter method.
	 *
	 * @param   string $slug The slug of the integration method being called.
	 *
	 * @see https://dev.twitter.com/web/sign-in/implementing
	 *
	 * @throws Exception
	 */
	public function onSocialLoginAuthenticate($slug)
	{
		// Make sure we are properly set up
		if (!$this->isProperlySetUp())
		{
			return;
		}

		// Make sure it's our integration
		if ($slug != $this->integrationName)
		{
			return;
		}

		// Perform the user redirection
		$this->getConnector()->authenticate();
	}

	/**
	 * Processes the authentication callback from Twitter.
	 *
	 * Note: this method is called from Joomla's com_ajax, not com_sociallogin itself
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 */
	public function onAjaxTwitter()
	{
		$this->onSocialLoginAjax();
	}

}
