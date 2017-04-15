<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

use Joomla\Registry\Registry;

/**
 * Helper class for managing integrations
 */
abstract class SocialLoginHelperIntegrations
{
	/**
	 * Cached copy of the social login buttons' HTML
	 *
	 * @var   string
	 */
	private static $cachedSocialLoginButtons = null;

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
	private static function renderLayout($layoutFile, $displayData = null, $includePath = '', $options = null)
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
	 * Gets the Social Login buttons placeholder
	 *
	 * @param   string  $loginURL       The URL to return to upon successful login. Current URL if omitted.
	 * @param   string  $failureURL     The URL to return to on login error. Set to $loginURL if omitted.
	 * @param   string  $buttonLayout   JLayout for rendering a single login button
	 * @param   string  $buttonsLayout  JLayout for rendering all the login buttons
	 *
	 * @return  string  The rendered HTML of the login buttons
	 */
	public static function getSocialLoginButtons($loginURL = null, $failureURL = null, $buttonLayout = 'akeeba.sociallogin.button', $buttonsLayout  = 'akeeba.sociallogin.buttons')
	{
		if (is_null(self::$cachedSocialLoginButtons))
		{
			JPluginHelper::importPlugin('sociallogin');

			$buttonDefinitions = JFactory::$application->triggerEvent('onSocialLoginGetLoginButton', array(
				$loginURL,
				$failureURL
			));
			$buttonsHTML       = array();

			foreach ($buttonDefinitions as $buttonDefinition)
			{
				if (empty($buttonDefinition))
				{
					continue;
				}

				$includePath = JPATH_SITE . '/plugins/sociallogin/' . $buttonDefinition['slug'] . '/layout';
				$buttonsHTML[] = self::renderLayout($buttonLayout, $buttonDefinition, $includePath);
			}

			self::$cachedSocialLoginButtons = self::renderLayout($buttonsLayout, array('buttons' => $buttonsHTML));
		}

		return self::$cachedSocialLoginButtons;
	}

}