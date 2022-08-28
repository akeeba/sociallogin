<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\System\SocialLogin\Library\Plugin;

use Exception;
use Joomla\Application\AbstractApplication;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\User;
use Joomla\Plugin\System\SocialLogin\Library\Helper\Joomla;

trait SocialLoginButtonsTrait
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
	 *
	 * @return  string  The rendered HTML of the login buttons
	 *
	 * @throws  Exception
	 */
	public function getSocialLinkButtons($user = null, $buttonLayout = 'akeeba.sociallogin.linkbutton', $buttonsLayout = 'akeeba.sociallogin.linkbuttons', $app = null)
	{
		if (!is_object($app))
		{
			$app = $this->app;
		}

		PluginHelper::importPlugin('sociallogin');

		$buttonDefinitions = Joomla::runPlugins('onSocialLoginGetLinkButton', [$user], $app);
		$buttonsHTML       = [];

		$this->customCss($buttonDefinitions);

		foreach ($buttonDefinitions as $buttonDefinition)
		{
			if (empty($buttonDefinition))
			{
				continue;
			}

			$includePath = JPATH_SITE . '/plugins/sociallogin/' . $buttonDefinition['slug'] . '/layout';

			// First try the plugin-specific layout
			$html = Joomla::renderLayout("$buttonLayout.{$buttonDefinition['slug']}", $buttonDefinition, $includePath);

			if (empty($html))
			{
				$html = Joomla::renderLayout($buttonLayout, $buttonDefinition, $includePath);
			}

			$buttonsHTML[] = $html;
		}

		return Joomla::renderLayout($buttonsLayout, ['buttons' => $buttonsHTML]);
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
	public function getSocialLoginButtonDefinitions(?string $loginURL = null, ?string $failureURL = null, AbstractApplication $app = null): array
	{
		if (!is_object($app))
		{
			$app = $this->app;
		}

		PluginHelper::importPlugin('sociallogin');

		$buttonDefinitions = Joomla::runPlugins('onSocialLoginGetLoginButton', [
			$loginURL,
			$failureURL,
		], $app);

		if (empty($buttonDefinitions))
		{
			$buttonDefinitions = [];
		}

		return array_filter($buttonDefinitions, function ($definition) {
			return is_array($definition) && !empty($definition);
		});
	}

	public function customCss(array $buttonDefinitions)
	{
		static $alreadyRun = false;

		if ($alreadyRun)
		{
			return;
		}

		$alreadyRun = true;
		$customCSS  = [];

		foreach ($buttonDefinitions as $def)
		{
			if (!($def['customCSS'] ?? false))
			{
				continue;
			}

			$customCSS[$def['slug'] ?: '__invalid'] = [
				$def['bgColor'] ?: '#000000',
				$def['fgColor'] ?: '#FFFFFF',
			];
		}

		if (isset($customCSS['__invalid']))
		{
			unset($customCSS['__invalid']);
		}

		if (empty($customCSS))
		{
			return;
		}

		$css = '';

		foreach ($customCSS as $slug => $colors)
		{
			[$bg, $fg] = $colors;
			$css .= <<< CSS
.akeeba-sociallogin-unlink-button-{$slug}, .akeeba-sociallogin-link-button-{$slug} { color: var(--sociallogin-{$slug}-fg, $fg) !important; background-color: var(--sociallogin-{$slug}-bg, $bg) !important; }

CSS;
		}

		try
		{
			$document = $this->app->getDocument();
		}
		catch (Exception $e)
		{
			return;
		}

		if (!($document instanceof HtmlDocument))
		{
			return;
		}

		$document->getWebAssetManager()->addInlineStyle($css);
	}

}