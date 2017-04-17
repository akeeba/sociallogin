<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

/**
 * Helper class for managing Joomla! user login
 */
abstract class SocialLoginHelperLogin
{
	/**
	 * Handle the login callback from a social network. This is called after the plugin code has fetched the account
	 * information from the social network. At this point we are simply checking if we can log in the user, create a
	 * new user account or report a login error.
	 *
	 * @param   string                          $slug             The slug of the social network plugin, e.g. 'facebook'.
	 * @param   SocialLoginPluginConfiguration  $config           The configuration information of the plugin.
	 * @param   SocialLoginUserData             $userData         The social network user account data.
	 * @param   array                           $userProfileData  The data to save in the #__user_profiles table.
	 *
	 * @return  void
	 *
	 * @throws  SocialLoginFailedLoginException     When a login error occurs (must report the login error to Joomla!).
	 * @throws  SocialLoginGenericMessageException  When there is no login error but we need to report a message to the
	 *                                              user, e.g. to tell them they need to click on the activation email.
	 */
	public static function handleSocialLogin($slug, SocialLoginPluginConfiguration $config, SocialLoginUserData $userData, array $userProfileData)
	{
		// Look for a local user account with the social network user ID
		$profileKeys = array_keys($userProfileData);
		$primaryKey  = $profileKeys[0];
		$userId      = SocialLoginHelperIntegrations::getUserIdByProfileData('sociallogin.' . $slug . '.' . $primaryKey, $userData->id);

		/**
		 * If a user is not linked to this social network account we are going to look for a user account that has the
		 * same email address as the social network user.
		 *
		 * We only allow that for verified accounts, i.e. people who have already verified that they have control of
		 * their stated email address and / or phone with the socual network and only if the "Allow social login to
		 * non-linked accounts" switch is enabled in the plugin. If a user exists with the same email when either of
		 * these conditions is false we raise a login failure. This is a security measure! It prevents someone from
		 * registering a social network account under your email address and use it to login into the Joomla site
		 * impersonating you.
		 */
		if (empty($userId))
		{
			$userId = SocialLoginHelperLogin::getUserIdByEmail($userData->email);

			/**
			 * The social network user is not verified. That's a possible security issue so let's pretend we can't find a match.
			 */
			if (!$userData->verified)
			{
				throw new SocialLoginFailedLoginException(JText::_('PLG_SOCIALLOGIN_' . $slug . '_ERROR_LOCAL_NOT_FOUND'));
			}

			/**
			 * This is a verified social network user and we found a user account with the same email address on our
			 * site. If "Allow social login to non-linked accounts" is disabled AND the userId is not null stop with an
			 * error. Otherwise, if "Create new users" is allowed we will be trying to create a user account with an
			 * email address which already exists, leading to failure with a message that makes no sense to the user.
			 */
			if (!$config->canLoginUnlinked && !empty($userId))
			{
				throw new SocialLoginFailedLoginException(JText::_('PLG_SOCIALLOGIN_' . $slug . '_ERROR_LOCAL_USERNAME_CONFLICT'));
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

			if ((($allowUserRegistration == 0) && !$config->canCreateAlways) || !$config->canCreateNewUsers)
			{
				throw new SocialLoginFailedLoginException(JText::_('PLG_SOCIALLOGIN_' . $slug . '_ERROR_LOCAL_NOT_FOUND'));
			}

			try
			{
				/**
				 * If the social network reports the user as verified and the "Bypass user validation for verified
				 * users" option is enabled in the plugin options we tell the helper to not send user
				 * verification emails, immediately activating the user.
				 */
				$bypassVerification = $userData->verified && $config->canBypassValidation;
				$userId             = SocialLoginHelperLogin::createUser($userData->email, $userData->name, $bypassVerification, $userData->timezone);
			}
			catch (UnexpectedValueException $e)
			{
				throw new SocialLoginFailedLoginException(JText::sprintf('PLG_SOCIALLOGIN_' . $slug . '_ERROR_CANNOT_CREATE', JText::_('PLG_SOCIALLOGIN_' . $slug . '_ERROR_LOCAL_USERNAME_CONFLICT')));
			}
			catch (RuntimeException $e)
			{
				throw new SocialLoginFailedLoginException(JText::sprintf('PLG_SOCIALLOGIN_' . $slug . '_ERROR_CANNOT_CREATE', $e->getMessage()));
			}

			// Does the account need user or administrator verification?
			if (in_array($userId, array('useractivate', 'adminactivate')))
			{
				// Do NOT go through processLoginFailure. This is NOT a login failure.
				throw new SocialLoginGenericMessageException(JText::_('PLG_SOCIALLOGIN_' . $slug . '_NOTICE_' . $userId));
			}
		}

		/**
		 * Catch still empty user ID. This means we cannot find any matching user for this social network account and we
		 * are not allowed to create new users. As a result we have to give up and tell the user we can't log them in.
		 */
		if (empty($userId))
		{
			throw new SocialLoginFailedLoginException(JText::_('PLG_SOCIALLOGIN_' . $slug . '_ERROR_LOCAL_NOT_FOUND'));
		}

		// Attach the social network link information to the user's profile
		try
		{
			SocialLoginHelperIntegrations::insertUserProfileData($userId, 'sociallogin.' . $slug, $userProfileData);
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
			throw new SocialLoginFailedLoginException($e->getMessage());
		}
	}

	/**
	 * Returns a (blank) Joomla! authentication response
	 *
	 * @return  JAuthenticationResponse
	 */
	public static function getAuthenticationResponseObject()
	{
		// Force the class auto-loader to load the JAuthentication class
		JLoader::import('joomla.user.authentication');
		class_exists('JAuthentication', true);

		return new JAuthenticationResponse();
	}

	/**
	 * Have Joomla! process a login failure
	 *
	 * @param   JAuthenticationResponse  $response  The Joomla! auth response object
	 * @param   JApplicationBase         $app       The application we are running in. Skip to auto-detect (recommended).
	 *
	 * @return  bool
	 */
	public static function processLoginFailure(JAuthenticationResponse $response, JApplicationBase $app = null)
	{
		// Import the user plugin group.
		SocialLoginHelperJoomla::importPlugins('user');

		if (!is_object($app))
		{
			$app = JFactory::getApplication();
		}

		// Trigger onUserLoginFailure Event.
		SocialLoginHelperJoomla::runPlugins('onUserLoginFailure', array((array) $response), $app);

		// If silent is set, just return false.
		if (isset($options['silent']) && $options['silent'])
		{
			return false;
		}

		// If status is success, any error will have been raised by the user plugin
		if ($response->status !== JAuthentication::STATUS_SUCCESS)
		{
			JLog::add($response->error_message, JLog::WARNING, 'jerror');
		}

		// Enqueue the error message
		JFactory::getApplication()->enqueueMessage($response->error_message, 'error');

		return false;
	}

	/**
	 * Is the user linked to the social login account?
	 *
	 * @param   string  $slug  The slug of the social media integration plugin, e.g. 'facebook'
	 * @param   JUser   $user  The user account we are checking
	 *
	 * @return  bool
	 */
	public static function isLinkedUser($slug, JUser $user = null)
	{
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
		            ->where($db->qn('profile_key') . ' LIKE ' . $db->q('sociallogin.' . $slug . '.%'));

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
	 * Returns the user ID, if a user exists, given an email address.
	 *
	 * @param   string  $email  The email to search on.
	 *
	 * @return  integer  The user id or 0 if not found.
	 */
	private static function getUserIdByEmail($email)
	{
		// Initialise some variables
		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
		            ->select($db->qn('id'))
		            ->from($db->qn('#__users'))
		            ->where($db->qn('email') . ' = ' . $db->q($email));
		$db->setQuery($query, 0, 1);

		return $db->loadResult();
	}

	/**
	 * Create a new user account.
	 *
	 * The username will be the name of the user: converted to all lowercase, all non-word characters squashed into a
	 * maximum of one consecutive dots. For example "Bill W. Gates 3rd" will be converted to "bill.w.gates.3rd". If that
	 * is taken the email will be used instead.
	 *
	 * If the email is already present or we cannot find a username that works we will throw UnexpectedValueException.
	 *
	 * Any other error from the model should result in a RuntimeException.
	 *
	 * @param   string  $email          The email address of the user to create
	 * @param   string  $name           The full name of the user
	 * @param   bool    $emailVerified  Is the email already verified?
	 * @param   string  $timezone       The user's timezone
	 *
	 * @return  mixed  The user id on success, 'useractivate' or 'adminactivate' if activation is required
	 *
	 * @throws  UnexpectedValueException  If the email or username already exists
	 */
	private static function createUser($email, $name, $emailVerified, $timezone)
	{
		// Does the email already exist?
		if (empty($email) || self::getUserIdByEmail($email))
		{
			throw new UnexpectedValueException();
		}

		// Try to create a username from the full name
		$username = preg_replace('/[^\w]/us', '.', $name);
		$username = strtolower($username);

		while (strpos($username, '..') !== false)
		{
			$username = str_replace('..', '.', $username);
		}

		// If the username already exists try using the email as the username
		if (JUserHelper::getUserId($username))
		{
			$username = $email;

			// If that exists too throw an exception
			if (JUserHelper::getUserId($username))
			{
				throw new UnexpectedValueException();
			}
		}

		$data = array(
			'name'     => $name,
			'username' => $username,
			'email'    => $email,
		);

		// Save the timezone into the user parameters
		$userParams = array(
			'timezone' => $timezone,
		);

		return self::register($data, $userParams, $emailVerified);
	}

	/**
	 * Logs in a user to the site, bypassing the authentication plugins.
	 *
	 * @param   int               $userId  The user ID to log in
	 * @param   JApplicationBase  $app     The application we are running in. Skip to auto-detect (recommended).
	 */
	private static function loginUser($userId, JApplicationBase $app = null)
	{
		// Trick the class auto-loader into loading the necessary classes
		JLoader::import('joomla.user.authentication');
		JLoader::import('joomla.plugin.helper');
		JLoader::import('joomla.user.helper');
		class_exists('JAuthentication', true);

		// Fake a successful login message
		if (!is_object($app))
		{
			$app = JFactory::getApplication();
		}

		$isAdmin = method_exists($app, 'isClient') ? $app->isClient('administrator') : $app->isAdmin();
		$user    = JFactory::getUser($userId);

		// Does the user account have a pending activation?
		if (!empty($user->activation))
		{
			throw new RuntimeException(JText::_('JGLOBAL_AUTH_ACCESS_DENIED'));
		}

		// Is the user account blocked?
		if ($user->block)
		{
			throw new RuntimeException(JText::_('JGLOBAL_AUTH_ACCESS_DENIED'));
		}

		$response                = new JAuthenticationResponse();
		$response->status        = JAuthentication::STATUS_SUCCESS;
		$response->username      = $user->username;
		$response->fullname      = $user->name;
		$response->error_message = '';
		$response->language      = $user->getParam('language');
		$response->type          = 'SocialLogin';

		if ($isAdmin)
		{
			$response->language = $user->getParam('admin_language');
		}

		// We force Remember Me when the user uses social login.
		$options = array('remember' => true);

		// Run the user plugins. They CAN block login by returning boolean false and setting $response->error_message.
		SocialLoginHelperJoomla::importPlugins('user');
		$results = SocialLoginHelperJoomla::runPlugins('onUserLogin', array((array) $response, $options), $app);

		// If there is no boolean FALSE result from any plugin the login is successful.
		if (in_array(false, $results, true) == false)
		{
			// Set the user in the session, letting Joomla! know that we are logged in.
			$session = \JFactory::getSession();
			$session->set('user', $user);

			// Trigger the onUserAfterLogin event
			$options['user']         = $user;
			$options['responseType'] = $response->type;

			// The user is successfully logged in. Run the after login events
			SocialLoginHelperJoomla::runPlugins('onUserAfterLogin', array($options), $app);
		}

		// If we are here the plugins marked a login failure. Trigger the onUserLoginFailure Event.
		SocialLoginHelperJoomla::runPlugins('onUserLoginFailure', array((array) $response), $app);

		// Log the failure
		JLog::add($response->error_message, JLog::WARNING, 'jerror');

		// Throw an exception to let the caller know that the login failed
		throw new RuntimeException($response->error_message);
	}

	/**
	 * Method to register a new user account. Based on UsersModelRegistration::register().
	 *
	 * @param   array             $data                The user data to save.
	 * @param   array             $userParams          User parameters to save with the user account
	 * @param   bool              $skipUserActivation  Should I forcibly skip user activation?
	 * @param   JApplicationBase  $app                 The application we are running in. Skip to auto-detect (recommended).
	 *
	 * @return  mixed  The user id on success, 'useractivate' or 'adminactivate' if activation is required
	 */
	private static function register(array $data, array $userParams = array(), $skipUserActivation = false, JApplicationBase $app = null)
	{
		if (!is_object($app))
		{
			$app = JFactory::getApplication();
		}

		$params = JComponentHelper::getParams('com_users');

		$data = array_merge(array(
			'name'     => '',
			'username' => '',
			'password' => '',
			'email'    => '',
			'groups'   => array(),
		), $data);

		// Initialise the table with JUser.
		$user = new JUser;

		// If no password was specified create a random one
		if (!isset($data['password']) || empty($data['password']))
		{
			$data['password'] = JUserHelper::genRandomPassword(24);
		}

		// Convert the email to punycode if necessary
		$data['email']    = JStringPunycode::emailToPunycode($data['email']);

		// Get the groups the user should be added to after registration.
		$data['groups']   = array($params->get('new_usertype', 2));

		/**
		 * Get the dispatcher and load the users plugins.
		 *
		 * IMPORTANT: We cannot go through the JApplicationCms object directly since user plugins will set the error
		 * message on the dispatcher instead of throwing an exception. See the plugins/user/profile/profile.php plugin
		 * file's onContentPrepareData method to understand this questionable approach. Until Joomla! stops supporting
		 * legacy error handling we cannot switch to SocialLoginHelperJoomla::runPlugins and a regular exceptions
		 * handler around it :(
		 */
		$dispatcher = JEventDispatcher::getInstance();
		SocialLoginHelperJoomla::importPlugins('user');

		// Trigger the data preparation event.
		try
		{
			$results = $dispatcher->trigger('onContentPrepareData', array('com_users.registration', $data));
		}
		catch (Exception $e)
		{
			throw new RuntimeException($e->getMessage());
		}

		// Check for errors encountered while preparing the data. YOU CANNOT REMOVE THIS. READ THE BIG COMMENT ABOVE.
		if (count($results) && in_array(false, $results, true))
		{
			throw new RuntimeException($dispatcher->getError());
		}

		// Get the parameters affecting the behavior
		$userActivation   = $params->get('useractivation');
		$sendPassword     = $params->get('sendpassword', 1);
		$sendEmailToAdmin = $params->get('mail_to_admin');

		// Do we have to forcibly skip user activation?
		if ($skipUserActivation)
		{
			$userActivation = 0;
		}

		// Check if the user needs to activate their account.
		if (($userActivation == 1) || ($userActivation == 2))
		{
			$data['activation'] = JApplicationHelper::getHash(JUserHelper::genRandomPassword());
			$data['block']      = 1;
		}

		// Set the user parameters
		$data['params'] = $userParams;

		// Bind the data.
		if (!$user->bind($data))
		{
			throw new RuntimeException(JText::sprintf('COM_USERS_REGISTRATION_BIND_FAILED', $user->getError()));
		}

		// Load the users plugin group.
		SocialLoginHelperJoomla::importPlugins('user');

		// Store the data.
		if (!$user->save())
		{
			throw new RuntimeException(JText::sprintf('COM_USERS_REGISTRATION_SAVE_FAILED', $user->getError()));
		}

		$config = JFactory::getConfig();
		$db     = JFactory::getDbo();
		$query  = $db->getQuery(true);

		// Compile the notification mail values.
		$data             = $user->getProperties();
		$data['fromname'] = $config->get('fromname');
		$data['mailfrom'] = $config->get('mailfrom');
		$data['sitename'] = $config->get('sitename');
		$data['siteurl']  = JUri::root();

		// Handle account activation/confirmation emails.
		$app = JFactory::getApplication();
		$isAdmin = method_exists($app, 'isClient') ? $app->isClient('administrator') : $app->isAdmin();

		switch ($userActivation)
		{
			// Self-activation of user account
			default:
			case 2:
				// Set the link to confirm the user email.
				$uri              = JUri::getInstance();
				$base             = $uri->toString(array('scheme', 'user', 'pass', 'host', 'port'));
				$data['activate'] = $base . JRoute::_('index.php?option=com_users&task=registration.activate&token=' . $data['activation'], false);

				// Remove administrator/ from activate url in case this method is called from admin
				if ($isAdmin)
				{
					$adminPos         = strrpos($data['activate'], 'administrator/');
					$data['activate'] = substr_replace($data['activate'], '', $adminPos, 14);
				}

				$emailSubject = JText::sprintf(
					'COM_USERS_EMAIL_ACCOUNT_DETAILS',
					$data['name'],
					$data['sitename']
				);

				$emailBody = JText::sprintf(
					'COM_USERS_EMAIL_REGISTERED_WITH_ADMIN_ACTIVATION_BODY_NOPW',
					$data['name'],
					$data['sitename'],
					$data['activate'],
					$data['siteurl'],
					$data['username']
				);

				if ($sendPassword)
				{
					$emailBody = JText::sprintf(
						'COM_USERS_EMAIL_REGISTERED_WITH_ADMIN_ACTIVATION_BODY',
						$data['name'],
						$data['sitename'],
						$data['activate'],
						$data['siteurl'],
						$data['username'],
						$data['password_clear']
					);
				}
				break;

			// Administrator activation of user account
			case 1:
				// Set the link to activate the user account.
				$uri              = JUri::getInstance();
				$base             = $uri->toString(array('scheme', 'user', 'pass', 'host', 'port'));
				$data['activate'] = $base . JRoute::_('index.php?option=com_users&task=registration.activate&token=' . $data['activation'], false);

				// Remove administrator/ from activate url in case this method is called from admin
				if ($isAdmin)
				{
					$adminPos         = strrpos($data['activate'], 'administrator/');
					$data['activate'] = substr_replace($data['activate'], '', $adminPos, 14);
				}

				$emailSubject = JText::sprintf(
					'COM_USERS_EMAIL_ACCOUNT_DETAILS',
					$data['name'],
					$data['sitename']
				);

				$emailBody = JText::sprintf(
					'COM_USERS_EMAIL_REGISTERED_WITH_ACTIVATION_BODY_NOPW',
					$data['name'],
					$data['sitename'],
					$data['activate'],
					$data['siteurl'],
					$data['username']
				);

				if ($sendPassword)
				{
					$emailBody = JText::sprintf(
						'COM_USERS_EMAIL_REGISTERED_WITH_ACTIVATION_BODY',
						$data['name'],
						$data['sitename'],
						$data['activate'],
						$data['siteurl'],
						$data['username'],
						$data['password_clear']
					);
				}

				break;

			// No activation required
			case 0:
				$emailSubject = JText::sprintf(
					'COM_USERS_EMAIL_ACCOUNT_DETAILS',
					$data['name'],
					$data['sitename']
				);

				$emailBody = JText::sprintf(
					'COM_USERS_EMAIL_REGISTERED_BODY_NOPW',
					$data['name'],
					$data['sitename'],
					$data['siteurl']
				);

				if ($sendPassword)
				{
					$emailBody = JText::sprintf(
						'COM_USERS_EMAIL_REGISTERED_BODY',
						$data['name'],
						$data['sitename'],
						$data['siteurl'],
						$data['username'],
						$data['password_clear']
					);
				}

				break;
		}

		// Send the registration email.
		$return = JFactory::getMailer()
		                  ->sendMail($data['mailfrom'], $data['fromname'], $data['email'], $emailSubject, $emailBody);

		// Send Notification mail to administrators
		if (($userActivation < 2) && ($sendEmailToAdmin == 1))
		{
			$emailSubject = JText::sprintf(
				'COM_USERS_EMAIL_ACCOUNT_DETAILS',
				$data['name'],
				$data['sitename']
			);

			$emailBodyAdmin = JText::sprintf(
				'COM_USERS_EMAIL_REGISTERED_NOTIFICATION_TO_ADMIN_BODY',
				$data['name'],
				$data['username'],
				$data['siteurl']
			);

			// Get all admin users
			$query->clear()
			      ->select($db->quoteName(array('name', 'email', 'sendEmail')))
			      ->from($db->quoteName('#__users'))
			      ->where($db->quoteName('sendEmail') . ' = 1')
			      ->where($db->quoteName('block') . ' = 0');

			$db->setQuery($query);

			try
			{
				$rows = $db->loadObjectList();
			}
			catch (RuntimeException $e)
			{
				throw new RuntimeException(JText::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 500);
			}

			// Send mail to all Super Users
			foreach ($rows as $row)
			{
				$return = JFactory::getMailer()
				                  ->sendMail($data['mailfrom'], $data['fromname'], $row->email, $emailSubject, $emailBodyAdmin);

				// Check for an error.
				if ($return !== true)
				{
					throw new RuntimeException(JText::_('COM_USERS_REGISTRATION_ACTIVATION_NOTIFY_SEND_MAIL_FAILED'));
				}
			}
		}

		// Check for an error.
		if ($return !== true)
		{
			// Send a system message to administrators receiving system mails
			$db = JFactory::getDbo();
			$query->clear()
			      ->select($db->qn('id'))
			      ->from($db->qn('#__users'))
			      ->where($db->qn('block') . ' = ' . (int) 0)
			      ->where($db->qn('sendEmail') . ' = ' . (int) 1);
			$db->setQuery($query);

			try
			{
				$userids = $db->loadColumn();
			}
			catch (RuntimeException $e)
			{
				throw new RuntimeException(JText::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 500);
			}

			if (count($userids) > 0)
			{
				$jdate = new JDate;

				// Build the query to add the messages
				foreach ($userids as $userid)
				{
					$values = array(
						$db->quote($userid),
						$db->quote($userid),
						$db->quote($jdate->toSql()),
						$db->quote(JText::_('COM_USERS_MAIL_SEND_FAILURE_SUBJECT')),
						$db->quote(JText::sprintf('COM_USERS_MAIL_SEND_FAILURE_BODY', $return, $data['username']))
					);
					$query->clear()
					      ->insert($db->quoteName('#__messages'))
					      ->columns($db->quoteName(array(
						      'user_id_from',
						      'user_id_to',
						      'date_time',
						      'subject',
						      'message'
					      )))
					      ->values(implode(',', $values));
					$db->setQuery($query);

					try
					{
						$db->execute();
					}
					catch (RuntimeException $e)
					{
						throw new RuntimeException(JText::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 500);
					}
				}
			}

			throw new RuntimeException(JText::_('COM_USERS_REGISTRATION_SEND_MAIL_FAILED'));
		}

		if ($userActivation == 1)
		{
			return 'useractivate';
		}
		elseif ($userActivation == 2)
		{
			return 'adminactivate';
		}
		else
		{
			return $user->id;
		}
	}
}