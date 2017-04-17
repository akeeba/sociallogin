<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

/**
 * Renders a social login button, allowing the user to log into Joomla! using their social media presence. This is
 * typically used in login modules.
 *
 * Generic data
 *
 * @var   JLayoutFile  $this         The JLayout renderer
 * @var   array        $displayData  The data in array format. DO NOT USE.
 *
 * Layout specific data
 *
 * @var   string       $slug        The name of the button being rendered, e.g. facebook
 * @var   string       $link        URL for the button (href)
 * @var   string       $tooltip     Tooltip to show on the button
 * @var   string       $label       Text content of the button
 * @var   string       $icon_class  An icon class for the span inside the button
 * @var   string       $img         Relative path to an icon image file, used when $icon_class is not provided
 */

// Extract the data. Do not remove until the unset() line.
extract(array_merge(array(
	'slug'       => '',
	'link'       => '',
	'tooltip'    => '',
	'label'      => '',
	'icon_class' => '',
	'img'        => '',
), $displayData));

// Start writing your template override code below this line
?>
<a class="btn btn-default akeeba-sociallogin-button akeeba-sociallogin-button-<?php echo $slug?> hasTooltip"
   href="<?php echo $link?>"  title="<?php echo $tooltip ?>">
    <?php if (!empty($icon_class)): ?>
    <span class="<?php echo $icon_class ?>"></span>
    <?php else: ?>
    <img src="<?php echo $img ?>" border="0" />
    <?php endif; ?>
    <?php echo $label ?>
</a>
