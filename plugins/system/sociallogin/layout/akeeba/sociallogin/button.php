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

/**
 * Renders a social login button, allowing the user to log into Joomla! using their social media presence. This is
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
// Extract the data. Do not remove until the unset() line.
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
<a class="btn btn-default akeeba-sociallogin-button akeeba-sociallogin-button-<?= $slug ?> hasTooltip"
   id="<?= $randomId ?>"
   href="<?= $link ?>" title="<?= $tooltip ?>">
	<?php if (!empty($icon_class)): ?>
		<span class="<?= $icon_class ?>"></span>
	<?php else: ?>
		<?= $img ?>
	<?php endif; ?>
	<?= $label ?>
</a>
