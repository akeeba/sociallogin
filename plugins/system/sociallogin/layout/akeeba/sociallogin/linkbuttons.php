<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') || die();

use Joomla\CMS\Layout\FileLayout;

$array_merge = array_merge(array(
        'buttons' => array()
), $displayData);

/**
 * Renders a list of Social Login user account to social network link / unlink buttons. The HTML of each button
 * is rendered with the akeeba.sociallogin.linkbutton layout using the information from the `onSocialLoginGetLinkButton`
 * method of each `sociallogin` plugin. This is typically used in the user account edit page.
 *
 * Generic data
 *
 * @var   FileLayout   $this         The JLayout renderer
 * @var   array        $displayData  The data in array format. DO NOT USE.
 *
 * Layout specific data
 *
 * @var   array        $buttons      The HTML of each button.
 */

// Extract the data. Do not remove until the unset() line.
extract($array_merge);

// Start writing your template override code below this line
?>
<div class="akeeba-sociallogin-linkunlink-buttons">
<?php foreach ($buttons as $button): ?>
    <?php echo $button; ?>
<?php endforeach; ?>
</div>
