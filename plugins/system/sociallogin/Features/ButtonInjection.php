<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\SocialLogin\Features;

// Prevent direct access
defined('_JEXEC') or die;

use Akeeba\SocialLogin\Library\Helper\Integrations;
use Akeeba\SocialLogin\Library\Helper\Joomla;
use Exception;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\User\UserHelper;

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
	 * Intercepts module rendering, appending the Social Login buttons to the configured login modules.
	 *
	 * @param   object  $module   The module being rendered
	 * @param   object  $attribs  The module rendering attributes
	 *
	 * @throws  Exception
	 */
	public function onRenderModule(&$module, &$attribs)
	{
		if (!$this->enabled || $this->useJ4Injection())
		{
			return;
		}

		// We need this convoluted check because the JDocument is not initialized on plugin object construction or even
		// during onAfterInitialize. This is the only safe way to determine the document type.
		static $docType = null;

		if (is_null($docType))
		{
			try
			{
				$document = Joomla::getApplication()->getDocument();
			}
			catch (Exception $e)
			{
				$document = null;
			}

			$docType = (is_null($document)) ? 'error' : $document->getType();

			if ($docType != 'html')
			{
				$this->enabled = false;

				return;
			}
		}

		// If it's not a module I need to intercept bail out
		if (!in_array($module->module, $this->loginModules))
		{
			return;
		}

		// Append the social login buttons content
		Joomla::log('system', "Injecting buttons to {$module->module} module.");
		$selectors          = empty($this->relocateSelectors) ? [] : $this->relocateSelectors;
		$socialLoginButtons = Integrations::getSocialLoginButtons(null, null, 'akeeba.sociallogin.button', 'akeeba.sociallogin.buttons', null, $this->relocateButton, $selectors);
		$module->content    .= $socialLoginButtons;
	}

	/**
	 * Called after a component has finished running, right after Joomla has set the component output to the buffer.
	 * Used to inject our social login buttons in the front-end login page rendered by com_users.
	 *
	 * @return  void
	 */
	public function onAfterDispatch()
	{
		// Should I use this method?
		if (!$this->enabled || $this->useJ4Injection())
		{
			return;
		}

		// Are we enabled?
		if (!$this->interceptLogin)
		{
			return;
		}

		// Make sure I can get basic information
		try
		{
			$app     = Joomla::getApplication();
			$user    = Joomla::getUser();
			$isAdmin = Joomla::isAdminPage($app);
			$input   = $app->input;
		}
		catch (Exception $e)
		{
			return;
		}

		// No point showing a login button when you're already logged in
		if (!$user->guest)
		{
			return;
		}

		// I can only operate in frontend pages
		if ($isAdmin)
		{
			return;
		}

		// Make sure this is the Users component
		$option = $input->getCmd('option');

		if ($option !== 'com_users')
		{
			return;
		}

		// Make sure it is the right view / task
		$view = $input->getCmd('view');
		$task = $input->getCmd('task');

		$check1 = is_null($view) && is_null($task);
		$check2 = is_null($view) && ($task === 'login');
		$check3 = ($view === 'login') && is_null($task);

		if (!$check1 && !$check2 && !$check3)
		{
			return;
		}

		// Make sure it's an HTML document
		$document = $app->getDocument();

		if ($document->getType() != 'html')
		{
			return;
		}

		// Get the component output and append our buttons
		$buttons = Integrations::getSocialLoginButtons(null, null, 'akeeba.sociallogin.button', 'akeeba.sociallogin.buttons', null, true);

		$buffer = $document->getBuffer();

		$componentOutput = $buffer['component'][''][''];
		$componentOutput .= $buttons;
		$document->setBuffer($componentOutput, 'component');
	}

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
		if (!$this->enabled || !$this->useJ4Injection())
		{
			return [];
		}

		// Append the social login buttons content
		Joomla::log('system', "Injecting buttons using the Joomla 4 way.");

		$this->includeJ4ButtonHandler();

		return array_map(function (array $def) {
			$randomId = sprintf("plg_system_sociallogin-%s-%s-%s",
				$def['slug'], UserHelper::genRandomPassword(12), UserHelper::genRandomPassword(8));

			return [
				'label'          => $def['label'],
				'icon'           => $def['icon_class'] ?? '',
				'image'          => $def['rawimage'] ?? '',
				'class'          => sprintf('akeeba-sociallogin-link-button-j4 akeeba-sociallogin-link-button-%s', $def['slug']),
				'id'             => $randomId,
				'data-socialurl' => $def['link'],
			];
		}, Integrations::getSocialLoginButtonDefinitions());
	}

	private function includeJ4ButtonHandler()
	{
		if (self::$includedJ4ButtonHandlerJS)
		{
			return;
		}

		// Load the JavaScript
		HTMLHelper::_('script', 'plg_system_sociallogin/dist/j4buttons.js', [
			'relative'  => true,
			'framework' => true,
		], [
			'defer' => 'defer',
		]);

		// Set the "don't load again" flag
		self::$includedJ4ButtonHandlerJS = true;
	}


	/**
	 * Should I use the Joomla 4 button injection method?
	 *
	 * @return  bool
	 *
	 * @since   3.1.0
	 */
	private function useJ4Injection(): bool
	{
		if ($this->params->get('j4buttons', 1) == 0)
		{
			return false;
		}

		return version_compare(JVERSION, '3.999.999', 'ge');
	}
}