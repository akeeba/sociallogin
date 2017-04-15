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
	private $integrationName = 'facebook';

	/**
	 * The URL for the help content
	 *
	 * @var   string
	 */
	private $helpURL = '';

	/**
	 * Can I use this integration to create new user accounts?
	 *
	 * @var   bool
	 */
	private $canCreate = true;

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

		$this->loadLanguage();

		// Load options
		$this->helpURL = $this->params->get('helpurl', 'https://github.com/akeeba/sociallogin/wiki/Facebook');
		$this->canCreate = $this->params->get('createnew', true);
		$this->appId = $this->params->get('appid', '');
		$this->appSecret = $this->params->get('appsecret', '');
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
	private function getFacebookOauth()
	{
		if (is_null($this->connector))
		{
			$options = new Registry(array(
				'clientid'     => $this->appId,
				'clientsecret' => $this->appSecret,
				'redirecturi'  => JUri::base() . 'index.php?option=com_ajax&group=sociallogin&plugin=facebook&format=raw'
			));
			$this->connector = new JFacebookOAuth($options);
			$this->connector->setScope('public_profile,email');
		}

		return $this->connector;
	}


	/**
	 * Gets the identity of this social login integration
	 *
	 * @return  array|false
	 */
	public function onSocialLoginGetIntegration()
	{
		if (!$this->isProperlySetUp())
		{
			return false;
		}

		return array(
			// Slug for referencing this integration
			'name'       => $this->integrationName,
			// Human readable display title for this integration
			'display'    => JText::_('PLG_SOCIALLOGIN_FACEBOOK_LBLDISPLAYEDAS'),
			// Human readable short description of this integration
			'shortinfo'  => JText::_('PLG_SOCIALLOGIN_FACEBOOK_SHORTINFO'),
			// URL to the logo image for this integration
			'image'      => 'media/plg_sociallogin_facebook/images/facebook.png',
			// Are we allowed to disable (unlink) it after it's set up?
			'canDisable' => true,
			// Can it be used to create user accounts?
			'canCreate'  => $this->canCreate,
			// URL for help content
			'help_url'   => $this->helpURL,
		);
	}

	/**
	 * Is the user linked to the social login account?
	 *
	 * @param   string  $slug  The slug of the integration we are checking against
	 * @param   JUser   $user  The user account we are checking
	 *
	 * @return  bool
	 */
	public function onSocialLoginIsLinked($slug, JUser $user = null)
	{
		// Only respond to requests about our integration
		if ($slug != $this->integrationName)
		{
			return false;
		}

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
	 * @param   string  $slug        The slug of the integration we are checking against
	 * @param   string  $loginURL    The URL to be redirected to upon successful login / account link
	 * @param   string  $failureURL  The URL to be redirected to on error
	 *
	 * @return  array
	 */
	public function onSocialLoginGetButton($slug, $loginURL = null, $failureURL = null)
	{
		// Make sure we are properly set up
		if (!$this->isProperlySetUp())
		{
			return array();
		}

		if ($slug != $this->integrationName)
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
		$connector = $this->getFacebookOauth();
		$url       = $connector->createUrl();

		return array(
			// How to render the button. "anchor" (HTML <a> tag) or "custom" (custom HTML)
			'field_type' => 'anchor',
			// The href attribute for the anchor tag.
			'link'       => $url,
			// The tooltip of the anchor tag. Null for "Login with <Method Display Name>" or "Link <Method Display Name>"
			'tooltip'    => '',
			// The class attribute for the anchor tag. Leave empty for the defaults (typically it's something like "btn btn-default")
			'class'      => '',
			// What to put inside the anchor tag. Leave empty to put the image returned by onSocialLoginGetIntegration.
			'label'      => '',
			// Custom HTML for rendering the login / link field. Only used when field_type = custom.
			'html'       => '',
			// URL for help content
			'help_url'   => $this->helpURL,
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

		// Cannot unlink a guest user!
		if ($user->guest || empty($user->id))
		{
			return;
		}

		$db     = JFactory::getDbo();
		$query  = $db->getQuery(true)
		             ->delete($db->qn('#__user_profiles'))
		             ->where($db->qn('user_id') . ' = ' . $db->q((int) $user->id))
		             ->where($db->qn('profile_key') . ' LIKE ' . $db->q('sociallogin.facebook.%'));

		$db->setQuery($query)->execute();
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
		$facebookOauth = $this->getFacebookOauth();
		$app           = JFactory::getApplication();

		try
		{
			$token = $facebookOauth->authenticate();

			if ($token === false)
			{
				throw new RuntimeException(JText::_('PLG_SOCIALLOGIN_FACEBOOK_ERROR_NOT_LOGGED_IN_FB'));
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
			// Log failed login
			$response                = SocialLoginHelperLogin::getAuthenticationResponseObject();
			$response->status        = JAuthentication::STATUS_UNKNOWN;
			$response->error_message = JText::sprintf('JGLOBAL_AUTH_FAILED', $e->getMessage());
			SocialLoginHelperLogin::processLoginFailure($response);
			$app->redirect($failureUrl);

			return;
		}

		// Look for a local user account with the Facebook user ID
		$userId = $this->getUserIdByFacebookId($fbUserId);

		/**
		 * Does a user exist with the same email as the Facebook email?
		 *
		 * We only do that for verified Facebook users, i.e. people who have already verified that they have control of
		 * their stated email address and / or phone with Facebook. This is a security measure! It prevents someone from
		 * registering a Facebook account under your email address (without verifying that email address) and use it to
		 * login into the Joomla site impersonating you.
		 */
		if ($fbUserVerified && ($userId == 0))
		{
			$userId = SocialLoginHelperLogin::getUserIdByEmail($fbUserEmail);
		}

		if (empty($userId))
		{
			$usersConfig           = JComponentHelper::getParams('com_users');
			$allowUserRegistration = $usersConfig->get('allowUserRegistration');

			// User not found and user registration is disabled
			if ($allowUserRegistration == 0)
			{
				// Log failed login
				$response                = SocialLoginHelperLogin::getAuthenticationResponseObject();
				$response->status        = JAuthentication::STATUS_UNKNOWN;
				$response->error_message = JText::sprintf('JGLOBAL_AUTH_FAILED', JText::_('PLG_SOCIALLOGIN_FACEBOOK_ERROR_LOCAL_NOT_FOUND'));
				SocialLoginHelperLogin::processLoginFailure($response);
				$app->redirect($failureUrl);

				return;
			}

			try
			{
				$userId = SocialLoginHelperLogin::createUser($fbUserEmail, $fullName, $fbUserVerified, $fbUserGMTOffset);
			}
			catch (UnexpectedValueException $e)
			{
				// Log failure to create user (username already exists)
				$response                = SocialLoginHelperLogin::getAuthenticationResponseObject();
				$response->status        = JAuthentication::STATUS_UNKNOWN;
				$response->error_message = JText::sprintf('PLG_SOCIALLOGIN_FACEBOOK_ERROR_CANNOT_CREATE', JText::_('PLG_SOCIALLOGIN_FACEBOOK_ERROR_LOCAL_USERNAME_CONFLICT'));
				SocialLoginHelperLogin::processLoginFailure($response);
				$app->redirect($failureUrl);

				return;
			}
			catch (RuntimeException $e)
			{
				// Log failure to create user (other internal error, check the model error message returned in the exception)
				$response                = SocialLoginHelperLogin::getAuthenticationResponseObject();
				$response->status        = JAuthentication::STATUS_UNKNOWN;
				$response->error_message = JText::sprintf('PLG_SOCIALLOGIN_FACEBOOK_ERROR_CANNOT_CREATE', $e->getMessage());
				SocialLoginHelperLogin::processLoginFailure($response);
				$app->redirect($failureUrl);

				return;
			}

			// Does the account need user or administrator verification?
			if (in_array($userId, array('useractivate', 'adminactivate')))
			{
				// Do NOT go through processLoginFailure. This is NOT a login failure.
				$message = JText::_('PLG_SOCIALLOGIN_FACEBOOK_NOTICE_' . $userId);
				$app->enqueueMessage($message, 'info');
				$app->redirect($failureUrl);

				return;
			}
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

			$app->redirect($loginUrl);
		}
		catch (RuntimeException $e)
		{
			$app->enqueueMessage($e->getMessage(), 'error');
			$app->redirect($failureUrl);
		}
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
		// Load the profile data from the database.
		$db     = JFactory::getDbo();
		$query  = $db->getQuery(true)
		             ->select(array(
			             $db->qn('profile_key'),
			             $db->qn('profile_value'),
		             ))->from($db->qn('#__user_profiles'))
		             ->where($db->qn('user_id') . ' = ' . $db->q((int) $userId))
		             ->where($db->qn('profile_key') . ' LIKE ' . $db->q('sociallogin.facebook.%'))
		             ->order($db->qn('ordering'));
		$fields = $db->setQuery($query)->loadAssocList('profile_key', 'profile_value');

		if (!isset($fields['sociallogin.facebook.userid']))
		{
			$newField = (object) array(
				'user_id'       => $userId,
				'profile_key'   => 'sociallogin.facebook.userid',
				'profile_value' => $fbUserId,
				'ordering'      => 0
			);
			$db->insertObject('#__user_profiles', $newField);
		}
		elseif ($fields['sociallogin.facebook.userid'] != $fbUserId)
		{
			$query = $db->getQuery(true)
						->update($db->qn('#__user_profiles'))
			            ->set($db->qn('profile_value') . ' = ' . $db->q($fbUserId))
			            ->where($db->qn('user_id') . ' = ' . $db->q((int) $userId))
			            ->where($db->qn('profile_key') . ' = ' . $db->q('sociallogin.facebook.userid'));
			$db->setQuery($query)->execute();
		}

		$token = json_encode($token);

		if (!isset($fields['sociallogin.facebook.token']))
		{
			$newField = (object) array(
				'user_id'       => $userId,
				'profile_key'   => 'sociallogin.facebook.token',
				'profile_value' => $token,
				'ordering'      => 0
			);
			$db->insertObject('#__user_profiles', $newField);
		}
		elseif ($fields['sociallogin.facebook.token'] != $token)
		{
			$query = $db->getQuery(true)
			            ->update($db->qn('#__user_profiles'))
			            ->set($db->qn('profile_value') . ' = ' . $db->q($token))
			            ->where($db->qn('user_id') . ' = ' . $db->q((int) $userId))
			            ->where($db->qn('profile_key') . ' = ' . $db->q('sociallogin.facebook.token'));
			$db->setQuery($query)->execute();
		}
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
}