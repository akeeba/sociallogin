<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\SocialLogin\Library\Helper;

// Protect from unauthorized access
use Exception;
use JApplicationBase;
use JApplicationCms;
use JFactory;
use JLayoutFile;
use Joomla\CMS\Application\BaseApplication;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\User;
use JPluginHelper;
use JUser;
use RuntimeException;

defined('_JEXEC') or die();

/**
 * A helper class for abstracting core features in Joomla! 3.4 and later, including 4.x
 */
class Joomla
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
	 * @param   \JApplicationCms|CMSApplication $app The current CMS application which tells us if we are inside an admin page
	 *
	 * @return  bool
	 *
	 * @throws  Exception
	 */
	public static function isAdminPage($app = null)
	{
		if (is_null(self::$isAdmin))
		{
			if (is_null($app))
			{
				$app = self::getApplication();
			}

			self::$isAdmin = method_exists($app, 'isClient') ? $app->isClient('administrator') : $app->isAdmin();
		}

		return self::$isAdmin;
	}

	/**
	 * Is the current user allowed to edit the social login configuration of $user? To do so I must either be editing my
	 * own account OR I have to be a Super User.
	 *
	 * @param   JUser|User $user The user you want to know if we're allowed to edit
	 *
	 * @return  bool
	 */
	public static function canEditUser($user = null)
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
		$myUser = self::getUser();

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
	 * @param   string $layoutFile  Dot separated path to the layout file, relative to base path (plugins/system/sociallogin/layout)
	 * @param   object $displayData Object which properties are used inside the layout file to build displayed output
	 * @param   string $includePath Additional path holding layout files
	 * @param   mixed  $options     Optional custom options to load. Registry or array format. Set 'debug'=>true to output debug information.
	 *
	 * @return  string
	 */
	public static function renderLayout($layoutFile, $displayData = null, $includePath = '', $options = null)
	{
		$basePath = JPATH_SITE . '/plugins/system/sociallogin/layout';
		$layout   = self::getJLayoutFromFile($layoutFile, $options, $basePath);

		if (!empty($includePath))
		{
			$layout->addIncludePath($includePath);
		}

		return $layout->render($displayData);
	}

	/**
	 * Execute a plugin event and return the results
	 *
	 * @param   string                           $event   The plugin event to trigger.
	 * @param   array                            $data    The data to pass to the event handlers.
	 * @param   JApplicationBase|BaseApplication $app     The application to run plugins against,
	 *                                                    default the currently loaded application.
	 *
	 * @return  array  The plugin responses
	 *
	 * @throws  RuntimeException  When we cannot run the plugins
	 * @throws  Exception         When we cannot create the application
	 */
	public static function runPlugins($event, $data, $app = null)
	{
		if (!is_object($app))
		{
			$app = self::getApplication();
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
	 * @param   string $group The plugin group to import
	 *
	 * @return  void
	 */
	public static function importPlugins($group)
	{
		if (class_exists('Joomla\\CMS\\Plugin\\PluginHelper'))
		{
			PluginHelper::importPlugin($group);

			return;
		}

		JPluginHelper::importPlugin($group);
	}

	/**
	 * Get the CMS application object
	 *
	 * @return  JApplicationCms|CMSApplication
	 *
	 * @throws  Exception
	 */
	public static function getApplication()
	{
		if (class_exists('Joomla\\CMS\\Factory'))
		{
			return Factory::getApplication();
		}

		return JFactory::getApplication();
	}

	/**
	 * Returns the user, delegates to JFactory/Factory.
	 *
	 * @param   int|null $id The ID of the Joomla! user to load, default null (currently logged in user)
	 *
	 * @return  JUser|User
	 */
	public static function getUser($id = null)
	{
		if (class_exists('Joomla\\CMS\\Factory'))
		{
			return Factory::getUser($id);
		}

		return JFactory::getUser($id);
	}

	/**
	 * Return a Joomla! layout object, creating from a layout file
	 *
	 * @param   string  $layoutFile  Path to the layout file
	 * @param   array   $options     Options to the layout file
	 * @param   string  $basePath    Base path for the layout file
	 *
	 * @return JLayoutFile|FileLayout
	 */
	public static function getJLayoutFromFile($layoutFile, $options, $basePath)
	{
		if (class_exists('Joomla\\CMS\\Layout\\FileLayout'))
		{
			return new FileLayout($layoutFile, $basePath, $options);
		}

		return new JLayoutFile($layoutFile, $basePath, $options);
	}
}
