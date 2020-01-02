<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Layout\FileLayout;

// Protect from unauthorized access
defined('_JEXEC') or die();

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
 * @var   string       $icon_class  An icon class for the span inside the button
 * @var   string       $img         An <img> (or other) tag to use inside the button when $icon_class is empty
 */

// Extract the data. Do not remove until the unset() line.
extract(array_merge(array(
	'slug'       => '',
	'type'       => 'link',
	'link'       => '',
	'tooltip'    => '',
	'label'      => '',
	'icon_class' => '',
	'img'        => '',
), $displayData));

// Start writing your template override code below this line
?>
<a class="btn btn-default akeeba-sociallogin-linkunlink-button akeeba-sociallogin-<?php echo $type?>-button akeeba-sociallogin-<?php echo $type?>-button-<?php echo $slug?> hasTooltip"
   href="<?php echo $link?>"  title="<?php echo $tooltip ?>">
    <?php if (!empty($icon_class)): ?>
    <span class="<?php echo $icon_class ?>"></span>
    <?php else: ?>
    <?php echo $img ?>
    <?php endif; ?>
    <?php echo $label ?>
</a>
