<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

use Joomla\Registry\Registry;

/**
 * Helper class for managing integrations
 */
abstract class SocialLoginHelperIntegrations
{
	/**
	 * Cached copy of the social login buttons' HTML
	 *
	 * @var   string
	 */
	private static $cachedSocialLoginButtons = null;

	/**
	 * Helper method to render a JLayout.
	 *
	 * @param   string  $layoutFile   Dot separated path to the layout file, relative to base path (plugins/system/sociallogin/layout)
	 * @param   object  $displayData  Object which properties are used inside the layout file to build displayed output
	 * @param   string  $includePath  Additional path holding layout files
	 * @param   mixed   $options      Optional custom options to load. Registry or array format. Set 'debug'=>true to output debug information.
	 *
	 * @return  string
	 */
	private static function renderLayout($layoutFile, $displayData = null, $includePath = '', $options = null)
	{
		$basePath = JPATH_SITE . '/plugins/system/sociallogin/layout';
		$layout = new JLayoutFile($layoutFile, $basePath, $options);

		if (!empty($includePath))
		{
			$layout->addIncludePath($includePath);
		}

		return $layout->render($displayData);
	}

	/**
	 * Gets the Social Login buttons for logging into the site (typically used in login modules)
	 *
	 * @param   string  $loginURL       The URL to return to upon successful login. Current URL if omitted.
	 * @param   string  $failureURL     The URL to return to on login error. It's set automatically to $loginURL if omitted.
	 * @param   string  $buttonLayout   JLayout for rendering a single login button
	 * @param   string  $buttonsLayout  JLayout for rendering all the login buttons
	 *
	 * @return  string  The rendered HTML of the login buttons
	 */
	public static function getSocialLoginButtons($loginURL = null, $failureURL = null, $buttonLayout = 'akeeba.sociallogin.button', $buttonsLayout  = 'akeeba.sociallogin.buttons')
	{
		if (is_null(self::$cachedSocialLoginButtons))
		{
			JPluginHelper::importPlugin('sociallogin');

			$buttonDefinitions = JFactory::$application->triggerEvent('onSocialLoginGetLoginButton', array(
				$loginURL,
				$failureURL
			));
			$buttonsHTML       = array();

			foreach ($buttonDefinitions as $buttonDefinition)
			{
				if (empty($buttonDefinition))
				{
					continue;
				}

				$includePath = JPATH_SITE . '/plugins/sociallogin/' . $buttonDefinition['slug'] . '/layout';

				// First try the plugin-specific layout
				$html = self::renderLayout("$buttonLayout.{$buttonDefinition['slug']}", $buttonDefinition, $includePath);

				if (empty($html))
				{
					$html          = self::renderLayout($buttonLayout, $buttonDefinition, $includePath);
				}

				$buttonsHTML[] = $html;
			}

			self::$cachedSocialLoginButtons = self::renderLayout($buttonsLayout, array('buttons' => $buttonsHTML));
		}

		return self::$cachedSocialLoginButtons;
	}

	/**
	 * Gets the Social Login buttons for linking and unlinking accounts (typically used in the My Account page).
	 *
	 * @param   JUser   $user           The Joomla! user object for which to get the buttons. Omit to use the currently logged in user.
	 * @param   string  $buttonLayout   JLayout for rendering a single login button
	 * @param   string  $buttonsLayout  JLayout for rendering all the login buttons
	 *
	 * @return  string  The rendered HTML of the login buttons
	 */
	public static function getSocialLinkButtons(JUser $user = null, $buttonLayout = 'akeeba.sociallogin.linkbutton', $buttonsLayout  = 'akeeba.sociallogin.linkbuttons')
	{
		if (is_null(self::$cachedSocialLoginButtons))
		{
			JPluginHelper::importPlugin('sociallogin');

			$buttonDefinitions = JFactory::$application->triggerEvent('onSocialLoginGetLinkButton', array($user));
			$buttonsHTML       = array();

			foreach ($buttonDefinitions as $buttonDefinition)
			{
				if (empty($buttonDefinition))
				{
					continue;
				}

				$includePath = JPATH_SITE . '/plugins/sociallogin/' . $buttonDefinition['slug'] . '/layout';

				// First try the plugin-specific layout
				$html = self::renderLayout("$buttonLayout.{$buttonDefinition['slug']}", $buttonDefinition, $includePath);

				if (empty($html))
				{
					$html          = self::renderLayout($buttonLayout, $buttonDefinition, $includePath);
				}

				$buttonsHTML[] = $html;
			}

			self::$cachedSocialLoginButtons = self::renderLayout($buttonsLayout, array('buttons' => $buttonsHTML));
		}

		return self::$cachedSocialLoginButtons;
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
	public static function insertUserProfileData($userId, $slug, array $data)
	{
		// Make sure we have a user
		$user = JFactory::getUser($userId);

		// Cannot link data to a guest user
		if ($user->guest || empty($user->id))
		{
			return;
		}

		$db = JFactory::getDbo();

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

			$allUserIDs = empty($allUserIDs) ? array() : $allUserIDs;
		}
		catch (Exception $e)
		{
			$allUserIDs = array();
		}

		// Remove our own user's ID from the list
		if (!empty($allUserIDs))
		{
			$temp = array();

			foreach ($allUserIDs as $id)
			{
				if ($id = $userId)
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
		$allUserIDs = array_map(array($db, 'quote'), $allUserIDs);
		$keys = array_map(function ($x) use ($slug, $db) {
			return $db->q($slug . '.' . $x);
		}, $keys);

		// Delete old values
		$query = $db->getQuery(true)
			->delete($db->qn('#__user_profiles'))
			->where($db->qn('user_id') . ' IN(' . implode(', ', $allUserIDs) . ')' )
			->where($db->qn('profile_key') . ' IN(' . implode(', ', $keys) . ')' );
		$db->setQuery($query)->execute();

		// Insert new values
		$insertData = array();

		foreach ($data as $key => $value)
		{
			$insertData[] = '(' . $db->q($userId) . ', ' . $db->q($slug . '.' . $key) . ', ' . $db->q($value) . ')';
		}

		$query = $db->getQuery(true)
			->insert($db->qn('#__user_profiles'))
			->columns('(' . $db->qn('user_id') . ', ' . $db->qn('profile_key') . ', ' . $db->qn('profile_value') . ')')
			->values($insertData);
		$db->setQuery($query)->execute();
	}

	/**
	 * Delete all data with a common key name from the user profile table #__user_profiles.
	 *
	 * @param   int     $userId  The user ID to remove data from
	 * @param   string  $slug    Common prefix for all keys, e.g. 'sociallogin.mysocialnetwork'
	 */
	public static function removeUserProfileData($userId, $slug)
	{
		// Make sure we have a user
		$user = JFactory::getUser($userId);

		// Cannot unlink data from a guest user
		if ($user->guest || empty($user->id))
		{
			return;
		}


		$db = JFactory::getDbo();

		$query = $db->getQuery(true)
		            ->delete($db->qn('#__user_profiles'))
		            ->where($db->qn('user_id') . ' = ' . $db->q($userId))
		            ->where($db->qn('profile_key') . ' LIKE ' . $db->q($slug . '.%'));
		$db->setQuery($query)->execute();

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
	public static function getUserIdByProfileData($profileKey, $profileValue)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
		            ->select(array(
			            $db->qn('user_id'),
		            ))->from($db->qn('#__user_profiles'))
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

	/**
	 * Handle the login callback from a social network. This is called after the plugin code has fetched the account
	 * information from the social network. At this point we are simply checking if we can log in the user, create a
	 * new user account or report a login error.
	 *
	 * @param   string  $socialNetworkSlug    The slug of the social network plugin, e.g. 'facebook', 'twitter',
	 *                                        'mycustomthingie'. Used to construct the names of the profile keys in the
	 *                                        #__user_profiles table.
	 * @param   SocialLoginPluginConfiguration  $pluginConfiguration  The configuration information of the plugin.
	 * @param   array   $userData             The social network user account data.
	 * @param   array   $userProfileData      The data to save in the #__user_profiles table.
	 *
	 * @return  void
	 *
	 * @throws  SocialLoginFailedLoginException     When a login error occurs (must report the login error to Joomla!).
	 * @throws  SocialLoginGenericMessageException  When there is no login error but we need to report a message to the
	 *                                              user, e.g. to tell them they need to click on the activation email.
	 */
	public static function handleSocialLogin($socialNetworkSlug, SocialLoginPluginConfiguration $pluginConfiguration, array $userData, array $userProfileData)
	{
		$fullName        = $userData['fullName'];
		$socialUserId    = $userData['socialUserId'];
		$socialUserEmail = $userData['socialUserEmail'];
		$socialVerified  = $userData['socialVerified'];
		$socialTimezone  = $userData['socialTimezone'];

		// Look for a local user account with the social network user ID
		$userId = SocialLoginHelperIntegrations::getUserIdByProfileData('sociallogin.' . $socialNetworkSlug . '.userid', $socialUserId);

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
			$userId = SocialLoginHelperLogin::getUserIdByEmail($socialUserEmail);

			/**
			 * The social network user is not verified. That's a possible security issue so let's pretend we can't find a match.
			 */
			if (!$socialVerified)
			{
				throw new SocialLoginFailedLoginException(JText::_('PLG_SOCIALLOGIN_' . $socialNetworkSlug . '_ERROR_LOCAL_NOT_FOUND'));
			}

			/**
			 * This is a verified social network user and we found a user account with the same email address on our
			 * site. If "Allow social login to non-linked accounts" is disabled AND the userId is not null stop with an
			 * error. Otherwise, if "Create new users" is allowed we will be trying to create a user account with an
			 * email address which already exists, leading to failure with a message that makes no sense to the user.
			 */
			if (!$pluginConfiguration->canLoginUnlinked && !empty($userId))
			{
				throw new SocialLoginFailedLoginException(JText::_('PLG_SOCIALLOGIN_' . $socialNetworkSlug . '_ERROR_LOCAL_USERNAME_CONFLICT'));
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

			if ((($allowUserRegistration == 0) && !$pluginConfiguration->canCreateAlways) || !$pluginConfiguration->canCreateNewUsers)
			{
				throw new SocialLoginFailedLoginException(JText::_('PLG_SOCIALLOGIN_' . $socialNetworkSlug . '_ERROR_LOCAL_NOT_FOUND'));
			}

			try
			{
				/**
				 * If the social network reports the user as verified and the "Bypass user validation for verified
				 * users" option is enabled in the plugin options we tell the helper to not send user
				 * verification emails, immediately activating the user.
				 */
				$bypassVerification = $socialVerified && $pluginConfiguration->canBypassValidation;
				$userId             = SocialLoginHelperLogin::createUser($socialUserEmail, $fullName, $bypassVerification, $socialTimezone);
			}
			catch (UnexpectedValueException $e)
			{
				throw new SocialLoginFailedLoginException(JText::sprintf('PLG_SOCIALLOGIN_' . $socialNetworkSlug . '_ERROR_CANNOT_CREATE', JText::_('PLG_SOCIALLOGIN_' . $socialNetworkSlug . '_ERROR_LOCAL_USERNAME_CONFLICT')));
			}
			catch (RuntimeException $e)
			{
				throw new SocialLoginFailedLoginException(JText::sprintf('PLG_SOCIALLOGIN_' . $socialNetworkSlug . '_ERROR_CANNOT_CREATE', $e->getMessage()));
			}

			// Does the account need user or administrator verification?
			if (in_array($userId, array('useractivate', 'adminactivate')))
			{
				// Do NOT go through processLoginFailure. This is NOT a login failure.
				throw new SocialLoginGenericMessageException(JText::_('PLG_SOCIALLOGIN_' . $socialNetworkSlug . '_NOTICE_' . $userId));
			}
		}

		/**
		 * Catch still empty user ID. This means we cannot find any matching user for this social network account and we
		 * are not allowed to create new users. As a result we have to give up and tell the user we can't log them in.
		 */
		if (empty($userId))
		{
			throw new SocialLoginFailedLoginException(JText::_('PLG_SOCIALLOGIN_' . $socialNetworkSlug . '_ERROR_LOCAL_NOT_FOUND'));
		}

		// Attach the social network link information to the user's profile
		try
		{
			SocialLoginHelperIntegrations::insertUserProfileData($userId, 'sociallogin.' . $socialNetworkSlug, $userProfileData);
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

}

/**
 * Exception thrown when a login error occurs. The application must go through the failed login user plugin handlers.
 */
class SocialLoginFailedLoginException extends RuntimeException {}

/**
 * Exception thrown when a generic error occurs. The application must redirect to the error page WITHOUT going through
 * the login failure handlers of the user plugins.
 */
class SocialLoginGenericMessageException extends RuntimeException {}

/**
 * Configuration parameters of a social media integration plugin, used during login.
 *
 * @property   bool  $canLoginUnlinked     Should I log in users who have not yet linked their social network account to
 *                                         their site account?
 * @property   bool  $canCreateNewUsers    Can I use this integration to create new user accounts?
 * @property   bool  $canCreateAlways      Allow the plugin to override Joomla's new user account registration flag?
 * @property   bool  $canBypassValidation  Am I allowed to bypass user verification if the social network reports the
 *                                         user verified on their end?
 */
final class SocialLoginPluginConfiguration
{
	/**
	 * Should I log in users who have not yet linked their social network account to their site account? THIS MAY BE
	 * DANGEROUS (impersonation risk), therefore it is disabled by default.
	 *
	 * @var   bool
	 */
	private $canLoginUnlinked = false;

	/**
	 * Can I use this integration to create new user accounts? This will happen when someone tries to login through
	 * the social network but their social network account is not linked to a user account yet.
	 *
	 * @var   bool
	 */
	private $canCreateNewUsers = false;

	/**
	 * Allow the plugin to override Joomla's new user account registration flag. This is useful to prevent new user
	 * accounts from being created _unless_ they have a social media account and use it on your site (force new users to
	 * link their social media accounts).
	 *
	 * @var   bool
	 */
	private $canCreateAlways = false;

	/**
	 * When creating new users, am I allowed to bypass email verification if the social network reports the user as
	 * verified on their end?
	 *
	 * @var   bool
	 */
	private $canBypassValidation = true;

	/**
	 * Magic getter. Returns the stored, sanitized property values.
	 *
	 * @param   string  $name  The name of the property to read.
	 *
	 * @return  mixed
	 */
	function __get($name)
	{
		switch ($name)
		{
			case 'canLoginUnlinked':
			case 'canCreateNewUsers':
			case 'canCreateAlways':
			case 'canByPassValidation':
				return $this->{$name};
				break;

			default:
				return null;
		}
	}

	/**
	 * Magic setter. Stores a sanitized property value.
	 *
	 * @param   string  $name   The name of the property to set
	 * @param   mixed   $value  The value to set the property to
	 *
	 * @return  void
	 */
	function __set($name, $value)
	{
		switch ($name)
		{
			case 'canLoginUnlinked':
			case 'canCreateNewUsers':
			case 'canCreateAlways':
			case 'canByPassValidation':
				$this->{$name} = (bool) $value;
				break;
		}

	}


}