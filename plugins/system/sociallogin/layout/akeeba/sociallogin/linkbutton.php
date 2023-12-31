<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
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
 * @var   string       $link        URL for the button (href)
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
<a class="btn btn-default akeeba-sociallogin-linkunlink-button akeeba-sociallogin-<?= $type?>-button akeeba-sociallogin-<?= $type?>-button-<?= $slug?> hasTooltip w-100"
   href="<?= $link?>" title="<?= $tooltip ?>">
	<?php if (!empty($icon)): ?>
	<span class="<?= $icon ?>" aria-hidden="true"></span>
	<?php else: ?>
	<?= $img ?>
	<?php endif; ?>
	<?= $label ?>
</a>
