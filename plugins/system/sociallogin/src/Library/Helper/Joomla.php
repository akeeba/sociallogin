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
use Joomla\CMS\Factory;
use RuntimeException;

/**
 * A helper class for abstracting core features in Joomla! 3.4 and later, including 4.x
 */
abstract class Joomla
{
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
}
