<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();


class SocialLoginHelperJoomla
{
	/**
	 * Are we inside the administrator application
	 *
	 * @var   bool
	 */
	protected static $isAdmin = null;

	/**
	 * Are we inside an administrator page?
	 *
	 * @param   JApplicationCms  $app  The current CMS application which tells us if we are inside an admin page
	 *
	 * @return  bool
	 */
	public static function isAdminPage(JApplicationCms $app = null)
	{
		if (is_null(self::$isAdmin))
		{
			if (is_null($app))
			{
				$app = JFactory::getApplication();
			}

			self::$isAdmin = version_compare(JVERSION, '3.7.0', 'ge') ? $app->isClient('administrator') : $app->isAdmin();
		}

		return self::$isAdmin;
	}

	/**
	 * Is the current user allowed to edit the social login configuration of $user? To do so I must either be editing my
	 * own account OR I have to be a Super User.
	 *
	 * @param   JUser  $user  The user you want to know if we're allowed to edit
	 *
	 * @return  bool
	 */
	public static function canEditUser(JUser $user = null)
	{
		// I can edit myself
		if (empty($user))
		{
			return true;
		}

		// Guests can't have social logins associated
		if ($user->guest)
		{
			return false;
		}

		// Get the currently logged in used
		$myUser = JFactory::getUser();

		// Same user? I can edit myself
		if ($myUser->id == $user->id)
		{
			return true;
		}

		// To edit a different user I must be a Super User myself. If I'm not, I can't edit another user!
		if (!$myUser->authorise('core.admin'))
		{
			return false;
		}

		// I am a Super User editing another user. That's allowed.
		return true;
	}


}