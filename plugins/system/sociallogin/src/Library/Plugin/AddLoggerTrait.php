<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\System\SocialLogin\Library\Plugin;

use Joomla\CMS\Log\Log;

trait AddLoggerTrait
{
	/**
	 * Which plugins have already registered a text file logger. Prevents double registration of a log file.
	 *
	 * @since 2.1.0
	 * @var   array
	 */
	protected static array $registeredLoggers = [];

	/**
	 * Register a debug log file writer for a Social Login plugin.
	 *
	 * @param   string  $plugin  The Social Login plugin for which to register a debug log file writer
	 *
	 * @return  void
	 *
	 * @since   2.1.0
	 */
	protected function addLogger($plugin)
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