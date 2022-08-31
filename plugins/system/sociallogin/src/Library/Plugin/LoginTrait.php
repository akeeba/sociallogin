<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\System\SocialLogin\Library\Plugin;

use Exception;
use Joomla\Application\AbstractApplication;
use Joomla\CMS\Authentication\Authentication;
use Joomla\CMS\Authentication\AuthenticationResponse;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\User\UserHelper;
use Joomla\Plugin\System\SocialLogin\Library\Data\PluginConfiguration;
use Joomla\Plugin\System\SocialLogin\Library\Data\UserData;
use Joomla\Plugin\System\SocialLogin\Library\Exception\Login\GenericMessage;
use Joomla\Plugin\System\SocialLogin\Library\Exception\Login\LoginError;
use RuntimeException;
use UnexpectedValueException;

trait LoginTrait
{
	/**
	 * Returns a (blank) Joomla! authentication response
	 *
	 * @return  AuthenticationResponse
	 */
	public function getAuthenticationResponseObject()
	{
		// Force the class autoloader to load the Authentication class
		class_exists(Authentication::class);

		return new AuthenticationResponse();
	}

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
	public function handleSocialLogin(string $slug, PluginConfiguration $config, UserData $userData, array $userProfileData): void
	{
		Log::add('Entering Social Login login handler (common code)', Log::DEBUG, 'sociallogin.' . $slug);

		// Look for a local user account with the social network user ID _unless_ we are already logged in.
		$profileKeys = array_keys($userProfileData);
		$primaryKey  = $profileKeys[0];
		$currentUser = $this->app->getIdentity();
		$userId      = $currentUser->id;

		if ($currentUser->guest)
		{
			Log::add(
				sprintf(
					'Guest (not logged in) user detected. Trying to find a user using the %s unique ID %s',
					$slug,
					$userData->id
				),
				Log::DEBUG,
				'sociallogin.' . $slug
			);

			$userId = $this->getUserIdByProfileData('sociallogin.' . $slug . '.' . $primaryKey, $userData->id);
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
			Log::add(
				'Could not find a linked user. Performing email pre-checks before checking the plugin configuration.',
				Log::DEBUG,
				'sociallogin.' . $slug
			);

			$userId = $this->getUserIdByEmail($email);

			/**
			 * The social network user is not verified. That's a possible security issue so let's pretend we can't find a match.
			 */
			if (!$userData->verified)
			{
				Log::add(
					'The social network user does not have a verified email. Because of that we will NOT check the plugin configuration (if we can log them in or create a new user).',
					Log::DEBUG,
					'sociallogin.' . $slug
				);

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
				Log::add(
					'The social network user has a verified email which matches an existing user BUT "Allow social login to non-linked accounts" is disabled. As a result we need to complain about a user account conflict.',
					Log::DEBUG,
					'sociallogin.' . $slug
				);

				throw new LoginError(Text::_('PLG_SOCIALLOGIN_' . $slug . '_ERROR_LOCAL_USERNAME_CONFLICT'));
			}
		}

		// Try to subscribe a new user
		if (empty($userId))
		{
			Log::add(
				sprintf(
					'Neither a linked user account was found, nor an account belonging to email %s. Considering my options for creating a new user.',
					$email
				),
				Log::DEBUG,
				'sociallogin.' . $slug
			);

			$usersConfig           = ComponentHelper::getParams('com_users');
			$allowUserRegistration = $usersConfig->get('allowUserRegistration');

			if (empty($email))
			{
				Log::add(
					'No email was sent by the social network. Cannot create a new user',
					Log::DEBUG,
					'sociallogin.' . $slug
				);

				throw new LoginError(Text::_('PLG_SOCIALLOGIN_' . $slug . '_ERROR_LOCAL_NOT_FOUND'));
			}

			if (!$config->canCreateNewUsers)
			{
				Log::add(
					'"Create new user accounts" is set to No. Cannot create a new user',
					Log::DEBUG,
					'sociallogin.' . $slug
				);

				throw new LoginError(Text::_('PLG_SOCIALLOGIN_' . $slug . '_ERROR_LOCAL_NOT_FOUND'));
			}

			if (($allowUserRegistration == 0) && !$config->canCreateAlways)
			{
				Log::add(
					'Joomla user registration is disabled and "Ignore Joomla! setting for creating user accounts" is set to No. Cannot create a new user.',
					Log::DEBUG,
					'sociallogin.' . $slug
				);

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
					Log::add(
						'The "Bypass user validation" option is enabled in the plugin. No user activation email will be sent. The user will be immediately activated.',
						Log::DEBUG,
						'sociallogin.' . $slug
					);
				}

				Log::add(
					'Creating user',
					Log::DEBUG,
					'sociallogin.' . $slug
				);

				$userId = $this->createUser($email, $userData->name, $bypassVerification, $userData->timezone);

				/**
				 * OK, here's the problem. The createUser() method proxies Joomla's user creation model. In its
				 * infinite wisdom, it does NOT always return the user ID. It returns the strings 'useractivate' and
				 * 'adminactivate' when activation is required. But I absolutely, definitely need the user ID to save
				 * the SocialLogin token.
				 *
				 * So, I have to do it the STUPID way: search the database for the user with the email address I just
				 * asked Joomla to use. Unless, of course, I got boolean FALSE in which case something else is broken
				 * and I have to let the code run to its failure handler point.
				 */
				if ($userId !== false)
				{
					$actualUserId = is_integer($userId) ? $userId : $this->getUserIdByEmail($email);

					if (!empty($actualUserId))
					{
						Log::add(
							sprintf(
								'Linking the social network profile with the newly created Joomla user profile (user ID %d)',
								$actualUserId
							),
							Log::INFO,
							'sociallogin.' . $slug
						);

						$this->insertUserProfileData($actualUserId, 'sociallogin.' . $slug, $userProfileData);
					}
					else
					{
						Log::add(
							sprintf(
								'I cannot find the user Joomla ostensibly has just created (email address %s). Crashing is imminent!',
								$email
							),
							Log::ERROR,
							'sociallogin.' . $slug
						);

					}
				}
			}
			catch (UnexpectedValueException $e)
			{
				Log::add(
					'Whoops! A user with the same username of email address already exists. Cannot create new user.',
					Log::ERROR,
					'sociallogin.' . $slug
				);

				throw new LoginError(Text::sprintf('PLG_SOCIALLOGIN_' . $slug . '_ERROR_CANNOT_CREATE', Text::_('PLG_SOCIALLOGIN_' . $slug . '_ERROR_LOCAL_USERNAME_CONFLICT')));
			}
			catch (RuntimeException $e)
			{
				Log::add(
					'Joomla reported a failure trying to create a new user record.',
					Log::ERROR,
					'sociallogin.' . $slug
				);

				throw new LoginError(Text::sprintf('PLG_SOCIALLOGIN_' . $slug . '_ERROR_CANNOT_CREATE', $e->getMessage()));
			}

			// Does the account need user or administrator verification?
			if (in_array($userId, ['useractivate', 'adminactivate']))
			{
				Log::add(
					'The user account needs to be activated before it can be used. We will notify the user.',
					Log::DEBUG,
					'sociallogin.' . $slug
				);

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
			Log::add(
				"YOU SHOULD NOT BE HERE. We cannot find a linked user, we are not allowed to log in non-linked users and we are not allowed to create new users. We should have already failed. Yet here we are. FAIL IMMEDIATELY.",
				Log::CRITICAL,
				'sociallogin.' . $slug
			);

			throw new LoginError(Text::_('PLG_SOCIALLOGIN_' . $slug . '_ERROR_LOCAL_NOT_FOUND'));
		}

		// Attach the social network link information to the user's profile
		try
		{
			Log::add(
				"Linking the social network profile with the Joomla user profile",
				Log::INFO,
				'sociallogin.' . $slug
			);

			$this->insertUserProfileData($userId, 'sociallogin.' . $slug, $userProfileData);
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
				Log::add(
					"Logging in the user",
					Log::INFO,
					'sociallogin.' . $slug
				);

				$this->loginUser($userId);
			}
		}
		catch (Exception $e)
		{
			Log::add(
				"Login failure. Typically this means that a third party user or authentication plugin denied the login. This is not a bug.",
				Log::INFO,
				'sociallogin.' . $slug
			);

			throw new LoginError($e->getMessage());
		}
	}

	/**
	 * Insert user profile data to the database table #__user_profiles, removing existing data by the same keys.
	 *
	 * @param   int     $userId  The user ID we are inserting data to
	 * @param   string  $slug    Common prefix for all keys, e.g. 'sociallogin.mysocialnetwork'
	 * @param   array   $data    The data to insert, e.g. ['socialNetworkUserID' => 1234, 'token' => 'foobarbazbat']
	 *
	 * @return  void
	 */
	public function insertUserProfileData($userId, $slug, array $data)
	{
		// Make sure we have a user
		$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);

		// Cannot link data to a guest user
		if ($user->guest || empty($user->id))
		{
			return;
		}

		$db = $this->db;

		/**
		 * The first item in $data is the unique key. No other user accounts can share it. For example, for the Facebook
		 * integration that's the Facebook User ID. The first thing we do is find other user accounts with the same
		 * primary key and remove the integration data for the same social network.
		 *
		 * Why does that matter? Let's give an example.
		 *
		 * I have registered on the site as bill@example.com but my Facebook account is using the address
		 * william@example.net. This means that trying to login without linking the accounts creates a new user account
		 * with my Facebook email address. But I don't want that! I want to use my Facebook account to log in to my
		 * regular user account (bill@example.com) on the site. I log out, log back into my regular account and try to
		 * link my Facebook account to rectify this issue.
		 *
		 * If this code below isn't present then the system doesn't delete the previous Facebook account link. Therefore
		 * we will end up with TWO user accounts linked to the same Facebook account. Trying to log in would pick the
		 * "first" one, where "first" is something decided by the database and most likely NOT what we want, leading to
		 * great confusion for the user.
		 */
		$keys         = array_keys($data);
		$primaryKey   = $keys[0];
		$primaryValue = $data[$primaryKey];
		$query        = $db->getQuery(true)
		                   ->select('user_id')
		                   ->from($db->qn('#__user_profiles'))
		                   ->where($db->qn('profile_key') . ' = ' . $db->q($slug . '.' . $primaryKey))
		                   ->where($db->qn('profile_value') . ' = ' . $db->q($primaryValue));

		// Get all user IDs matching the primary key's value
		try
		{
			$allUserIDs = $db->setQuery($query)->loadAssocList(null, 'user_id');

			$allUserIDs = empty($allUserIDs) ? [] : $allUserIDs;
		}
		catch (Exception $e)
		{
			$allUserIDs = [];
		}

		// Remove our own user's ID from the list
		if (!empty($allUserIDs))
		{
			$temp = [];

			foreach ($allUserIDs as $id)
			{
				if ($id == $userId)
				{
					continue;
				}

				$temp[] = $id;
			}

			$allUserIDs = $temp;
		}

		// Now add our own user's ID back. We need this to remove old integration values so we can INSERT the new ones.
		$allUserIDs[] = $userId;

		// Create database-escaped lists of user IDs and keys to remove
		$allUserIDs = array_map([$db, 'quote'], $allUserIDs);
		$keys       = array_map(function ($x) use ($slug, $db) {
			return $db->q($slug . '.' . $x);
		}, $keys);

		// Delete old values
		$query = $db->getQuery(true)
		            ->delete($db->qn('#__user_profiles'))
		            ->where($db->qn('user_id') . ' IN(' . implode(', ', $allUserIDs) . ')')
		            ->where($db->qn('profile_key') . ' IN(' . implode(', ', $keys) . ')');
		$db->setQuery($query)->execute();

		// Insert new values
		$insertData = [];

		foreach ($data as $key => $value)
		{
			$insertData[] = $db->q($userId) . ', ' . $db->q($slug . '.' . $key) . ', ' . $db->q($value);
		}

		$query = $db->getQuery(true)
		            ->insert($db->qn('#__user_profiles'))
		            ->columns($db->qn('user_id') . ', ' . $db->qn('profile_key') . ', ' . $db->qn('profile_value'))
		            ->values($insertData);
		$db->setQuery($query)->execute();
	}

	/**
	 * Is the user linked to the social login account?
	 *
	 * @param   string     $slug  The slug of the social media integration plugin, e.g. 'facebook'
	 * @param   User|null  $user  The user account we are checking
	 *
	 * @return  bool
	 */
	public function isLinkedUser(string $slug, ?User $user = null): bool
	{
		// Make sure there's a user to check for
		if (empty($user) || !is_object($user) || !($user instanceof User))
		{
			$user = $this->app->getIdentity();
		}

		// Make sure there's a valid user
		if ($user->guest || empty ($user->id))
		{
			return false;
		}

		$db    = $this->db;
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
			Log::add(
				"Database error. Cannot write Joomla user profile information to link the user account with the social network account. Check your database. This is NOT a Social Login issue.",
				Log::ERROR,
				'sociallogin.' . $slug
			);

			return false;
		}
	}

	/**
	 * Have Joomla! process a login failure
	 *
	 * @param   AuthenticationResponse  $response    The Joomla! auth response object
	 * @param   string                  $logContext  Logging context (plugin name). Default: system.
	 *
	 * @return  bool
	 *
	 * @throws Exception
	 */
	public function processLoginFailure(AuthenticationResponse $response, string $logContext = 'system')
	{
		// Import the user plugin group.
		PluginHelper::importPlugin('user');

		// Trigger onUserLoginFailure Event.
		Log::add(
			"Calling onUserLoginFailure plugin event",
			Log::DEBUG,
			'sociallogin.' . $logContext
		);

		$this->runPlugins('onUserLoginFailure', [(array) $response], $this->app);

		// If status is success, any error will have been raised by the user plugin
		$expectedStatus = Authentication::STATUS_SUCCESS;

		if ($response->status !== $expectedStatus)
		{
			Log::add(
				"The login failure has been logged in Joomla's error log",
				Log::DEBUG,
				'sociallogin.' . $logContext
			);

			// Everything logged in the 'jerror' category ends up being enqueued in the application message queue.
			Log::add($response->error_message, Log::WARNING, 'jerror');

			return false;
		}

		Log::add(
			"The login failure was caused by a third party user plugin but it did not return any further information. Good luck figuring this one out...",
			Log::WARNING,
			'sociallogin.' . $logContext
		);

		return false;
	}

	/**
	 * Get the user ID which matches the given profile data. Namely, the #__user_profiles table must have an entry for
	 * that user where profile_key == $profileKey and profile_value == $profileValue.
	 *
	 * @param   string  $profileKey    The key in the user profiles table to look up
	 * @param   string  $profileValue  The value in the user profiles table to look for
	 *
	 * @return  int  The user ID or 0 if no matching user is found / the user found no longer exists in the system.
	 */
	protected function getUserIdByProfileData(string $profileKey, string $profileValue): int
	{
		$db    = $this->db;
		$query = $db->getQuery(true)
		            ->select([
			            $db->qn('user_id'),
		            ])->from($db->qn('#__user_profiles'))
		            ->where($db->qn('profile_key') . ' = ' . $db->q($profileKey))
		            ->where($db->qn('profile_value') . ' = ' . $db->q($profileValue));

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
			 * if the user *really* exists. However we can't just go through Factory::getUser() because if the user
			 * does not exist we'll end up with an ugly Warning on our page with a text similar to "JUser: :_load:
			 * Unable to load user with ID: 1234". This cannot be disabled so we have to be, um, a bit creative :/
			 */
			$query      = $db->getQuery(true)
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
	private function createUser($email, $name, $emailVerified, $timezone)
	{
		// Does the email already exist?
		if (empty($email) || $this->getUserIdByEmail($email))
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
			'email1'   => $email,
		];

		// Save the timezone into the user parameters
		$userParams = [
			'timezone' => $timezone,
		];

		return $this->register($data, $userParams, $emailVerified);
	}

	/**
	 * Returns the user ID, if a user exists, given an email address.
	 *
	 * @param   string  $email  The email to search on.
	 *
	 * @return  integer  The user id or 0 if not found.
	 */
	private function getUserIdByEmail($email)
	{
		// Initialise some variables
		$db    = $this->db;
		$query = $db->getQuery(true)
		            ->select($db->qn('id'))
		            ->from($db->qn('#__users'))
		            ->where($db->qn('email') . ' = ' . $db->q($email));
		$db->setQuery($query, 0, 1);

		return $db->loadResult();
	}

	/**
	 * Logs in a user to the site, bypassing the authentication plugins.
	 *
	 * @param   int                  $userId  The user ID to log in
	 * @param   AbstractApplication  $app     The application we are running in. Skip to auto-detect (recommended).
	 *
	 * @throws  Exception
	 */
	private function loginUser($userId)
	{
		// Trick the class autoloader into loading the necessary classes
		class_exists(Authentication::class, true);

		// Fake a successful login message
		$isAdmin = $this->app->isClient('administrator');
		$user    = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);

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

		$statusSuccess           = Authentication::STATUS_SUCCESS;
		$response                = $this->getAuthenticationResponseObject();
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

		if ($this->app->isClient('administrator'))
		{
			$options['action'] = 'core.login.admin';
		}

		// Run the user plugins. They CAN block login by returning boolean false and setting $response->error_message.
		PluginHelper::importPlugin('user');
		$results = $this->runPlugins('onUserLogin', [(array) $response, $options], $this->app);

		// If there is no boolean FALSE result from any plugin the login is successful.
		if (!in_array(false, $results, true))
		{
			// Set the user in the session, letting Joomla! know that we are logged in.
			$this->app->getSession()->set('user', $user);

			// Trigger the onUserAfterLogin event
			$options['user']         = $user;
			$options['responseType'] = $response->type;

			// The user is successfully logged in. Run the after login events
			$this->runPlugins('onUserAfterLogin', [$options], $this->app);

			return;
		}

		// If we are here the plugins marked a login failure. Trigger the onUserLoginFailure Event.
		$this->runPlugins('onUserLoginFailure', [(array) $response], $this->app);

		// Log the failure
		Log::add($response->error_message, Log::WARNING, 'jerror');

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
	private function register(array $data, array $userParams = [], $skipUserActivation = false)
	{
		$cParams = ComponentHelper::getParams('com_users');

		$data = array_merge([
			'name'      => '',
			'username'  => '',
			'password1' => UserHelper::genRandomPassword(24),
			'email1'    => '',
			'groups'    => [$cParams->get('new_usertype', 2)],
			'params'    => $userParams,
		], $data);

		$data['email2']    = $data['email2'] ?? $data['email1'];
		$data['password2'] = $data['password2'] ?? $data['password1'];

		$userActivation = $cParams->get('useractivation', 0);

		if ($skipUserActivation)
		{
			$cParams->set('useractivation', 0);
		}

		$this->app->getLanguage()->load('com_users');

		/** @var \Joomla\Component\Users\Site\Model\RegistrationModel $registrationModel */
		$registrationModel = $this->app->bootComponent('com_users')->getMVCFactory()
		                               ->createModel('Registration', 'Site', ['ignore_request' => true]);

		$return = $registrationModel->register($data);

		$cParams->set('useractivation', $userActivation);

		return $return;
	}

}