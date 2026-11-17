<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') || die();

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Uri\Uri;

$array_merge = array_merge(array(
	'slug'       => '',
	'type'       => 'link',
	'link'       => '',
	'token'      => '',
	'tooltip'    => '',
	'label'      => '',
	'img'        => '',
	'svg'        => '',
	'rawimage'   => '',
	'icon'       => '',
), $displayData);

/**
 * Renders a social account link / unlink button. Lets the user link their Joomla! user account with a social network
 * or, if it's already linked, unlink their user account from their social network presence. This is typically used in
 * the user account edit page.
 *
 * Generic data
 *
 * @var   FileLayout   $this         The JLayout renderer
 * @var   array        $displayData  The data in array format. DO NOT USE.
 *
 * Layout specific data
 *
 * @var   string       $slug        The name of the button being rendered, e.g. facebook
 * @var   string       $type        The type of the button being rendered: 'link' (user has not linked to this social
 *                                  network before) or 'unlink' (user is already linked to this social network, clicking
 *                                  this button will _unlink_ their user account from it).
 * @var   string       $link        URL for the button. For 'unlink' buttons this is the action of the POST form used
 *                                  to carry out the unlinking.
 * @var   string       $token       The anti-CSRF session token, rendered as a hidden field of the unlink form
 * @var   string       $tooltip     Tooltip to show on the button
 * @var   string       $label       Text content of the button
 * @var   string       $img         An <img> (or other) tag to use inside the button when $icon_class is empty
 * @var   string       $rawimage    Relative image path, e.g. plg_sociallogin_example/foobar.svg
 */

// Extract the data. Do not remove until the unset() line.
extract($array_merge);

if (empty($icon) && substr($rawimage, -4) === '.svg')
{
	$image = HTMLHelper::_('image', $rawimage, '', '', true, true);
	$image = $image ? JPATH_ROOT . substr($image, \strlen(Uri::root(true))) : '';
	$img   = file_get_contents($image);
}

// Start writing your template override code below this line
?>
<?php if ($type === 'unlink'): ?>
<?php
/**
 * This button is displayed inside the user edit form. HTML does not allow nested forms, so we cannot render a form
 * element here. Instead, a script submits a separate, detached POST form (see media/js/unlink.js).
 */
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();

if (!$wa->getRegistry()->exists('script', 'plg_system_sociallogin.unlink'))
{
	$wa->registerAndUseScript('plg_system_sociallogin.unlink', 'plg_system_sociallogin/unlink.js', [], ['defer' => true], ['core']);
}
else
{
	$wa->useScript('plg_system_sociallogin.unlink');
}
?>
<button type="button" class="btn btn-default akeeba-sociallogin-linkunlink-button akeeba-sociallogin-<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>-button akeeba-sociallogin-<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>-button-<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?> hasTooltip w-100"
   data-sociallogin-unlink="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>"
   data-sociallogin-token="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"
   title="<?= htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') ?>">
	<?php if (!empty($icon)): ?>
	<span class="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span>
	<?php else: ?>
	<?= $img ?>
	<?php endif; ?>
	<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
</button>
<?php else: ?>
<a class="btn btn-default akeeba-sociallogin-linkunlink-button akeeba-sociallogin-<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>-button akeeba-sociallogin-<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>-button-<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?> hasTooltip w-100"
   href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') ?>">
	<?php if (!empty($icon)): ?>
	<span class="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span>
	<?php else: ?>
	<?= $img ?>
	<?php endif; ?>
	<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
</a>
<?php endif; ?>
