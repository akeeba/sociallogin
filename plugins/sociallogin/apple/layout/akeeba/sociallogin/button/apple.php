<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') || die();

use Akeeba\SocialLogin\Library\Helper\Integrations;
use Akeeba\SocialLogin\Library\Helper\Joomla;
use Joomla\CMS\Layout\FileLayout;

/**
 * Renders a Sign In with Apple login button, allowing the user to log into Joomla! using their Apple ID. This is
 * typically used in login modules.
 *
 * Generic data
 *
 * @var   FileLayout $this        The JLayout renderer
 * @var   array      $displayData The data in array format. DO NOT USE.
 *
 * Layout specific data
 *
 * @var   string     $slug        The name of the button being rendered, e.g. facebook
 * @var   string     $link        URL for the button (href)
 * @var   string     $tooltip     Tooltip to show on the button
 * @var   string     $label       Text content of the button
 * @var   string     $icon_class  An icon class for the span inside the button
 * @var   string     $img         An <img> (or other) tag to use inside the button when $icon_class is empty
 * @var   bool       $relocate    Should I try to move the social login button next to the regular login button?
 * @var   string[]   $selectors   A list of CSS selectors I will use to find the regular login button in the module.
 */
// BEGIN - MANDATORY CODE
// Should I fall back to the generic akeeba.sociallogin.button layout instead?
$plugin       = \Joomla\CMS\Plugin\PluginHelper::getPlugin('sociallogin', 'apple');
$pluginParams = new \Joomla\Registry\Registry($plugin->params);

if ($pluginParams->get('imagebutton', 1) != 1)
{
	return;
}
$array_merge = array_merge([
	'slug'       => '',
	'link'       => '',
	'tooltip'    => '',
	'label'      => '',
	'icon_class' => '',
	'img'        => '',
	'relocate'   => false,
	'selectors'  => [
		"#form-login-submit > button",
		"button[type=submit]",
		"[type=submit]",
		"[id*=\"submit\"]",
	],
], $displayData);

// Extract the data.
extract($array_merge);

$randomId = 'akeeba-sociallogin-' . Joomla::generateRandom(12) . '-' . Joomla::generateRandom(8);

$jsSelectors = implode(", ", array_map(function ($selector) {
	return '"' . addslashes($selector) . '"';
}, $selectors));

if ($relocate)
{
	Integrations::includeButtonRelocationJS();

	$js = <<< JS

window.jQuery(document).ready(function(){
	akeeba_sociallogin_move_button(document.getElementById('{$randomId}'), [{$jsSelectors}]); 
});

JS;
	Joomla::getApplication()->getDocument()->addScriptDeclaration($js);
}
// END - MANDATORY CODE

// Start writing your template override code below this line
?>
<a class="akeeba-sociallogin-button akeeba-sociallogin-button-<?= $slug ?>--image hasTooltip"
   id="<?= $randomId ?>"
   href="<?= $link ?>" title="<?= $tooltip ?>">
	<img
			srcset="https://appleid.cdn-apple.com/appleid/button?height=36&scale=6 6x, https://appleid.cdn-apple.com/appleid/button?height=36&scale=4 4x, https://appleid.cdn-apple.com/appleid/button?height=36&scale=3 3x, https://appleid.cdn-apple.com/appleid/button?height=36&scale=2 2x, https://appleid.cdn-apple.com/appleid/button?height=36 1x"
			src="https://appleid.cdn-apple.com/appleid/button"
			alt="<?= $label ?>"
</a>
