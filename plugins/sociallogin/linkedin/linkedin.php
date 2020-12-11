<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') || die();

use Akeeba\SocialLogin\Library\Data\UserData;
use Akeeba\SocialLogin\Library\Helper\Joomla;
use Akeeba\SocialLogin\Library\Plugin\AbstractPlugin;
use Akeeba\SocialLogin\LinkedIn\OAuth as LinkedInOAuth;
use Akeeba\SocialLogin\LinkedIn\UserQuery;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

if (!class_exists('Akeeba\\SocialLogin\\Library\\Plugin\\AbstractPlugin', true))
{
	return;
}

/**
 * Akeeba Social Login plugin for LinkedIn integration
 */
class plgSocialloginLinkedin extends AbstractPlugin
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
		JLoader::registerNamespace('Akeeba\\SocialLogin\\LinkedIn', __DIR__ . '/LinkedIn', false, false, 'psr4');

		// Per-plugin customization
		$this->buttonImage = 'plg_sociallogin_linkedin/linkedin_34.png';
		$this->customCSS = /** @lang CSS */
			<<< CSS
.akeeba-sociallogin-link-button-linkedin, .akeeba-sociallogin-unlink-button-linkedin, .akeeba-sociallogin-button-linkedin { background-color: #ffffff; color: #000000; transition-duration: 0.33s; background-image: none; border-color: #86898C
; }
.akeeba-sociallogin-link-button-linkedin:hover, .akeeba-sociallogin-unlink-button-linkedin:hover, .akeeba-sociallogin-button-linkedin:hover { background-color: #CFEDFB
 ; color: #000000; transition-duration: 0.33s; border-color: #00A0DC
 ; }
.akeeba-sociallogin-link-button-linkedin img, .akeeba-sociallogin-unlink-button-linkedin img, .akeeba-sociallogin-button-linkedin img { display: inline-block; width: 22px; height: 16px; margin: 0 0.33em 0.1em 0; padding: 0 }

CSS;
	}

	/**
	 * Returns a LinkedInOAuth object
	 *
	 * @return  LinkedInOAuth
	 *
	 * @throws  Exception
	 *
	 * @see  https://docs.microsoft.com/en-us/linkedin/shared/authentication/authorization-code-flow?context=linkedin/consumer/context
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
			$this->connector = new LinkedInOAuth($options, $httpClient, $this->app->input, $this->app);
			$this->connector->setScope('r_liteprofile r_emailaddress');
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
	 *
	 * @see  https://docs.microsoft.com/en-us/linkedin/consumer/integrations/self-serve/sign-in-with-linkedin
	 */
	protected function getSocialNetworkProfileInformation($connector)
	{
		$tokenArray = $connector->getToken();

		$options      = new Registry([
			'userAgent' => 'Akeeba-Social-Login',
		]);
		$client       = \Joomla\CMS\Http\HttpFactory::getHttp($options);
		$liUserQuery  = new UserQuery($client, $tokenArray['access_token']);
		$liUserFields = $liUserQuery->getUserInformation();
		$emailFields  = $liUserQuery->getEmailAddress();

		return array_merge($liUserFields, $emailFields);
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

		foreach (['localizedFirstName', 'localizedLastName'] as $fieldName)
		{
			if (!isset($socialProfile[$fieldName]))
			{
				continue;
			}

			$nameParts[] = $socialProfile[$fieldName];
		}

		$userData           = new UserData();
		$userData->name     = implode(' ', $nameParts);
		$userData->name     = empty($userData->name) ? 'John Doe' : $userData->name;
		$userData->id       = $socialProfile['id'];
		$userData->verified = false;
		$userData->email    = 'invalid@example.com';

		if (isset($socialProfile['elements']) &&
			isset($socialProfile['elements'][0]) &&
			isset($socialProfile['elements'][0]['handle~']) &&
			isset($socialProfile['elements'][0]['handle~']['emailAddress']))
		{
			$userData->email    = $socialProfile['elements'][0]['handle~']['emailAddress'];
			$userData->verified = true;
		}

		return $userData;
	}

	/**
	 * Processes the authentication callback from LinkedIn.
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 */
	public function onAjaxLinkedin()
	{
		$this->onSocialLoginAjax();
	}
}
