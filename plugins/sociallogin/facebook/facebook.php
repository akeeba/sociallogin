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
 * Akeeba Social Login plugin for Facebook integration
 */
class plgSocialloginFacebook extends JPlugin
{
	/**
	 * The integration slug used by this plugin
	 *
	 * @var   string
	 */
	private $integrationName = '';

	/**
	 * Should I log in users who have not yet linked their Facebook account to their site account? THIS MAY BE DANGEROUS
	 * (impersonation risk), therefore it is disabled by default.
	 *
	 * @var   bool
	 */
	private $canLoginUnlinked = false;

	/**
	 * Can I use this integration to create new user accounts? This will happen when someone tries to login through
	 * Facebook but their Facebook account is not linked to a user account yet.
	 *
	 * @var   bool
	 */
	private $canCreateNewUsers = false;

	/**
	 * Allow the plugin to override Joomla's new user account registration flag. This is useful to prevent new user
	 * accounts from being created _unless_ they have a Facebook account and use it on your site (force new users to
	 * link their social media accounts).
	 *
	 * @var   bool
	 */
	private $canCreateAlways = false;

	/**
	 * When creating new users, am I allowed to bypass email verification if Facebook reports the user as verified on
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
	 * Facebook OAUth connector object
	 *
	 * @var   JFacebookOAuth
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
	 * Returns a JFacebookOAuth object
	 *
	 * @return  JFacebookOAuth
	 */
	private function getConnector()
	{
		if (is_null($this->connector))
		{
			$options = new Registry(array(
				'clientid'     => $this->appId,
				'clientsecret' => $this->appSecret,
				'redirecturi'  => JUri::base() . 'index.php?option=com_ajax&group=sociallogin&plugin=' . $this->integrationName . '&format=raw'
			));
			$this->connector = new JFacebookOAuth($options);
			$this->connector->setScope('public_profile,email');
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

		// Make sure there's a user to check for
		if (empty($user) || !is_object($user) || !($user instanceof JUser))
		{
			$user = JFactory::getUser();
		}

		// Make sure there's a valid user
		if ($user->guest || empty ($user->id))
		{
			return false;
		}

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
		            ->select('COUNT(*)')
		            ->from($db->qn('#__user_profiles'))
		            ->where($db->qn('user_id') . ' = ' . $db->q($user->id))
		            ->where($db->qn('profile_key') . ' = ' . $db->q('sociallogin.facebook.userid'));

		try
		{
			$count = $db->setQuery($query)->loadResult();

			return $count != 0;
		}
		catch (Exception $e)
		{
			return false;
		}
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
		$session->set('loginUrl', $loginURL, 'plg_sociallogin_facebook');
		$session->set('failureUrl', $failureURL, 'plg_sociallogin_facebook');

		// Get a Facebook OAUth2 connector object and retrieve the URL
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
			'tooltip'    => JText::_('PLG_SOCIALLOGIN_FACEBOOK_LOGIN_DESC'),
			// What to put inside the anchor tag. Leave empty to put the image returned by onSocialLoginGetIntegration.
			'label'      => JText::_('PLG_SOCIALLOGIN_FACEBOOK_LOGIN_LABEL'),
			// The image to use if there is no icon class
			'img'        => JHtml::image('plg_sociallogin_facebook/fb_white_29.png', '', array(), true),
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
			$unlinkURL = JUri::base() . 'index.php?option=com_ajax&group=system&plugin=sociallogin&format=raw&akaction=unlink&slug=' . $this->integrationName . '&' . $token . '=1';

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
				'tooltip'    => JText::_('PLG_SOCIALLOGIN_FACEBOOK_UNLINK_LABEL'),
				// What to put inside the anchor tag. Leave empty to put the image returned by onSocialLoginGetIntegration.
				'label'      => JText::_('PLG_SOCIALLOGIN_FACEBOOK_UNLINK_DESC'),
				// An icon class for the span before the label inside the anchor tag. Nothing is shown if this is blank.
				'icon_class' => 'icon-facebook-sign',
			);
		}

		// Get a Facebook OAUth2 connector object and retrieve the URL
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
			'tooltip'    => JText::_('PLG_SOCIALLOGIN_FACEBOOK_LINK_DESC'),
			// What to put inside the anchor tag. Leave empty to put the image returned by onSocialLoginGetIntegration.
			'label'      => JText::_('PLG_SOCIALLOGIN_FACEBOOK_LINK_LABEL'),
			// An icon class for the span before the label inside the anchor tag. Nothing is shown if this is blank.
			'icon_class' => 'icon-facebook-sign',
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

		SocialLoginHelperIntegrations::removeUserProfileData($user->id, 'sociallogin.facebook');
	}

	/**
	 * Processes the authentication callback from Facebook.
	 *
	 * Note: this method is called from Joomla's com_ajax, not com_sociallogin itself
	 *
	 * @return  void
	 */
	public function onAjaxFacebook()
	{
		// Get the return URLs from the session
		$session    = JFactory::getSession();
		$loginUrl   = $session->get('loginUrl', null, 'plg_sociallogin_facebook');
		$failureUrl = $session->get('failureUrl', null, 'plg_sociallogin_facebook');

		// Remove the return URLs from the session
		$session->set('loginUrl', null, 'plg_sociallogin_facebook');
		$session->set('failureUrl', null, 'plg_sociallogin_facebook');

		// Try to exchange the code with a token
		$oauthConnector = $this->getConnector();
		$app            = JFactory::getApplication();

		/**
		 * Handle the login callback from Facebook. There are three possibilities:
		 *
		 * 1. plgSocialloginFacebookLoginException exception is thrown. We must go through Joomla's user plugins and let
		 *    them handle the login failure. They MAY change the error response. Then we report that error reponse to
		 *    the user while redirecting them to the error handler page.
		 *
		 * 2. plgSocialloginFacebookGenericMessageException exception is thrown. We must NOT go through the user
		 *    plugins, this is not a login error. Most likely we have to tell the user to validate their account.
		 *
		 * 3. No exception is thrown. Proceed to the login success page ($loginUrl).
		 */
		try
		{
			$this->handleLogin($oauthConnector);
		}
		catch (plgSocialloginFacebookLoginException $e)
		{
			// Log failed login
			$response                = SocialLoginHelperLogin::getAuthenticationResponseObject();
			$response->status        = JAuthentication::STATUS_UNKNOWN;
			$response->error_message = JText::sprintf('JGLOBAL_AUTH_FAILED', $e->getMessage());
			SocialLoginHelperLogin::processLoginFailure($response);
			$app->redirect($failureUrl);

			return;
		}
		catch (plgSocialloginFacebookGenericMessageException $e)
		{
			// Do NOT go through processLoginFailure. This is NOT a login failure.
			$app->enqueueMessage($e->getMessage(), 'info');
			$app->redirect($failureUrl);

			return;
		}

		$app->redirect($loginUrl);
	}

	/**
	 * Links the user account to the Facebook account through User Profile fields
	 *
	 * @param   int    $userId   The Joomla! user ID
	 * @param   int    $fbUserId The Facebook user ID
	 * @param   string $token    The Facebook OAuth token
	 *
	 * @return  void
	 *
	 * @since   3.7
	 */
	private function linkToFacebook($userId, $fbUserId, $token)
	{
		$data = array(
			'userid' => $fbUserId,
			'token' => json_encode($token),
		);

		SocialLoginHelperIntegrations::insertUserProfileData($userId, 'sociallogin.facebook', $data);
	}

	/**
	 * Gets the Joomla! user ID that corresponds to a Facebook user ID. Of course that implies that the user has logged
	 * in to the Joomla! site through Facebook in the past or he has otherwise linked his user account to Facebook.
	 *
	 * @param   string $fbUserId The Facebook User ID.
	 *
	 * @return  int  The corresponding user ID or 0 if no user is found
	 *
	 * @since   3.7
	 */
	private function getUserIdByFacebookId($fbUserId)
	{
		// TODO Generalize in the helper

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
		            ->select(array(
			            $db->qn('user_id'),
		            ))->from($db->qn('#__user_profiles'))
		            ->where($db->qn('profile_key') . ' = ' . $db->q('sociallogin.facebook.userid'))
		            ->where($db->qn('profile_value') . ' = ' . $db->q($fbUserId));

		try
		{
			$id = $db->setQuery($query, 0, 1)->loadResult();

			// Not found?
			if (empty($id))
			{
				return 0;
			}

			/**
			 * If you delete a user its profile fields are left behind and confuse our code. Therefore we have to check
			 * if the user *really* exists. However we can't just go through JFactory::getUser() because if the user
			 * does not exist we'll end up with an ugly Warning on our page with a text similar to "JUser: :_load:
			 * Unable to load user with ID: 1234". This cannot be disabled so we have to be, um, a bit creative :/
			 */
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true)
			            ->select('COUNT(*)')->from($db->qn('#__users'))
			            ->where($db->qn('id') . ' = ' . $db->q($id));
			$userExists = $db->setQuery($query)->loadResult();
			return ($userExists == 0) ? 0 : $id;
		}
		catch (Exception $e)
		{
			return 0;
		}
	}

	private function addCustomCSS()
	{
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
.akeeba-sociallogin-link-button-facebook, .akeeba-sociallogin-unlink-button-facebook, .akeeba-sociallogin-button-facebook { background-color: #3B5998; color: #ffffff; transition-duration: 0.33s }
.akeeba-sociallogin-link-button-facebook img, .akeeba-sociallogin-unlink-button-facebook img, .akeeba-sociallogin-button-facebook img { width: 16px; height: 16px; margin: 0 0.33em 0.1em 0; padding: 0; }
.akeeba-sociallogin-link-button-facebook:hover, .akeeba-sociallogin-unlink-button-facebook:hover, .akeeba-sociallogin-button-facebook:hover { background-color: #8B9DC3; color: #ffffff; transition-duration: 0.33s }
CSS;


		$jDocument->addStyleDeclaration($css);
	}

	/**
	 * Handle the Facebook login callback
	 *
	 * @param   JFacebookOAuth  $facebookOauth  The Facebook OAuth object, used to retrieve the user data
	 *
	 * @throws  plgSocialloginFacebookLoginException  when a login error occurs
	 * @throws  plgSocialloginFacebookGenericMessageException  when we need to tell the user to do something more to log in to the site
	 */
	private function handleLogin(JFacebookOAuth $facebookOauth)
	{
		try
		{
			$token = $facebookOauth->authenticate();

			if ($token === false)
			{
				throw new plgSocialloginFacebookLoginException(JText::_('PLG_SOCIALLOGIN_FACEBOOK_ERROR_NOT_LOGGED_IN_FB'));
			}

			// Get information about the user from Big Brother... er... Facebook.
			$options = new Registry();
			$options->set('api.url', 'https://graph.facebook.com/v2.7/');
			$fbUserApi       = new JFacebookUser($options, null, $facebookOauth);
			$fbUserFields    = $fbUserApi->getUser('me?fields=id,name,email,verified,timezone');
			$fullName        = $fbUserFields->name;
			$fbUserId        = $fbUserFields->id;
			$fbUserEmail     = $fbUserFields->email;
			$fbUserVerified  = $fbUserFields->verified;
			$fbUserGMTOffset = $fbUserFields->timezone;
		}
		catch (Exception $e)
		{
			throw new plgSocialloginFacebookLoginException($e->getMessage());
		}

		// Look for a local user account with the Facebook user ID
		$userId = $this->getUserIdByFacebookId($fbUserId);

		/**
		 * If a user is not linked to this Facebook account we are going to look for a user account that has the same
		 * email address as the Facebook user.
		 *
		 * We only allow that for verified Facebook users, i.e. people who have already verified that they have control
		 * of their stated email address and / or phone with Facebook and only if the "Allow social login to non-linked
		 * accounts" switch is enabled in the plugin. If a user exists with the same email when either of these
		 * conditions is false we raise a login failure. This is a security measure! It prevents someone from
		 * registering a Facebook account under your email address and use it to login into the Joomla site
		 * impersonating you.
		 */
		if (empty($userId))
		{
			$userId = SocialLoginHelperLogin::getUserIdByEmail($fbUserEmail);

			/**
			 * The Facebook user is not verified. That's a possible security issue so let's pretend we can't find a match.
			 */
			if (!$fbUserVerified)
			{
				throw new plgSocialloginFacebookLoginException(JText::_('PLG_SOCIALLOGIN_FACEBOOK_ERROR_LOCAL_NOT_FOUND'));
			}

			/**
			 * This is a verified Facebook user and we found a user account with the same email address on our site. If
			 * "Allow social login to non-linked accounts" is disabled AND the userId is not null stop with an error.
			 * Otherwise, if "Create new users" is allowed we will be trying to create a user account with an email
			 * address which already exists, leading to failure with a message that makes no sense to the user.
			 */
			if (!$this->canLoginUnlinked && !empty($userId))
			{
				throw new plgSocialloginFacebookLoginException(JText::_('PLG_SOCIALLOGIN_FACEBOOK_ERROR_LOCAL_USERNAME_CONFLICT'));
			}
		}

		if (empty($userId))
		{
			$usersConfig           = JComponentHelper::getParams('com_users');
			$allowUserRegistration = $usersConfig->get('allowUserRegistration');

			/**
			 * User not found and user registration is disabled OR create new users is not allowed. Note that if the
			 * canCreateAlways flag is set we override Joomla's user registration preference. This can be used to force
			 * new account registrations to take place only through social media logins.
			 */

			if ((($allowUserRegistration == 0) && !$this->canCreateAlways) || !$this->canCreateNewUsers)
			{
				throw new plgSocialloginFacebookLoginException(JText::_('PLG_SOCIALLOGIN_FACEBOOK_ERROR_LOCAL_NOT_FOUND'));
			}

			try
			{
				/**
				 * If Facebook reports the user as verified and the "Bypass user validation for verified Facebook users"
				 * option is enabled in the plugin options we tell the helper to not send user verification emails,
				 * immediately activating the user.
				 */
				$bypassVerification = $fbUserVerified && $this->canBypassValidation;
				$userId             = SocialLoginHelperLogin::createUser($fbUserEmail, $fullName, $bypassVerification, $fbUserGMTOffset);
			}
			catch (UnexpectedValueException $e)
			{
				throw new plgSocialloginFacebookLoginException(JText::sprintf('PLG_SOCIALLOGIN_FACEBOOK_ERROR_CANNOT_CREATE', JText::_('PLG_SOCIALLOGIN_FACEBOOK_ERROR_LOCAL_USERNAME_CONFLICT')));
			}
			catch (RuntimeException $e)
			{
				throw new plgSocialloginFacebookLoginException(JText::sprintf('PLG_SOCIALLOGIN_FACEBOOK_ERROR_CANNOT_CREATE', $e->getMessage()));
			}

			// Does the account need user or administrator verification?
			if (in_array($userId, array('useractivate', 'adminactivate')))
			{
				// Do NOT go through processLoginFailure. This is NOT a login failure.
				throw new plgSocialloginFacebookGenericMessageException(JText::_('PLG_SOCIALLOGIN_FACEBOOK_NOTICE_' . $userId));
			}
		}

		/**
		 * Catch still empty user ID. This means we cannot find any matching user for this Facebook login and we are not
		 * allowed to create new users. As a result we have to give up and tell the user we can't log them in.
		 */
		if (empty($userId))
		{
			throw new plgSocialloginFacebookLoginException(JText::_('PLG_SOCIALLOGIN_FACEBOOK_ERROR_LOCAL_NOT_FOUND'));
		}

		// Attach the Facebook user ID and token to the user's profile
		try
		{
			$this->linkToFacebook($userId, $fbUserId, $token);
		}
		catch (Exception $e)
		{
			// Ignore database exceptions at this point
		}

		// Log in the user
		try
		{
			SocialLoginHelperLogin::loginUser($userId);
		}
		catch (Exception $e)
		{
			throw new plgSocialloginFacebookLoginException($e->getMessage());
		}
	}
}

/**
 * Exception thrown when a login error occurs. The application must go through the failed login user plugin handlers.
 */
class plgSocialloginFacebookLoginException extends RuntimeException {}

/**
 * Exception thrown when a generic error occurs. The application must redirect to the error page WITHOUT going through
 * the login failure handlers of the user plugins.
 */
class plgSocialloginFacebookGenericMessageException extends RuntimeException {}