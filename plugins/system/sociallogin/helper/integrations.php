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

				// TODO First try layout "$buttonLayout.{$buttonDefinition['slug']}"
				$buttonsHTML[] = self::renderLayout($buttonLayout, $buttonDefinition, $includePath);
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

				// TODO First try layout "$buttonLayout.{$buttonDefinition['slug']}"
				$buttonsHTML[] = self::renderLayout($buttonLayout, $buttonDefinition, $includePath);
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
}