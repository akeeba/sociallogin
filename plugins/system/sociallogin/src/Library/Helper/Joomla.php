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
use Joomla\CMS\Application\EventAware;
use Joomla\CMS\Event\GenericEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Log\Log;
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
	protected static ?bool $isAdmin = null;

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
	public static function runPlugins(string $event, array $data, $app = null)
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
	 * @return DatabaseDriver
	 */
	public static function getDbo()
	{
		return Factory::getContainer()->get('DatabaseDriver') ?: Factory::getDbo();
	}
}
