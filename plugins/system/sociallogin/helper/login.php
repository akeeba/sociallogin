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
	 * @return  JAuthenticationResponse
	 */
	public static function getAuthenticationResponseObject()
	{
		// Force the class auto-loader to load the JAuthentication class
		class_exists('JAuthentication', true);

		return new JAuthenticationResponse();
	}

	public static function processLoginFailure(JAuthenticationResponse $response)
	{
		// Import the user plugin group.
		JPluginHelper::importPlugin('user');

		$app = JFactory::getApplication();

		// Trigger onUserLoginFailure Event.
		$app->triggerEvent('onUserLoginFailure', array((array) $response));

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
	 * Returns userid if a user exists
	 *
	 * @param   string  $email  The email to search on.
	 *
	 * @return  integer  The user id or 0 if not found.
	 */
	public static function getUserIdByEmail($email)
	{
		// Initialise some variables
		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
		            ->select($db->quoteName('id'))
		            ->from($db->quoteName('#__users'))
		            ->where($db->quoteName('email') . ' = ' . $db->quote($email));
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
	 * @param   string  $timezone       The user's timezone, either as a GMT offset ("e.g. +2") or as a proper timezone (e.g. "Asia/Nicosia")
	 *
	 * @return  mixed  The user id on success, 'useractivate' or 'adminactivate' if activation is required
	 *
	 * @throws  UnexpectedValueException  If the email or username already exists
	 */
	public static function createUser($email, $name, $emailVerified, $timezone)
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

		// TODO Do something with the timezone?

		return self::register($data, $emailVerified);
	}

	/**
	 * Logs in a user to the site, bypassing the authentication plugins.
	 *
	 * @param   int  $userId  The user ID to log in
	 */
	public static function loginUser($userId)
	{
		// Trick the class auto-loader into loading the necessary classes
		JLoader::import('joomla.user.authentication');
		JLoader::import('joomla.plugin.helper');
		JLoader::import('joomla.user.helper');
		class_exists('JAuthentication', true);

		// Fake a successful login message
		$app     = JFactory::getApplication();
		$isAdmin = method_exists($app, 'isClient') ? $app->isClient('administrator') : $app->isAdmin();
		$user    = JFactory::getUser($userId);

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
		JPluginHelper::importPlugin('user');
		$results = self::runPlugins('onUserLogin', array((array) $response, $options));

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
			$app->triggerEvent('onUserAfterLogin', array($options));
		}

		// If we are here the plugins marked a login failure. Trigger the onUserLoginFailure Event.
		$app->triggerEvent('onUserLoginFailure', array((array) $response));

		// Log the failure
		JLog::add($response->error_message, JLog::WARNING, 'jerror');

		// Throw an exception to let the caller know that the login failed
		throw new RuntimeException($response->error_message);
	}

	/**
	 * Method to register a new user account. Based on UsersModelRegistration::register().
	 *
	 * @param   array  $data                The user data to save.
	 * @param   bool   $skipUserActivation  Should I forcibly skip user activation?
	 *
	 * @return  mixed  The user id on success, 'useractivate' or 'adminactivate' if activation is required
	 */
	private static function register(array $data, $skipUserActivation = false)
	{
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

		// Get the dispatcher and load the users plugins.
		$dispatcher = JEventDispatcher::getInstance();
		JPluginHelper::importPlugin('user');

		// Trigger the data preparation event.
		$results = $dispatcher->trigger('onContentPrepareData', array('com_users.registration', $data));

		// Check for errors encountered while preparing the data.
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

		// Bind the data.
		if (!$user->bind($data))
		{
			throw new RuntimeException(JText::sprintf('COM_USERS_REGISTRATION_BIND_FAILED', $user->getError()));
		}

		// Load the users plugin group.
		JPluginHelper::importPlugin('user');

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

	/**
	 * Execute a plugin event and return the results
	 *
	 * @param   string  $event  The plugin event to trigger
	 * @param   array   $data   The data to pass to the event handlers
	 *
	 * @return  array  The plugin responses
	 */
	private static function runPlugins($event, $data)
	{
		if (class_exists('JEventDispatcher'))
		{
			return \JEventDispatcher::getInstance()->trigger($event, $data);
		}

		return \JFactory::getApplication()->triggerEvent($event, $data);
	}

}