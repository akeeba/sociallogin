<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
namespace Joomla\Plugin\Sociallogin\Google\Extension;

defined('_JEXEC') || die();

use Exception;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;
use Joomla\Plugin\Sociallogin\Google\Integration\OAuth2;
use Joomla\Plugin\Sociallogin\Google\Integration\OpenID;
use Joomla\Plugin\System\SocialLogin\Library\Data\UserData;
use Joomla\Plugin\System\SocialLogin\Library\OAuth\OAuth2Client;
use Joomla\Plugin\System\SocialLogin\Library\Plugin\AbstractPlugin;
use Joomla\Registry\Registry;

if (!class_exists(AbstractPlugin::class, true))
{
	return;
}

/**
 * Akeeba Social Login plugin for Google integration
 */
class Plugin extends AbstractPlugin
{
	/**
	 * The OAuth2 client object used by the Google OAuth connector
	 *
	 * @var   OAuth2Client
	 */
	private $oAuth2Client;

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
		$this->fgColor = '#FFFFFF';
		$this->bgColor = '#4285F4';

		parent::__construct($subject, $config);

		// Per-plugin customization
		$this->buttonImage = 'plg_sociallogin_google/google.svg';
	}

	/**
	 * Returns a OAuth2 object
	 *
	 * @return  OAuth2
	 *
	 * @throws  Exception
	 */
	protected function getConnector()
	{
		if (is_null($this->connector))
		{
			$options = [
				'authurl'       => 'https://accounts.google.com/o/oauth2/auth',
				'tokenurl'      => 'https://accounts.google.com/o/oauth2/token',
				'clientid'      => $this->appId,
				'clientsecret'  => $this->appSecret,
				'redirecturi'   => Uri::base() . 'index.php?option=com_ajax&group=sociallogin&plugin=' . $this->integrationName . '&format=raw',
				/**
				 * Authorization scopes, space separated.
				 *
				 * @see https://developers.google.com/+/web/api/rest/oauth#authorization-scopes
				 */
				'scope'         => 'profile email',
				'requestparams' => [
					'access_type'            => 'online',
					'include_granted_scopes' => 'true',
					'prompt'                 => 'select_account',
				],
			];

			$httpClient         = HttpFactory::getHttp();
			$this->oAuth2Client = new OAuth2Client($options, $httpClient, $this->app->input, $this->app);
			$this->connector    = new OAuth2($options, $this->oAuth2Client);
		}

		return $this->connector;
	}

	/**
	 * Returns the OAuth2Client we use to authenticate to Google
	 *
	 * @return  OAuth2Client
	 *
	 * @throws Exception
	 */
	private function getClient()
	{
		if (is_null($this->oAuth2Client))
		{
			$this->getConnector();
		}

		return $this->oAuth2Client;
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
		return $this->getClient()->createUrl();
	}

	/**
	 * Get the OAuth / OAuth2 token from the social network. Used in the onAjax* handler.
	 *
	 * @return  array{0:string,1:OAuth2}  [$token, $connector]
	 *
	 * @throws  Exception
	 */
	protected function getToken()
	{
		$connector = $this->getConnector();

		/**
		 * I have to do this because Joomla's Google OAuth2 connector is buggy :@ The googlize() method assumes that
		 * the requestparams option is an array. However, when you construct the object Joomla! will "helpfully" convert
		 * your original array into an object. Therefore trying to later access it as an array causes a PHP Fatal Error
		 * about trying to access an stdClass object as an array...!
		 */
		$connector->setOption('requestparams', [
			'access_type'            => 'online',
			'include_granted_scopes' => 'true',
			'prompt'                 => 'select_account',
		]);

		return [$connector->authenticate(), $connector];
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
		/** @var OAuth2 $connector */
		$options       = new Registry();
		$googleUserApi = new OpenID($options, $connector);
		$openIDProfile = $googleUserApi->getOpenIDProfile();

		return $openIDProfile;
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
		$userData           = new UserData();
		$userData->name     = $socialProfile['name'] ?? '';
		$userData->id       = $socialProfile['sub'];
		$userData->email    = $socialProfile['email'] ?? '';
		$userData->verified = $socialProfile['email_verified'] ?? false;
		$userData->timezone = $socialProfile['zoneinfo'] ?? 'GMT';

		return $userData;
	}

	/**
	 * Processes the authentication callback from Google.
	 *
	 * Note: this method is called from Joomla's com_ajax, not com_sociallogin itself
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 */
	public function onAjaxGoogle()
	{
		$this->onSocialLoginAjax();
	}

}
