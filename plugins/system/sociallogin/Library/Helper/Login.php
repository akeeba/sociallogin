<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\SocialLogin\Library\Helper;

// Protect from unauthorized access
defined('_JEXEC') || die();

use Akeeba\SocialLogin\Library\Data\PluginConfiguration;
use Akeeba\SocialLogin\Library\Data\UserData;
use Akeeba\SocialLogin\Library\Exception\Login\GenericMessage;
use Akeeba\SocialLogin\Library\Exception\Login\LoginError;
use Exception;
use Joomla\Application\AbstractApplication;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Application\ApplicationHelper as JApplicationHelper;
use Joomla\CMS\Authentication\Authentication;
use Joomla\CMS\Authentication\Authentication as JAuthentication;
use Joomla\CMS\Authentication\AuthenticationResponse;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Component\ComponentHelper as JComponentHelper;
use Joomla\CMS\Date\Date as JDate;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Log\Log as JLog;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route as JRoute;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\CMS\Uri\Uri as JUri;
use Joomla\CMS\User\User as JUser;
use Joomla\CMS\User\UserHelper;
use Joomla\Event\Event;
use Joomla\Registry\Registry;
use RuntimeException;
use UnexpectedValueException;

/**
 * Helper class for managing Joomla! user login
 */
abstract class Login
{
	/**
	 * Handle the login callback from a social network. This is called after the plugin code has fetched the account
	 * information from the social network. At this point we are simply checking if we can log in the user, create a
	 * new user account or report a login error.
	 *
	 * @param   string               $slug             The slug of the social network plugin, e.g. 'facebook'.
	 * @param   PluginConfiguration  $config           The configuration information of the plugin.
	 * @param   UserData             $userData         The social network user account data.
	 * @param   array                $userProfileData  The data to save in the #__user_profiles table.
	 *
	 * @return  void
	 *
	 * @throws  LoginError      When a login error occurs (must report the login error to Joomla!).
	 * @throws  GenericMessage  When there is no login error but we need to report a message to the user, e.g. to tell
	 *                          them they need to click on the activation email.
	 * @throws  Exception
	 */
	public static function handleSocialLogin($slug, PluginConfiguration $config, UserData $userData, array $userProfileData)
	{
		Joomla::log($slug, 'Entering Social Login login handler (common code)');
		// Look for a local user account with the social network user ID _unless_ we are already logged in.
		$profileKeys = array_keys($userProfileData);
		$primaryKey  = $profileKeys[0];
		$currentUser = Joomla::getUser();
		$userId      = $currentUser->id;

		if ($currentUser->guest)
		{
			Joomla::log($slug, sprintf('Guest (not logged in) user detected. Trying to find a user using the %s unique ID %s', $slug, $userData->id));
			$userId = Integrations::getUserIdByProfileData('sociallogin.' . $slug . '.' . $primaryKey, $userData->id);
		}

		// We need to extract this value since empty() cannot work on properties accessed with magic __get
		$email = $userData->email;

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
		if (empty($userId) && !empty($email))
		{
			Joomla::log($slug, 'Could not find a linked user. Performing email pre-checks before checking the plugin configuration.');

			$userId = self::getUserIdByEmail($email);

			/**
			 * The social network user is not verified. That's a possible security issue so let's pretend we can't find a match.
			 */
			if (!$userData->verified)
			{
				Joomla::log($slug, 'The social network user does not have a verified email. Because of that we will NOT check the plugin configuration (if we can log them in or create a new user).');
				throw new LoginError(Text::_('PLG_SOCIALLOGIN_' . $slug . '_ERROR_LOCAL_NOT_FOUND'));
			}

			/**
			 * This is a verified social network user and we found a user account with the same email address on our
			 * site. If "Allow social login to non-linked accounts" is disabled AND the userId is not null stop with an
			 * error. Otherwise, if "Create new users" is allowed we will be trying to create a user account with an
			 * email address which already exists, leading to failure with a message that makes no sense to the user.
			 */
			if (!$config->canLoginUnlinked && !empty($userId))
			{
				Joomla::log($slug, 'The social network user has a verified email which matches an existing user BUT "Allow social login to non-linked accounts" is disabled. As a result we need to complain about a user account conflict.');
				throw new LoginError(Text::_('PLG_SOCIALLOGIN_' . $slug . '_ERROR_LOCAL_USERNAME_CONFLICT'));
			}
		}

		// Try to subscribe a new user
		if (empty($userId))
		{
			Joomla::log($slug, sprintf('Neither a linked user account was found, nor an account belonging to email %s. Considering my options for creating a new user.', $email));

			$usersConfig           = self::getUsersParams();
			$allowUserRegistration = $usersConfig->get('allowUserRegistration');

			if (empty($email))
			{
				Joomla::log($slug, 'No email was sent by the social network. Cannot create a new user');

				throw new LoginError(Text::_('PLG_SOCIALLOGIN_' . $slug . '_ERROR_LOCAL_NOT_FOUND'));
			}

			if (!$config->canCreateNewUsers)
			{
				Joomla::log($slug, '"Create new user accounts" is set to No. Cannot create a new user');

				throw new LoginError(Text::_('PLG_SOCIALLOGIN_' . $slug . '_ERROR_LOCAL_NOT_FOUND'));
			}

			if (($allowUserRegistration == 0) && !$config->canCreateAlways)
			{
				Joomla::log($slug, 'Joomla user registration is disabled and "Ignore Joomla! setting for creating user accounts" is set to No. Cannot create a new user.');

				throw new LoginError(Text::_('PLG_SOCIALLOGIN_' . $slug . '_ERROR_LOCAL_NOT_FOUND'));
			}

			try
			{
				/**
				 * If the social network reports the user as verified and the "Bypass user validation for verified
				 * users" option is enabled in the plugin options we tell the helper to not send user
				 * verification emails, immediately activating the user.
				 */
				$bypassVerification = $userData->verified && $config->canBypassValidation;

				if ($bypassVerification)
				{
					Joomla::log($slug, 'The "Bypass user validation" option is enabled in the plugin. No user activation email will be sent. The user will be immediately activated.');
				}

				Joomla::log($slug, 'Creating user');
				$userId = self::createUser($email, $userData->name, $bypassVerification, $userData->timezone);
			}
			catch (UnexpectedValueException $e)
			{
				Joomla::log($slug, 'Whoops! A user with the same username of email address already exists. Cannot create new user.', Log::ERROR);

				throw new LoginError(Text::sprintf('PLG_SOCIALLOGIN_' . $slug . '_ERROR_CANNOT_CREATE', Text::_('PLG_SOCIALLOGIN_' . $slug . '_ERROR_LOCAL_USERNAME_CONFLICT')));
			}
			catch (RuntimeException $e)
			{
				Joomla::log($slug, 'Joomla reported a failure trying to create a new user record.', Log::ERROR);

				throw new LoginError(Text::sprintf('PLG_SOCIALLOGIN_' . $slug . '_ERROR_CANNOT_CREATE', $e->getMessage()));
			}

			// Does the account need user or administrator verification?
			if (in_array($userId, ['useractivate', 'adminactivate']))
			{
				Joomla::log($slug, 'The user account needs to be activated before it can be used. We will notify the user.');

				// Do NOT go through processLoginFailure. This is NOT a login failure.
				throw new GenericMessage(Text::_('PLG_SOCIALLOGIN_' . $slug . '_NOTICE_' . $userId));
			}
		}

		/**
		 * Catch still empty user ID. This means we cannot find any matching user for this social network account and we
		 * are not allowed to create new users. As a result we have to give up and tell the user we can't log them in.
		 */
		if (empty($userId))
		{
			Joomla::log($slug, "YOU SHOULD NOT BE HERE. We cannot find a linked user, we are not allowed to log in non-linked users and we are not allowed to create new users. We should have already failed. Yet here we are. FAIL IMMEDIATELY.", Log::CRITICAL);
			throw new LoginError(Text::_('PLG_SOCIALLOGIN_' . $slug . '_ERROR_LOCAL_NOT_FOUND'));
		}

		// Attach the social network link information to the user's profile
		try
		{
			Joomla::log($slug, "Linking the social network profile with the Joomla user profile", Log::INFO);

			Integrations::insertUserProfileData($userId, 'sociallogin.' . $slug, $userProfileData);
		}
		catch (Exception $e)
		{
			// Ignore database exceptions at this point
		}

		// Log in the user
		try
		{
			if ($currentUser->guest)
			{
				Joomla::log($slug, "Logging in the user", Log::INFO);

				self::loginUser($userId);
			}
		}
		catch (Exception $e)
		{
			Joomla::log($slug, "Login failure. Typically this means that a third party user or authentication plugin denied the login. This is not a bug.", Log::INFO);

			throw new LoginError($e->getMessage());
		}
	}

	/**
	 * Returns a (blank) Joomla! authentication response
	 *
	 * @return  AuthenticationResponse
	 */
	public static function getAuthenticationResponseObject()
	{
		// Force the class auto-loader to load the JAuthentication class
		class_exists(JAuthentication::class, true);

		return new AuthenticationResponse();
	}

	/**
	 * Have Joomla! process a login failure
	 *
	 * @param   AuthenticationResponse  $response    The Joomla! auth response object
	 * @param   AbstractApplication     $app         The application we are running in. Skip to auto-detect
	 *                                               (recommended).
	 * @param   string                  $logContext  Logging context (plugin name). Default: system.
	 *
	 * @return  bool
	 *
	 * @throws  Exception
	 */
	public static function processLoginFailure($response, $app = null, $logContext = 'system')
	{
		// Import the user plugin group.
		PluginHelper::importPlugin('user');

		if (!is_object($app))
		{
			$app = Factory::getApplication();
		}

		// Trigger onUserLoginFailure Event.
		Joomla::log($logContext, "Calling onUserLoginFailure plugin event");
		Joomla::runPlugins('onUserLoginFailure', [(array) $response], $app);

		// If status is success, any error will have been raised by the user plugin
		if (class_exists('Joomla\CMS\Authentication\Authentication'))
		{
			$expectedStatus = Authentication::STATUS_SUCCESS;
		}
		else
		{
			$expectedStatus = JAuthentication::STATUS_SUCCESS;
		}

		if ($response->status !== $expectedStatus)
		{
			Joomla::log($logContext, "The login failure has been logged in Joomla's error log");

			// Everything logged in the 'jerror' category ends up being enqueued in the application message queue.
			JLog::add($response->error_message, JLog::WARNING, 'jerror');
		}
		else
		{
			Joomla::log($logContext, "The login failure was caused by a third party user plugin but it did not return any further information. Good luck figuring this one out...", Log::WARNING);
		}

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
			$user = Joomla::getUser();
		}

		// Make sure there's a valid user
		if ($user->guest || empty ($user->id))
		{
			return false;
		}

		$db    = Joomla::getDbo();
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
			Joomla::log($slug, "Database error. Cannot write Joomla user profile information to link the user account with the social network account. Check your database. This is NOT a Social Login issue.", Log::ERROR);

			return false;
		}
	}

	/**
	 * Get the com_users options
	 *
	 * @return Registry
	 */
	protected static function getUsersParams()
	{
		if (class_exists('Joomla\\CMS\\Component\\ComponentHelper'))
		{
			return ComponentHelper::getParams('com_users');
		}

		return JComponentHelper::getParams('com_users');
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
		$db    = Joomla::getDbo();
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
	 * @throws  Exception                 If user registration fails
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
		if (UserHelper::getUserId($username))
		{
			$username = $email;

			// If that exists too throw an exception
			if (UserHelper::getUserId($username))
			{
				throw new UnexpectedValueException();
			}
		}

		$data = [
			'name'     => $name,
			'username' => $username,
			'email'    => $email,
		];

		// Save the timezone into the user parameters
		$userParams = [
			'timezone' => $timezone,
		];

		return self::register($data, $userParams, $emailVerified);
	}

	/**
	 * Logs in a user to the site, bypassing the authentication plugins.
	 *
	 * @param   int                  $userId  The user ID to log in
	 * @param   AbstractApplication  $app     The application we are running in. Skip to auto-detect (recommended).
	 *
	 * @throws  Exception
	 */
	private static function loginUser($userId, $app = null)
	{
		// Trick the class auto-loader into loading the necessary classes
		class_exists(JAuthentication::class, true);

		// Fake a successful login message
		if (!is_object($app))
		{
			$app = Factory::getApplication();
		}

		$isAdmin = $app->isClient('administrator');
		$user    = Joomla::getUser($userId);

		// Does the user account have a pending activation?
		if (!empty($user->activation))
		{
			throw new RuntimeException(Text::_('JGLOBAL_AUTH_ACCESS_DENIED'));
		}

		// Is the user account blocked?
		if ($user->block)
		{
			throw new RuntimeException(Text::_('JGLOBAL_AUTH_ACCESS_DENIED'));
		}

		if (class_exists('Joomla\CMS\Authentication\Authentication'))
		{
			$statusSuccess = Authentication::STATUS_SUCCESS;
		}
		else
		{
			$statusSuccess = JAuthentication::STATUS_SUCCESS;
		}

		$response                = self::getAuthenticationResponseObject();
		$response->status        = $statusSuccess;
		$response->username      = $user->username;
		$response->fullname      = $user->name;
		$response->error_message = '';
		$response->language      = $user->getParam('language');
		$response->type          = 'SocialLogin';

		if ($isAdmin)
		{
			$response->language = $user->getParam('admin_language');
		}

		/**
		 * Set up the login options.
		 *
		 * The 'remember' element forces the use of the Remember Me feature when logging in with social media, as the
		 * users would expect.
		 *
		 * The 'action' element is actually required by plg_user_joomla. It is the core ACL action the logged in user
		 * must be allowed for the login to succeed. Please note that front-end and back-end logins use a different
		 * action. This allows us to provide the social login button on both front- and back-end and be sure that if a
		 * used with no backend access tries to use it to log in Joomla! will just slap him with an error message about
		 * insufficient privileges - the same thing that'd happen if you tried to use your front-end only username and
		 * password in a back-end login form.
		 */
		$options = [
			'remember' => true,
			'action'   => 'core.login.site',
		];

		if (Factory::getApplication()->isClient('administrator'))
		{
			$options['action'] = 'core.login.admin';
		}

		// Run the user plugins. They CAN block login by returning boolean false and setting $response->error_message.
		PluginHelper::importPlugin('user');
		$results = Joomla::runPlugins('onUserLogin', [(array) $response, $options], $app);

		// If there is no boolean FALSE result from any plugin the login is successful.
		if (in_array(false, $results, true) == false)
		{
			// Set the user in the session, letting Joomla! know that we are logged in.
			Factory::getApplication()->getSession()->set('user', $user);

			// Trigger the onUserAfterLogin event
			$options['user']         = $user;
			$options['responseType'] = $response->type;

			// The user is successfully logged in. Run the after login events
			Joomla::runPlugins('onUserAfterLogin', [$options], $app);

			return;
		}

		// If we are here the plugins marked a login failure. Trigger the onUserLoginFailure Event.
		Joomla::runPlugins('onUserLoginFailure', [(array) $response], $app);

		// Log the failure
		JLog::add($response->error_message, JLog::WARNING, 'jerror');

		// Throw an exception to let the caller know that the login failed
		throw new RuntimeException($response->error_message);
	}

	/**
	 * Method to register a new user account. Based on UsersModelRegistration::register().
	 *
	 * @param   array                $data                The user data to save.
	 * @param   array                $userParams          User parameters to save with the user account
	 * @param   bool                 $skipUserActivation  Should I forcibly skip user activation?
	 * @param   AbstractApplication  $app                 The application we are running in. Skip to auto-detect
	 *                                                    (recommended).
	 *
	 * @return  mixed  The user id on success, 'useractivate' or 'adminactivate' if activation is required
	 *
	 * @throws  Exception
	 */
	private static function register(array $data, array $userParams = [], $skipUserActivation = false, $app = null)
	{
		if (!is_object($app))
		{
			$app = Factory::getApplication();
		}

		// Load com_users language files
		$lang = $app->getLanguage();
		$lang->load('com_users', JPATH_BASE . '/components/com_users', 'en-GB', false, false);
		$lang->load('com_users', JPATH_BASE, 'en-GB', false, false);
		$lang->load('com_users', JPATH_BASE . '/components/com_users', null, false, false);
		$lang->load('com_users', JPATH_BASE, null, false, false);

		$params = self::getUsersParams();

		$data = array_merge([
			'name'     => '',
			'username' => '',
			'password' => '',
			'email'    => '',
			'groups'   => [],
		], $data);

		// Initialise the table with JUser.
		$user = new JUser;

		// If no password was specified create a random one
		if (!isset($data['password']) || empty($data['password']))
		{
			$data['password'] = UserHelper::genRandomPassword(24);
		}

		// Convert the email to punycode if necessary
		$data['email'] = PunycodeHelper::emailToPunycode($data['email']);

		// Get the groups the user should be added to after registration.
		$data['groups'] = [$params->get('new_usertype', 2)];

		/**
		 * Get the dispatcher and load the users plugins.
		 *
		 * IMPORTANT: We cannot go through the JApplicationCms object directly since user plugins will set the error
		 * message on the dispatcher instead of throwing an exception. See the plugins/user/profile/profile.php plugin
		 * file's onContentPrepareData method to understand this questionable approach. Until Joomla! stops supporting
		 * legacy error handling we cannot switch to Joomla::runPlugins and a regular exceptions
		 * handler around it :(
		 */
		$eventName = 'onContentPrepareData';

		try
		{
			// Joomla! 4 method, using DispatcherInterface from the Events package
			$appDispatcher = $app->getDispatcher();
			$dispatcher    = clone $appDispatcher;

			PluginHelper::importPlugin('user');

			$event       = new Event($eventName, ['com_users.registration', $data]);
			$eventReturn = $dispatcher->dispatch($eventName, $event);
			$results     = !isset($eventReturn['result']) || is_null($eventReturn['result']) ? [] : $eventReturn['result'];
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
			if (class_exists('Joomla\\CMS\\Application\\ApplicationHelper'))
			{
				$data['activation'] = ApplicationHelper::getHash(UserHelper::genRandomPassword(32));
			}
			else
			{
				$data['activation'] = JApplicationHelper::getHash(UserHelper::genRandomPassword(32));
			}

			$data['block'] = 1;
		}

		// Set the user parameters
		$data['params'] = $userParams;

		// Bind the data.
		if (!$user->bind($data))
		{
			throw new RuntimeException(Text::sprintf('COM_USERS_REGISTRATION_BIND_FAILED', $user->getError()));
		}

		// Load the users plugin group.
		PluginHelper::importPlugin('user');

		// Store the data.
		if (!$user->save())
		{
			throw new RuntimeException(Text::sprintf('COM_USERS_REGISTRATION_SAVE_FAILED', $user->getError()));
		}

		$app   = Factory::getApplication();
		$db    = Joomla::getDbo();
		$query = $db->getQuery(true);

		// Compile the notification mail values.
		$data             = $user->getProperties();
		$data['fromname'] = $app->get('fromname');
		$data['mailfrom'] = $app->get('mailfrom');
		$data['sitename'] = $app->get('sitename');
		$data['siteurl']  = JUri::root();

		// Handle account activation/confirmation emails.
		$isAdmin = $app->isClient('administrator');

		switch ($userActivation)
		{
			// Self-activation of user account
			default:
			case 2:
				// Set the link to confirm the user email.
				$uri              = JUri::getInstance();
				$base             = $uri->toString(['scheme', 'user', 'pass', 'host', 'port']);
				$data['activate'] = $base . JRoute::_('index.php?option=com_users&task=registration.activate&token=' . $data['activation'], false);

				// Remove administrator/ from activate url in case this method is called from admin
				if ($isAdmin)
				{
					$adminPos         = strrpos($data['activate'], 'administrator/');
					$data['activate'] = substr_replace($data['activate'], '', $adminPos, 14);
				}

				$emailSubject = Text::sprintf(
					'COM_USERS_EMAIL_ACCOUNT_DETAILS',
					$data['name'],
					$data['sitename']
				);

				$emailBody = Text::sprintf(
					'COM_USERS_EMAIL_REGISTERED_WITH_ADMIN_ACTIVATION_BODY_NOPW',
					$data['name'],
					$data['sitename'],
					$data['activate'],
					$data['siteurl'],
					$data['username']
				);

				if ($sendPassword)
				{
					$emailBody = Text::sprintf(
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
				$base             = $uri->toString(['scheme', 'user', 'pass', 'host', 'port']);
				$data['activate'] = $base . JRoute::_('index.php?option=com_users&task=registration.activate&token=' . $data['activation'], false);

				// Remove administrator/ from activate url in case this method is called from admin
				if ($isAdmin)
				{
					$adminPos         = strrpos($data['activate'], 'administrator/');
					$data['activate'] = substr_replace($data['activate'], '', $adminPos, 14);
				}

				$emailSubject = Text::sprintf(
					'COM_USERS_EMAIL_ACCOUNT_DETAILS',
					$data['name'],
					$data['sitename']
				);

				$emailBody = Text::sprintf(
					'COM_USERS_EMAIL_REGISTERED_WITH_ACTIVATION_BODY_NOPW',
					$data['name'],
					$data['sitename'],
					$data['activate'],
					$data['siteurl'],
					$data['username']
				);

				if ($sendPassword)
				{
					$emailBody = Text::sprintf(
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
				$emailSubject = Text::sprintf(
					'COM_USERS_EMAIL_ACCOUNT_DETAILS',
					$data['name'],
					$data['sitename']
				);

				$emailBody = Text::sprintf(
					'COM_USERS_EMAIL_REGISTERED_BODY_NOPW',
					$data['name'],
					$data['sitename'],
					$data['siteurl']
				);

				if ($sendPassword)
				{
					$emailBody = Text::sprintf(
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
		$return = Factory::getMailer()
			->sendMail($data['mailfrom'], $data['fromname'], $data['email'], $emailSubject, $emailBody);

		// Send Notification mail to administrators
		if (($userActivation < 2) && ($sendEmailToAdmin == 1))
		{
			$emailSubject = Text::sprintf(
				'COM_USERS_EMAIL_ACCOUNT_DETAILS',
				$data['name'],
				$data['sitename']
			);

			$emailBodyAdmin = Text::sprintf(
				'COM_USERS_EMAIL_REGISTERED_NOTIFICATION_TO_ADMIN_BODY',
				$data['name'],
				$data['username'],
				$data['siteurl']
			);

			// Get all admin users
			$query->clear()
				->select($db->quoteName(['name', 'email', 'sendEmail']))
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
				throw new RuntimeException(Text::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 500);
			}

			// Send mail to all Super Users
			foreach ($rows as $row)
			{
				$return = Factory::getMailer()
					->sendMail($data['mailfrom'], $data['fromname'], $row->email, $emailSubject, $emailBodyAdmin);

				// Check for an error.
				if ($return !== true)
				{
					throw new RuntimeException(Text::_('COM_USERS_REGISTRATION_ACTIVATION_NOTIFY_SEND_MAIL_FAILED'));
				}
			}
		}

		// Check for an error.
		if ($return !== true)
		{
			// Send a system message to administrators receiving system mails
			$db = Joomla::getDbo();
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
				throw new RuntimeException(Text::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 500);
			}

			if ((is_array($userids) || $userids instanceof \Countable ? count($userids) : 0) > 0)
			{
				$jdate = new JDate;

				// Build the query to add the messages
				foreach ($userids as $userid)
				{
					$values = [
						$db->quote($userid),
						$db->quote($userid),
						$db->quote($jdate->toSql()),
						$db->quote(Text::_('COM_USERS_MAIL_SEND_FAILURE_SUBJECT')),
						$db->quote(Text::sprintf('COM_USERS_MAIL_SEND_FAILURE_BODY', $return, $data['username'])),
					];
					$query->clear()
						->insert($db->quoteName('#__messages'))
						->columns($db->quoteName([
							'user_id_from',
							'user_id_to',
							'date_time',
							'subject',
							'message',
						]))
						->values(implode(',', $values));
					$db->setQuery($query);

					try
					{
						$db->execute();
					}
					catch (RuntimeException $e)
					{
						throw new RuntimeException(Text::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 500);
					}
				}
			}

			throw new RuntimeException(Text::_('COM_USERS_REGISTRATION_SEND_MAIL_FAILED'));
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
