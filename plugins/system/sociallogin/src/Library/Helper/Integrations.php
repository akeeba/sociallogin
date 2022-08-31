<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\System\SocialLogin\Library\Helper;

// Protect from unauthorized access
defined('_JEXEC') || die();

use Exception;
use Joomla\Application\AbstractApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\User\User;

/**
 * Helper class for managing integrations
 */
abstract class Integrations
{
	/**
	 * Gets the Social Login buttons for linking and unlinking accounts (typically used in the My Account page).
	 *
	 * @param   User                 $user           The Joomla! user object for which to get the buttons. Omit to use
	 *                                               the currently logged in user.
	 * @param   string               $buttonLayout   JLayout for rendering a single login button
	 * @param   string               $buttonsLayout  JLayout for rendering all the login buttons
	 * @param   AbstractApplication  $app            The application we are running in. Skip to auto-detect
	 *                                               (recommended).
	 *
	 * @return  string  The rendered HTML of the login buttons
	 *
	 * @throws  Exception
	 */
	public static function getSocialLinkButtons($user = null, $buttonLayout = 'akeeba.sociallogin.linkbutton', $buttonsLayout = 'akeeba.sociallogin.linkbuttons', $app = null)
	{
		return ($app ?? Factory::getApplication())
			->bootPlugin('sociallogin', 'system')
			->getSocialLinkButtons($user, $buttonLayout, $buttonsLayout);
	}

	/**
	 * Returns the raw SocialLogin button definitions.
	 *
	 * Each definition is a dictionary (hashed) array with the following keys:
	 *
	 * * `slug`: The name of the plugin rendering this button. Used for customized JLayouts.
	 * * `link`: The href attribute for the anchor tag.
	 * * `tooltip`: The tooltip of the anchor tag.
	 * * `label`: What to put inside the anchor tag.
	 * * `img`: The IMG tag for the image to use
	 * * `rawimage`: The relative image path e.g. `plg_sociallogin_example/foo.svg`
	 *
	 * @param   AbstractApplication  $app
	 * @param   string|null          $loginURL
	 * @param   string|null          $failureURL
	 *
	 * @return array  Simple array of dictionary arrays. See method description for the format.
	 * @throws Exception
	 */
	public static function getSocialLoginButtonDefinitions(AbstractApplication $app = null, ?string $loginURL = null, ?string $failureURL = null): array
	{
		return ($app ?? Factory::getApplication())
			->bootPlugin('sociallogin', 'system')
			->getSocialLoginButtonDefinitions($loginURL, $failureURL, $app);
	}
}
