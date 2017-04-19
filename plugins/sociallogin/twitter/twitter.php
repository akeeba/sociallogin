<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

use Joomla\Registry\Registry;

if (!class_exists('SocialLoginHelperLogin', true))
{
	return;
}

/**
 * Akeeba Social Login plugin for Twitter integration
 */
class plgSocialloginTwitter extends JPlugin
{
	/**
	 * The integration slug used by this plugin
	 *
	 * @var   string
	 */
	private $integrationName = '';

	/**
	 * Should I log in users who have not yet linked their Twitter account to their site account? THIS MAY BE DANGEROUS
	 * (impersonation risk), therefore it is disabled by default.
	 *
	 * @var   bool
	 */
	private $canLoginUnlinked = false;

	/**
	 * Can I use this integration to create new user accounts? This will happen when someone tries to login through
	 * Twitter but their Twitter account is not linked to a user account yet.
	 *
	 * @var   bool
	 */
	private $canCreateNewUsers = false;

	/**
	 * Allow the plugin to override Joomla's new user account registration flag. This is useful to prevent new user
	 * accounts from being created _unless_ they have a Twitter account and use it on your site (force new users to
	 * link their social media accounts).
	 *
	 * @var   bool
	 */
	private $canCreateAlways = false;

	/**
	 * When creating new users, am I allowed to bypass email verification if Twitter reports the user as verified on
	 * their end?
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
	 * Twitter Client ID
	 *
	 * @var   string
	 */
	private $clientId = '';

	/**
	 * Twitter Client Secret
	 *
	 * @var   string
	 */
	private $clientSecret = '';

	/**
	 * Twitter OAUth connector object
	 *
	 * @var   JTwitterOAuth
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

		// Set the integration name from the plugin name (without the plg_system_ part, of course)
		$this->integrationName = $this->_name;

		// Load the plugin options into properties
		$this->clientId            = $this->params->get('appid', '');
		$this->clientSecret        = $this->params->get('appsecret', '');
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
		return !(empty($this->clientId) || empty($this->clientSecret));
	}

	/**
	 * Returns a JTwitterOAuth object
	 *
	 * @return  JTwitterOAuth
	 */
	private function getClient()
	{
		if (is_null($this->connector))
		{
			$options = new Registry(array(
				'callback'        => JUri::base() . 'index.php?option=com_ajax&group=sociallogin&plugin=' . $this->integrationName . '&format=raw',
				'consumer_key'    => $this->clientId,
				'consumer_secret' => $this->clientSecret,
			    'sendheaders'     => true,
			));

			$this->connector = new JTwitterOAuth($options);
		}

		return $this->connector;
	}

	/**
	 * Is the user linked to the social login account?
	 *
	 * @param   JUser   $user  The user account we are checking
	 *
	 * @return  bool
	 */
	private function isLinked(JUser $user = null)
	{
		// Make sure we are set up
		if (!$this->isProperlySetUp())
		{
			return false;
		}

		return SocialLoginHelperLogin::isLinkedUser($this->integrationName, $user);
	}

	/**
	 * Initiate the user authentication (steps 1 & 2 per the Twitter documentation). Step 3 in the documentation is
	 * Twitter calling back our site, i.e. the call to the onAjaxTwitter method.
	 *
	 * @param   string  $slug  The slug of the integration method being called.
	 *
	 * @see https://dev.twitter.com/web/sign-in/implementing
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
		$this->getClient()->authenticate();
	}

	/**
	 * Get the information required to render a login / link account button
	 *
	 * @param   string  $loginURL    The URL to be redirected to upon successful login / account link
	 * @param   string  $failureURL  The URL to be redirected to on error
	 *
	 * @return  array
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
		$session = JFactory::getSession();
		$session->set('loginUrl', $loginURL, 'plg_sociallogin_twitter');
		$session->set('failureUrl', $failureURL, 'plg_sociallogin_twitter');

		/**
		 * Authentication has to go through a special com_ajax URL since the OAuth1 client needs to performs a Twitter
		 * server query and then a redirection to authenticate the user.
		 */
		$token = $session->getToken();
		$url   = JUri::base() . 'index.php?option=com_ajax&group=system&plugin=sociallogin&format=raw&akaction=authenticate&encoding=redirect&slug=' . $this->integrationName . '&' . $token . '=1';

		// Add custom CSS
		$this->addCustomCSS();

		return array(
			// The name of the plugin rendering this button. Used for customized JLayouts.
			'slug'       => $this->integrationName,
			// The href attribute for the anchor tag.
			'link'       => $url,
			// The tooltip of the anchor tag.
			'tooltip'    => JText::_('PLG_SOCIALLOGIN_TWITTER_LOGIN_DESC'),
			// What to put inside the anchor tag. Leave empty to put the image returned by onSocialLoginGetIntegration.
			'label'      => JText::_('PLG_SOCIALLOGIN_TWITTER_LOGIN_LABEL'),
			// The image to use if there is no icon class
			'img'        => JHtml::image('plg_sociallogin_twitter/twitter.png', '', array(), true),
			// An icon class for the span before the label inside the anchor tag. Nothing is shown if this is blank.
		    'icon_class' => $this->iconClass,
		);
	}

	/**
	 * Get the information required to render a link / unlink account button
	 *
	 * @param   JUser   $user        The user to be linked / unlinked
	 *
	 * @return  array
	 */
	public function onSocialLoginGetLinkButton(JUser $user = null)
	{
		// Make sure we are properly set up
		if (!$this->isProperlySetUp())
		{
			return array();
		}

		if (empty($user))
		{
			$user = JFactory::getUser();
		}

		// Get the return URL
		$returnURL = JUri::getInstance()->toString(array('scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'fragment'));

		// Save the return URL and user ID into the session
		$session = JFactory::getSession();
		$session->set('returnUrl', $returnURL, 'plg_system_sociallogin');
		$session->set('userID', $user->id, 'plg_system_sociallogin');

		if ($this->isLinked($user))
		{
			$token = $session->getToken();
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
				'tooltip'    => JText::_('PLG_SOCIALLOGIN_TWITTER_UNLINK_DESC'),
				// What to put inside the anchor tag. Leave empty to put the image returned by onSocialLoginGetIntegration.
				'label'      => JText::_('PLG_SOCIALLOGIN_TWITTER_UNLINK_LABEL'),
				// The image to use if there is no icon class
				'img'        => JHtml::image('plg_sociallogin_twitter/twitter.png', '', array(), true),
				// An icon class for the span before the label inside the anchor tag. Nothing is shown if this is blank.
				'icon_class' => $this->iconClass,
			);
		}

		// Make sure we return to the same profile edit page
		$loginURL = JUri::getInstance()->toString(array('scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'fragment'));
		$session = JFactory::getSession();
		$session->set('loginUrl', $loginURL, 'plg_sociallogin_twitter');
		$session->set('failureUrl', $loginURL, 'plg_sociallogin_twitter');

		/**
		 * Authentication has to go through a special com_ajax URL since the OAuth1 client needs to performs a Twitter
		 * server query and then a redirection to authenticate the user.
		 */
		$token = $session->getToken();
		$url   = JUri::base() . 'index.php?option=com_ajax&group=system&plugin=sociallogin&format=raw&akaction=authenticate&encoding=redirect&slug=' . $this->integrationName . '&' . $token . '=1';


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
			'tooltip'    => JText::_('PLG_SOCIALLOGIN_TWITTER_LINK_DESC'),
			// What to put inside the anchor tag. Leave empty to put the image returned by onSocialLoginGetIntegration.
			'label'      => JText::_('PLG_SOCIALLOGIN_TWITTER_LINK_LABEL'),
			// The image to use if there is no icon class
			'img'        => JHtml::image('plg_sociallogin_twitter/twitter.png', '', array(), true),
			// An icon class for the span before the label inside the anchor tag. Nothing is shown if this is blank.
			'icon_class' => $this->iconClass,
		);
	}

	/**
	 * Unlink a user account from a social login integration
	 *
	 * @param   string      $slug  The integration to unlink from
	 * @param   JUser|null  $user  The user to unlink, null to use the current user
	 *
	 * @return  void
	 */
	public function onSocialLoginUnlink($slug, JUser $user = null)
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
			$user = JFactory::getUser();
		}

		SocialLoginHelperIntegrations::removeUserProfileData($user->id, 'sociallogin.twitter');
	}

	/**
	 * Processes the authentication callback from Twitter.
	 *
	 * Note: this method is called from Joomla's com_ajax, not com_sociallogin itself
	 *
	 * @return  void
	 */
	public function onAjaxTwitter()
	{
		// Get the return URLs from the session
		$session    = JFactory::getSession();
		// This is the return URL used by the Link button
		$returnURL  = $session->get('returnUrl', JUri::base(), 'plg_system_sociallogin');
		// And this is the login success URL used by the Login button
		$loginUrl   = $session->get('loginUrl', $returnURL, 'plg_sociallogin_twitter');
		$failureUrl = $session->get('failureUrl', $loginUrl, 'plg_sociallogin_twitter');

		// Remove the return URLs from the session
		$session->set('loginUrl', null, 'plg_sociallogin_twitter');
		$session->set('failureUrl', null, 'plg_sociallogin_twitter');

		// Try to exchange the code with a token
		$connector    = $this->getClient();
		$app          = JFactory::getApplication();

		/**
		 * Handle the login callback from Twitter. There are three possibilities:
		 *
		 * 1. SocialLoginFailedLoginException exception is thrown. We must go through Joomla's user plugins and let
		 *    them handle the login failure. They MAY change the error response. Then we report that error reponse to
		 *    the user while redirecting them to the error handler page.
		 *
		 * 2. SocialLoginGenericMessageException exception is thrown. We must NOT go through the user
		 *    plugins, this is not a login error. Most likely we have to tell the user to validate their account.
		 *
		 * 3. No exception is thrown. Proceed to the login success page ($loginUrl).
		 */
		try
		{
			try
			{
				$token = $connector->authenticate();

				if ($token === false)
				{
					throw new SocialLoginFailedLoginException(JText::_('PLG_SOCIALLOGIN_TWITTER_ERROR_NOT_LOGGED_IN_TWITTER'));
				}

				// Get information about the user from Twitter.
				$parameters    = array(
					'oauth_token' => $token['key'],
				);
				$data          = array(
					'skip_status'   => 'true',
					'include_email' => 'true',
				);
				$path          = 'https://api.twitter.com/1.1/account/verify_credentials.json';
				$response      = $connector->oauthRequest($path, 'GET', $parameters, $data);
				$twitterFields = json_decode($response->body, true);

				if ($response->code != 200)
				{
					throw new SocialLoginFailedLoginException(JText::_('PLG_SOCIALLOGIN_TWITTER_ERROR_NOT_LOGGED_IN_TWITTER'));
				}
			}
			catch (Exception $e)
			{
				throw new SocialLoginFailedLoginException($e->getMessage());
			}

			// The data used to login or create a user
			$userData           = new SocialLoginUserData;
			$userData->name     = $twitterFields['name'];
			$userData->id       = $twitterFields['id'];
			$userData->timezone = $twitterFields['utc_offset'] / 3600;
			$userData->email    = '';
			$userData->verified = false;

			if (isset($twitterFields['email']) && !empty($twitterFields['email']))
			{
				$userData->email    = $twitterFields['email'];
				$userData->verified = true;
			}

			// Options which control login and user account creation
			$pluginConfiguration                      = new SocialLoginPluginConfiguration;
			$pluginConfiguration->canLoginUnlinked    = $this->canLoginUnlinked;
			$pluginConfiguration->canCreateAlways     = $this->canCreateAlways;
			$pluginConfiguration->canCreateNewUsers   = $this->canCreateNewUsers;
			$pluginConfiguration->canBypassValidation = $this->canBypassValidation;

			/**
			 * Data to save to the user profile. The first row is the primary key which links the Joomla! user account to
			 * the social media account.
			 */
			$userProfileData = array(
				'userid' => $userData->id,
				'token'  => json_encode($token),
			);

			SocialLoginHelperLogin::handleSocialLogin($this->integrationName, $pluginConfiguration, $userData, $userProfileData);
		}
		catch (SocialLoginFailedLoginException $e)
		{
			// Log failed login
			$response                = SocialLoginHelperLogin::getAuthenticationResponseObject();
			$response->status        = JAuthentication::STATUS_UNKNOWN;
			$response->error_message = $e->getMessage();

			// This also enqueues the login failure message for display after redirection. Look for JLog in that method.
			SocialLoginHelperLogin::processLoginFailure($response);

			$app->redirect($failureUrl);

			return;
		}
		catch (SocialLoginGenericMessageException $e)
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

		$jDocument = JFactory::getApplication()->getDocument();

		if (empty($jDocument) || !is_object($jDocument) || !($jDocument instanceof JDocumentHtml))
		{
			return;
		}

		$css = /** @lang CSS */
			<<< CSS
.akeeba-sociallogin-link-button-twitter, .akeeba-sociallogin-unlink-button-twitter, .akeeba-sociallogin-button-twitter { background-color: #1DA1F2
; color: #ffffff; transition-duration: 0.33s; background-image: none; border-color: #0b4060; }
.akeeba-sociallogin-link-button-twitter:hover, .akeeba-sociallogin-unlink-button-twitter:hover, .akeeba-sociallogin-button-twitter:hover { background-color: #1470a9; color: #ffffff; transition-duration: 0.33s; border-color: #3B5998; }
.akeeba-sociallogin-link-button-twitter img, .akeeba-sociallogin-unlink-button-twitter img, .akeeba-sociallogin-button-twitter img { width: 16px; height: 16px; margin: 0 0.33em 0.1em 0; padding: 0 }

CSS;


		$jDocument->addStyleDeclaration($css);
	}
}