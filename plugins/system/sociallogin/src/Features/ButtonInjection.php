<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\System\SocialLogin\Features;

// Prevent direct access
defined('_JEXEC') || die;

use Exception;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserHelper;
use Joomla\Component\Users\Site\View\Login\HtmlView as LoginHtmlView;
use Joomla\Event\Event;
use Joomla\Registry\Registry;

/**
 * Feature: Button injection in login modules and login pages
 *
 * @since   3.0.1
 * @package Akeeba\SocialLogin\Features
 */
trait ButtonInjection
{
	/**
	 * Have I already included the Joomla 4 button handler JavaScript?
	 *
	 * @since 3.1.0
	 * @var   bool
	 */
	private static bool $includedJ4ButtonHandlerJS = false;

	/**
	 * Creates additional login buttons
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 *
	 * @throws Exception
	 * @since        3.1.0
	 * @see          AuthenticationHelper::getLoginButtons()
	 *
	 * @noinspection PhpUnused
	 */
	public function onUserLoginButtons(Event $event): void
	{
		if (!$this->enabled)
		{
			return;
		}

		// Append the social login buttons content
		Log::add(
			'Injecting buttons using the Joomla 4 way.',
			Log::DEBUG,
			'sociallogin.system'
		);

		$this->includeJ4ButtonHandler();

		$returnUrl         = $this->getReturnURLFromBackTrace();
		$buttonDefinitions = $this->getSocialLoginButtonDefinitions(null, $returnUrl);

		$this->customCss($buttonDefinitions);

		$ret = array_map(function (array $def) {
			$randomId = sprintf(
				"plg_system_sociallogin-%s-%s-%s",
				$def['slug'], UserHelper::genRandomPassword(12), UserHelper::genRandomPassword(8)
			);

			$imageKey     = 'image';
			$imageContent = $def['img'];

			if (substr($def['rawimage'], -4) === '.svg')
			{
				$imageKey     = 'svg';
				$image        = HTMLHelper::_('image', $def['rawimage'], '', '', true, true);
				$image        = $image ? JPATH_ROOT . substr($image, strlen(Uri::root(true))) : '';
				$imageContent = file_get_contents($image);
			}

			return [
				'label'          => $def['label'],
				$imageKey        => $imageContent,
				'class'          => sprintf('akeeba-sociallogin-link-button-j4 akeeba-sociallogin-link-button-j4-%s akeeba-sociallogin-link-button-%1$s', $def['slug']),
				'id'             => $randomId,
				'data-socialurl' => $def['link'],
			];
		}, $buttonDefinitions);

		$result = $event->getArgument('result') ?: [];

		if (!is_array($result))
		{
			$result = [$result];
		}

		$result[] = $ret;

		$event->setArgument('result', $result);
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

		$Itemid = $this->app->input->get('Itemid', 0);

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

				if (($module->module ?? '') == 'mod_login')
				{
					return $this->normalizeRedirectionURL($params->get('login') ?: null);
				}
			}

			// Extract from com_users login page
			if ($function === 'display' && $class === LoginHtmlView::class && !empty($Itemid))
			{
				$params = $this->app->getMenu()->getActive()->getParams();

				if ($params->get('loginredirectchoice', 1) == 1)
				{
					return Route::_('index.php?Itemid=' . $params->get('login_redirect_menuitem', ''));
				}

				return $params->get('login_redirect_url', '');
			}
		}

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
}
