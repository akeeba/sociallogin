<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\System\SocialLogin\Library\Helper;

// Protect from unauthorized access
defined('_JEXEC') || die();

use Exception;
use Joomla\Application\AbstractApplication;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Log\Log;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseDriver;
use RuntimeException;

/**
 * A helper class for abstracting core features in Joomla! 3.4 and later, including 4.x
 */
abstract class Joomla
{
	/**
	 * Are we inside the administrator application
	 *
	 * @var   bool
	 */
	protected static $isAdmin = null;

	/**
	 * Which plugins have already registered a text file logger. Prevents double registration of a log file.
	 *
	 * @var   array
	 * @since 2.1.0
	 */
	protected static $registeredLoggers = [];

	/**
	 * Is the current user allowed to edit the social login configuration of $user? To do so I must either be editing my
	 * own account OR I have to be a Super User.
	 *
	 * @param   User  $user  The user you want to know if we're allowed to edit
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
	 * @param   string  $layoutFile   Dot separated path to the layout file, relative to base path
	 *                                (plugins/system/sociallogin/layout)
	 * @param   object  $displayData  Object which properties are used inside the layout file to build displayed output
	 * @param   string  $includePath  Additional path holding layout files
	 * @param   mixed   $options      Optional custom options to load. Registry or array format. Set 'debug'=>true to
	 *                                output debug information.
	 *
	 * @return  string
	 */
	public static function renderLayout($layoutFile, $displayData = null, $includePath = '', $options = null)
	{
		$basePath = JPATH_PLUGINS . '/system/sociallogin/layout';
		$layout   = new FileLayout($layoutFile, null, $options);

		if (!empty($includePath))
		{
			$layout->addIncludePath($includePath);
		}

		$result = $layout->render($displayData);

		if (empty($result))
		{
			$layout = new FileLayout($layoutFile, $basePath, $options);

			if (!empty($includePath))
			{
				$layout->addIncludePath($includePath);
			}

			$result = $layout->render($displayData);
		}

		return $result;
	}

	/**
	 * Execute a plugin event and return the results
	 *
	 * @param   string               $event  The plugin event to trigger.
	 * @param   array                $data   The data to pass to the event handlers.
	 * @param   AbstractApplication  $app    The application to run plugins against,
	 *                                       default the currently loaded application.
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
			$app = Factory::getApplication();
		}

		if (method_exists($app, 'triggerEvent'))
		{
			return $app->triggerEvent($event, $data);
		}

		throw new RuntimeException('Cannot run plugins');
	}

	/**
	 * Returns the user, delegates to JFactory/Factory.
	 *
	 * @param   int|null  $id  The ID of the Joomla! user to load, default null (currently logged in user)
	 *
	 * @return  User
	 */
	public static function getUser($id = null)
	{
		$userFactory = Factory::getContainer()->get(UserFactoryInterface::class);

		if (is_null($id))
		{
			$app = Factory::getApplication();

			return $app->getIdentity() ?: $app->getSession()->get('user') ?: $userFactory->loadUserById(0);
		}

		return $userFactory->loadUserById($id);
	}

	/**
	 * Is the variable an CMS application object?
	 *
	 * @param   mixed  $app
	 *
	 * @return  bool
	 */
	public static function isCmsApplication($app)
	{
		if (!is_object($app))
		{
			return false;
		}

		return $app instanceof CMSApplication;
	}

	/**
	 * @return DatabaseDriver
	 */
	public static function getDbo()
	{
		return Factory::getContainer()->get('DatabaseDriver') ?: Factory::getDbo();
	}

	/**
	 * Writes a log message to the debug log
	 *
	 * @param   string  $plugin    The Social Login plugin which generated this log message
	 * @param   string  $message   The message to write to the log
	 * @param   int     $priority  Log message priority, default is Log::DEBUG
	 *
	 * @return  void
	 *
	 * @since   2.1.0
	 */
	public static function log($plugin, $message, $priority = Log::DEBUG)
	{
		Log::add($message, $priority, 'sociallogin.' . $plugin);
	}

	/**
	 * Register a debug log file writer for a Social Login plugin.
	 *
	 * @param   string  $plugin  The Social Login plugin for which to register a debug log file writer
	 *
	 * @return  void
	 *
	 * @since   2.1.0
	 */
	public static function addLogger($plugin)
	{
		// Make sure this logger is not already registered
		if (in_array($plugin, self::$registeredLoggers))
		{
			return;
		}

		self::$registeredLoggers[] = $plugin;

		// We only log errors unless Site Debug is enabled
		$logLevels = Log::ERROR | Log::CRITICAL | Log::ALERT | Log::EMERGENCY;

		if (defined('JDEBUG') && JDEBUG)
		{
			$logLevels = Log::ALL;
		}

		// Add a formatted text logger
		Log::addLogger([
			'text_file'         => "sociallogin_{$plugin}.php",
			'text_entry_format' => '{DATETIME}	{PRIORITY} {CLIENTIP}	{MESSAGE}',
		], $logLevels, [
			"sociallogin.{$plugin}",
		]);
	}
}
