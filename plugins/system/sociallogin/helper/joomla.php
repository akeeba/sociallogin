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

			self::$isAdmin = method_exists($app, 'isClient') ? $app->isClient('administrator') : $app->isAdmin();
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
	public static function renderLayout($layoutFile, $displayData = null, $includePath = '', $options = null)
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
	 * Execute a plugin event and return the results
	 *
	 * @param   string            $event  The plugin event to trigger.
	 * @param   array             $data   The data to pass to the event handlers.
	 * @param   JApplicationBase  $app    The application to run plugins against, default the currently loaded application.
	 *
	 * @return  array  The plugin responses
	 */
	public static function runPlugins($event, $data, JApplicationBase $app = null)
	{
		if (!is_object($app))
		{
			$app = \JFactory::getApplication();
		}

		if (method_exists($app, 'triggerEvent'))
		{
			return $app->triggerEvent($event, $data);
		}

		if (class_exists('JEventDispatcher'))
		{
			return \JEventDispatcher::getInstance()->trigger($event, $data);
		}

		throw new RuntimeException('Cannot run plugins');
	}

	/**
	 * Tells Joomla! to load a plugin group.
	 *
	 * This is just a wrapper around JPluginHelper. We use our own helper method for future-proofing...
	 *
	 * @param   string  $group  The plugin group to import
	 *
	 * @return  void
	 */
	public static function importPlugins($group)
	{
		JPluginHelper::importPlugin($group);
	}
}