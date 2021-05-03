<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\System\SocialLogin\Features;

// Prevent direct access
defined('_JEXEC') || die;

use Exception;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\User\UserHelper;
use Joomla\Plugin\System\SocialLogin\Library\Helper\Integrations;
use Joomla\Plugin\System\SocialLogin\Library\Helper\Joomla;
use Joomla\Registry\Registry;

/**
 * Feature: Button injection in login modules and login pages
 *
 * @package Akeeba\SocialLogin\Features
 * @since   3.0.1
 */
trait ButtonInjection
{
	/**
	 * Have I already included the Joomla 4 button handler JavaScript?
	 *
	 * @var   bool
	 * @since 3.1.0
	 */
	private static $includedJ4ButtonHandlerJS = false;

	/**
	 * Creates additional login buttons
	 *
	 * @param   string  $form  The HTML ID of the form we are enclosed in
	 *
	 * @return  array
	 *
	 * @throws  Exception
	 *
	 * @see     AuthenticationHelper::getLoginButtons()
	 *
	 * @since   3.1.0
	 */
	public function onUserLoginButtons(string $form): array
	{
		if (!$this->enabled)
		{
			return [];
		}

		// Append the social login buttons content
		Joomla::log('system', "Injecting buttons using the Joomla 4 way.");

		$this->includeJ4ButtonHandler();

		$returnUrl = $this->getReturnURLFromBackTrace();

		return array_map(function (array $def) {
			$randomId = sprintf("plg_system_sociallogin-%s-%s-%s",
				$def['slug'], UserHelper::genRandomPassword(12), UserHelper::genRandomPassword(8));

			$imageKey     = 'image';
			$imageContent = $def['img'];

			if (substr($def['rawimage'], -4) === '.svg')
			{
				$imageKey     = 'svg';
				$imageContent = file_get_contents(JPATH_ROOT . HTMLHelper::_('image', $def['rawimage'], $def['label'], null, true, true));
			}


			return [
				'label'          => $def['label'],
				$imageKey        => $imageContent,
				'class'          => sprintf('akeeba-sociallogin-link-button-j4 akeeba-sociallogin-link-button-j4-%s akeeba-sociallogin-link-button-%1$s', $def['slug']),
				'id'             => $randomId,
				'data-socialurl' => $def['link'],
			];
		}, Integrations::getSocialLoginButtonDefinitions(null, $returnUrl));
	}

	/**
	 * Extracts the login return URL from the execution backtrace.
	 *
	 * This method currently extracts the return URL from mod_login and com_users.
	 *
	 * @return  string|null  The return URL. NULL if none can be found.
	 */
	private function getReturnURLFromBackTrace(): ?string
	{
		if (!function_exists('debug_backtrace'))
		{
			return null;
		}

		foreach (debug_backtrace(0) as $item)
		{
			$function = $item['function'] ?? '';
			$class    = $item['class'] ?? '';
			$args     = $item['args'] ?? [];

			// Extract from module definition.
			if (($function === 'renderRawModule') && ($class === 'Joomla\CMS\Helper\ModuleHelper'))
			{
				$module = $args[0] ?? null;
				$params = $args[1] ?? null;

				if (!is_object($module) || empty($module))
				{
					continue;
				}

				if (!is_object($params) || !($module instanceof Registry))
				{
					$params = new Registry($module->params ?? '{}');
				}

				switch ($module->module ?? '')
				{
					case 'mod_login':
						return $this->normalizeRedirectionURL($params->get('login') ?: null);
						break;
				}
			}

			// TODO com_users...
		}

		return null;
	}

	private function normalizeRedirectionURL($url): ?string
	{
		// No URL?
		if (empty($url))
		{
			return null;
		}

		// Absolute URL?
		if (
			(substr($url, 0, 7) == 'http://') ||
			(substr($url, 0, 8) == 'https://')
		)
		{
			return $url;
		}

		// Non-SEF URL?
		if ((substr($url, 0, 9) == 'index.php'))
		{
			return Route::_($url, false, true);
		}

		// Menu item ID?
		if (is_numeric($url))
		{
			return Route::_(sprintf("index.php?Itemid=%d", (int) $url), false, false, true);
		}

		// I have no idea what this is!
		return null;
	}

	private function includeJ4ButtonHandler()
	{
		if (self::$includedJ4ButtonHandlerJS)
		{
			return;
		}

		// Load the JavaScript
		HTMLHelper::_('script', 'plg_system_sociallogin/dist/j4buttons.js', [
			'relative' => true,
			'version'  => md5_file(JPATH_SITE . '/media/plg_system_sociallogin/js/dist/j4buttons.js'),
		], [
			'defer' => 'defer',
		]);

		// Set the "don't load again" flag
		self::$includedJ4ButtonHandlerJS = true;
	}
}
