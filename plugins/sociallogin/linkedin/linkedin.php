<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

use Akeeba\SocialLogin\LinkedIn\OAuth as LinkedInOAuth;
use Akeeba\SocialLogin\LinkedIn\UserQuery;
use Akeeba\SocialLogin\Library\Data\PluginConfiguration;
use Akeeba\SocialLogin\Library\Data\UserData;
use Akeeba\SocialLogin\Library\Exception\Login\GenericMessage;
use Akeeba\SocialLogin\Library\Exception\Login\LoginError;
use Akeeba\SocialLogin\Library\Helper\Integrations;
use Akeeba\SocialLogin\Library\Helper\Joomla;
use Akeeba\SocialLogin\Library\Helper\Login;
use Joomla\CMS\User\User;
use Joomla\Registry\Registry;

if (!class_exists('AkeebaSocialLoginJPlugin'))
{
	if (!include_once (JPATH_PLUGINS . '/system/sociallogin/abstraction.php'))
	{
		return;
	}
}

if (!class_exists('Akeeba\\SocialLogin\\Library\\Helper\\Login', true))
{
	return;
}

/**
 * Akeeba Social Login plugin for LinkedIn integration
 */
class plgSocialloginLinkedin extends AkeebaSocialLoginJPlugin
{
	/**
	 * The integration slug used by this plugin
	 *
	 * @var   string
	 */
	private $integrationName = '';

	/**
	 * Should I log in users who have not yet linked their LinkedIn account to their site account? THIS MAY BE DANGEROUS
	 * (impersonation risk), therefore it is disabled by default.
	 *
	 * @var   bool
	 */
	private $canLoginUnlinked = false;

	/**
	 * Can I use this integration to create new user accounts? This will happen when someone tries to login through
	 * LinkedIn but their LinkedIn account is not linked to a user account yet.
	 *
	 * @var   bool
	 */
	private $canCreateNewUsers = false;

	/**
	 * Allow the plugin to override Joomla's new user account registration flag. This is useful to prevent new user
	 * accounts from being created _unless_ they have a LinkedIn account and use it on your site (force new users to
	 * link their social media accounts).
	 *
	 * @var   bool
	 */
	private $canCreateAlways = false;

	/**
	 * When creating new users, am I allowed to bypass email verification? Remember that LinkedIn users always have their
	 * email addresses verified. This is a precondition to allowing them to link their account to an OAuth application
	 * i.e. log into another site using their LinkedIn account. Therefore if we see that a user has granted us permission
	 * to access their profile we are sure that their email address has already been verified by LinkedIn.
	 *
	 * @var   bool
	 */
	private $canBypassValidation = true;

	/**
	 * Should I output inline custom CSS in the page header to style this plugin's login, link and unlink buttons?
	 *
	 * @var   bool
	 */
	private $useCustomCSS = true;

	/**
	 * The icon class to be used in the buttons
	 *
	 * @var   string
	 */
	private $iconClass = '';

	/**
	 * LinkedIn App ID
	 *
	 * @var   string
	 */
	private $appId = '';

	/**
	 * LinkedIn App Secret
	 *
	 * @var   string
	 */
	private $appSecret = '';

	/**
	 * LinkedIn LinkedInOAuth connector object
	 *
	 * @var   LinkedInOAuth
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

		// Load the language files
		$this->loadLanguage();

		// Register the autoloader
		JLoader::registerNamespace('Akeeba\\SocialLogin\\LinkedIn', __DIR__ . '/LinkedIn', false, false, 'psr4');

		// Set the integration name from the plugin name (without the plg_sociallogin_ part, of course)
		$this->integrationName = $this->_name;

		// Load the plugin options into properties
		$this->appId               = $this->params->get('appid', '');
		$this->appSecret           = $this->params->get('appsecret', '');
		$this->canLoginUnlinked    = $this->params->get('loginunlinked', false);
		$this->canCreateNewUsers   = $this->params->get('createnew', false);
		$this->canCreateAlways     = $this->params->get('forcenew', true);
		$this->canBypassValidation = $this->params->get('bypassvalidation', true);
		$this->useCustomCSS        = $this->params->get('customcss', true);
		$this->iconClass           = $this->params->get('icon_class', '');
	}

	/**
	 * Is this integration properly set up and ready for use?
	 *
	 * @return  bool
	 */
	private function isProperlySetUp()
	{
		return !(empty($this->appId) || empty($this->appSecret));
	}

	/**
	 * Returns a LinkedInOAuth object
	 *
	 * @return  LinkedInOAuth
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
			$this->connector = new LinkedInOAuth($options, $httpClient, $app->input, $app);
			$this->connector->setScope('r_basicprofile r_emailaddress');
		}

		return $this->connector;
	}

	/**
	 * Is the user linked to the social login account?
	 *
	 * @param   JUser|User $user The user account we are checking
	 *
	 * @return  bool
	 */
	private function isLinked($user = null)
	{
		// Make sure we are set up
		if (!$this->isProperlySetUp())
		{
			return false;
		}

		return Login::isLinkedUser($this->integrationName, $user);
	}

	/**
	 * Get the information required to render a login / link account button
	 *
	 * @param   string  $loginURL    The URL to be redirected to upon successful login / account link
	 * @param   string  $failureURL  The URL to be redirected to on error
	 *
	 * @return  array
	 *
	 * @throws  Exception
	 */
	public function onSocialLoginGetLoginButton($loginURL = null, $failureURL = null)
	{
		// Make sure we are properly set up
		if (!$this->isProperlySetUp())
		{
			return array();
		}

		// If there's no return URL use the current URL
		if (empty($loginURL))
		{
			$loginURL = JUri::getInstance()->toString(array('scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'fragment'));
		}

		// If there's no failure URL use the same as the regular return URL
		if (empty($failureURL))
		{
			$failureURL = $loginURL;
		}

		// Save the return URLs into the session
		Joomla::setSessionVar('loginUrl', $loginURL, 'plg_sociallogin_linkedin');
		Joomla::setSessionVar('failureUrl', $failureURL, 'plg_sociallogin_linkedin');

		// Get a LinkedIn OAUth2 connector object and retrieve the URL
		$connector = $this->getConnector();
		$url       = $connector->createUrl();

		// Add custom CSS
		$this->addCustomCSS();

		return array(
			// The name of the plugin rendering this button. Used for customized JLayouts.
			'slug'       => $this->integrationName,
			// The href attribute for the anchor tag.
			'link'       => $url,
			// The tooltip of the anchor tag.
			'tooltip'    => Joomla::_('PLG_SOCIALLOGIN_LINKEDIN_LOGIN_DESC'),
			// What to put inside the anchor tag. Leave empty to put the image returned by onSocialLoginGetIntegration.
			'label'      => Joomla::_('PLG_SOCIALLOGIN_LINKEDIN_LOGIN_LABEL'),
			// The image to use if there is no icon class
			'img'        => JHtml::image('plg_sociallogin_linkedin/linkedin_34.png', '', array(), true),
			// An icon class for the span before the label inside the anchor tag. Nothing is shown if this is blank.
		    'icon_class' => $this->iconClass,
		);
	}

	/**
	 * Get the information required to render a link / unlink account button
	 *
	 * @param   JUser|User   $user        The user to be linked / unlinked
	 *
	 * @return  array
	 *
	 * @throws  Exception
	 */
	public function onSocialLoginGetLinkButton($user = null)
	{
		// Make sure we are properly set up
		if (!$this->isProperlySetUp())
		{
			return array();
		}

		if (empty($user))
		{
			$user = Joomla::getUser();
		}

		// Get the return URL
		$returnURL = JUri::getInstance()->toString(array('scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'fragment'));

		// Save the return URL and user ID into the session
		Joomla::setSessionVar('returnUrl', $returnURL, 'plg_system_sociallogin');
		Joomla::setSessionVar('userID', $user->id, 'plg_system_sociallogin');

		if ($this->isLinked($user))
		{
			$token = Joomla::getToken();
			$unlinkURL = JUri::base() . 'index.php?option=com_ajax&group=system&plugin=sociallogin&format=raw&akaction=unlink&encoding=redirect&slug=' . $this->integrationName . '&' . $token . '=1';

			// Add custom CSS
			$this->addCustomCSS();

			// Render an unlink button
			return array(
				// The name of the plugin rendering this button. Used for customized JLayouts.
				'slug'       => $this->integrationName,
				// The type of the button: 'link' or 'unlink'
				'type'       => 'unlink',
				// The href attribute for the anchor tag.
				'link'       => $unlinkURL,
				// The tooltip of the anchor tag.
				'tooltip'    => Joomla::_('PLG_SOCIALLOGIN_LINKEDIN_UNLINK_DESC'),
				// What to put inside the anchor tag. Leave empty to put the image returned by onSocialLoginGetIntegration.
				'label'      => Joomla::_('PLG_SOCIALLOGIN_LINKEDIN_UNLINK_LABEL'),
				// The image to use if there is no icon class
				'img'        => JHtml::image('plg_sociallogin_linkedin/linkedin_34.png', '', array(), true),
				// An icon class for the span before the label inside the anchor tag. Nothing is shown if this is blank.
				'icon_class' => $this->iconClass,
			);
		}

		// Make sure we return to the same profile edit page
		$loginURL = JUri::getInstance()->toString(array('scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'fragment'));
		Joomla::setSessionVar('loginUrl', $loginURL, 'plg_sociallogin_linkedin');
		Joomla::setSessionVar('failureUrl', $loginURL, 'plg_sociallogin_linkedin');

		// Get a LinkedIn OAUth2 connector object and retrieve the URL
		$connector = $this->getConnector();
		$url       = $connector->createUrl();

		// Add custom CSS
		$this->addCustomCSS();

		return array(
			// The name of the plugin rendering this button. Used for customized JLayouts.
			'slug'       => $this->integrationName,
			// The type of the button: 'link' or 'unlink'
			'type'       => 'link',
			// The href attribute for the anchor tag.
			'link'       => $url,
			// The tooltip of the anchor tag.
			'tooltip'    => Joomla::_('PLG_SOCIALLOGIN_LINKEDIN_LINK_DESC'),
			// What to put inside the anchor tag. Leave empty to put the image returned by onSocialLoginGetIntegration.
			'label'      => Joomla::_('PLG_SOCIALLOGIN_LINKEDIN_LINK_LABEL'),
			// The image to use if there is no icon class
			'img'        => JHtml::image('plg_sociallogin_linkedin/linkedin_34.png', '', array(), true),
			// An icon class for the span before the label inside the anchor tag. Nothing is shown if this is blank.
			'icon_class' => $this->iconClass,
		);
	}

	/**
	 * Unlink a user account from a social login integration
	 *
	 * @param   string           $slug  The integration to unlink from
	 * @param   JUser|User|null  $user  The user to unlink, null to use the current user
	 *
	 * @return  void
	 */
	public function onSocialLoginUnlink($slug, $user = null)
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

		// Make sure we have a user
		if (is_null($user))
		{
			$user = Joomla::getUser();
		}

		Integrations::removeUserProfileData($user->id, 'sociallogin.linkedin');
	}

	/**
	 * Processes the authentication callback from LinkedIn.
	 *
	 * Note: this method is called from Joomla's com_ajax, not com_sociallogin itself
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 */
	public function onAjaxLinkedin()
	{
		// This is the return URL used by the Link button
		$returnURL  = Joomla::getSessionVar('returnUrl', JUri::base(), 'plg_system_sociallogin');
		// And this is the login success URL used by the Login button
		$loginUrl   = Joomla::getSessionVar('loginUrl', $returnURL, 'plg_sociallogin_linkedin');
		$failureUrl = Joomla::getSessionVar('failureUrl', $loginUrl, 'plg_sociallogin_linkedin');

		// Remove the return URLs from the session
		Joomla::setSessionVar('loginUrl', null, 'plg_sociallogin_linkedin');
		Joomla::setSessionVar('failureUrl', null, 'plg_sociallogin_linkedin');

		// Try to exchange the code with a token
		$oauthConnector = $this->getConnector();
		$app            = Joomla::getApplication();

		/**
		 * Handle the login callback from LinkedIn. There are three possibilities:
		 *
		 * 1. LoginError exception is thrown. We must go through Joomla's user plugins and let
		 *    them handle the login failure. They MAY change the error response. Then we report that error reponse to
		 *    the user while redirecting them to the error handler page.
		 *
		 * 2. GenericMessage exception is thrown. We must NOT go through the user
		 *    plugins, this is not a login error. Most likely we have to tell the user to validate their account.
		 *
		 * 3. No exception is thrown. Proceed to the login success page ($loginUrl).
		 */
		try
		{
			try
			{
				$token = $oauthConnector->authenticate();

				if ($token === false)
				{
					$errorMessage = Joomla::_('PLG_SOCIALLOGIN_LINKEDIN_ERROR_NOT_LOGGED_IN_FB');

					if (defined('JDEBUG') && JDEBUG)
					{
						$error = JFactory::getApplication()->input->getString('error_description', '');

						if (!empty($error))
						{
							$errorMessage .= "<br/><small>$error</small>";
						}
					}

					throw new LoginError($errorMessage);
				}

				// Get information about the user from LinkedIn.
				$tokenArray   = $oauthConnector->getToken();

				$options      = new Registry(array(
					'userAgent' => 'Akeeba-Social-Login',
				));
				$client       = \Joomla\CMS\Http\HttpFactory::getHttp($options);
				$liUserQuery  = new UserQuery($client, $tokenArray['access_token']);
				$liUserFields = $liUserQuery->getUserInformation();
			}
			catch (Exception $e)
			{
				throw new LoginError($e->getMessage());
			}

			// The data used to login or create a user
			$userData = new UserData();
			$userData->name = $liUserFields->firstName . ' ' . $liUserFields->lastName;
			$userData->id = $liUserFields->id;
			$userData->email = $liUserFields->emailAddress;
			$userData->verified = true;

			// Options which control login and user account creation
			$pluginConfiguration = new PluginConfiguration;
			$pluginConfiguration ->canLoginUnlinked = $this->canLoginUnlinked;
			$pluginConfiguration ->canCreateAlways = $this->canCreateAlways;
			$pluginConfiguration ->canCreateNewUsers = $this->canCreateNewUsers;
			$pluginConfiguration ->canBypassValidation = $this->canBypassValidation;

			/**
			 * Data to save to the user profile. The first row is the primary key which links the Joomla! user account to
			 * the social media account.
			 */
			$userProfileData = array(
				'userid'     => $userData->id,
				'token'      => json_encode($token),
				'pictureUrl' => $liUserFields->pictureUrl,
			);

			Login::handleSocialLogin($this->integrationName, $pluginConfiguration, $userData, $userProfileData);
		}
		catch (LoginError  $e)
		{
			// Log failed login
			$response                = Login::getAuthenticationResponseObject();
			$response->status        = JAuthentication::STATUS_UNKNOWN;
			$response->error_message = $e->getMessage();

			// This also enqueues the login failure message for display after redirection. Look for JLog in that method.
			Login::processLoginFailure($response);

			$app->redirect($failureUrl);

			return;
		}
		catch (GenericMessage $e)
		{
			// Do NOT go through processLoginFailure. This is NOT a login failure.
			$app->enqueueMessage($e->getMessage(), 'info');
			$app->redirect($failureUrl);

			return;
		}

		$app->redirect($loginUrl);
	}

	/**
	 * Adds custom CSS to the page's head unless we're explicitly told not to. The CSS helps render the buttons with the
	 * correct branding color.
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 */
	private function addCustomCSS()
	{
		// Make sure we only output the custom CSS once
		static $hasOutputCustomCSS = false;

		if ($hasOutputCustomCSS)
		{
			return;
		}

		$hasOutputCustomCSS = true;

		// Am I allowed to add my custom CSS?
		if (!$this->useCustomCSS)
		{
			return;
		}

		$jDocument = Joomla::getApplication()->getDocument();

		if (empty($jDocument) || !is_object($jDocument) || !($jDocument instanceof JDocumentHtml))
		{
			return;
		}

		$css = /** @lang CSS */
			<<< CSS
.akeeba-sociallogin-link-button-linkedin, .akeeba-sociallogin-unlink-button-linkedin, .akeeba-sociallogin-button-linkedin { background-color: #ffffff; color: #000000; transition-duration: 0.33s; background-image: none; border-color: #86898C
; }
.akeeba-sociallogin-link-button-linkedin:hover, .akeeba-sociallogin-unlink-button-linkedin:hover, .akeeba-sociallogin-button-linkedin:hover { background-color: #CFEDFB
 ; color: #000000; transition-duration: 0.33s; border-color: #00A0DC
 ; }
.akeeba-sociallogin-link-button-linkedin img, .akeeba-sociallogin-unlink-button-linkedin img, .akeeba-sociallogin-button-linkedin img { display: inline-block; width: 22px; height: 16px; margin: 0 0.33em 0.1em 0; padding: 0 }

CSS;
		$jDocument->addStyleDeclaration($css);
	}
}
