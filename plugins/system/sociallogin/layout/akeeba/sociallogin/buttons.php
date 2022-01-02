<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') || die();

use Joomla\CMS\Layout\FileLayout;

$array_merge = array_merge(array(
        'buttons' => array()
), $displayData);

/**
 * Renders a list of Social Login buttons. The HTML of each button is rendered with the akeeba.sociallogin.button layout
 * using the information from the `onSocialLoginGetLoginButton` method of each `sociallogin` plugin. This is typically
 * used in login modules.
 *
 * Generic data
 *
 * @var   FileLayout   $this         The Joomla layout renderer
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
<div class="akeeba-sociallogin-buttons">
<?php foreach ($buttons as $button): ?>
    <?php echo $button; ?>
<?php endforeach; ?>
</div>
