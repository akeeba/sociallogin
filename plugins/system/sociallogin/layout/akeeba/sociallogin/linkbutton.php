<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') || die();

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
<form method="post" action="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>">
	<input type="hidden" name="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>" value="1">
	<button type="submit" class="btn btn-default akeeba-sociallogin-linkunlink-button akeeba-sociallogin-<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>-button akeeba-sociallogin-<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>-button-<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?> hasTooltip w-100"
	   title="<?= htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') ?>">
		<?php if (!empty($icon)): ?>
		<span class="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span>
		<?php else: ?>
		<?= $img ?>
		<?php endif; ?>
		<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
	</button>
</form>
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
