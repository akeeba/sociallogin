<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

/**
 * Renders a list of Social Login buttons. The HTML of each button is rendered with the akeeba.sociallogin.button layout
 * using the information from the `onSocialLoginGetLoginButton` method of each `sociallogin` plugin.
 *
 * Generic data
 *
 * @var   JLayoutFile  $this         The JLayout renderer
 * @var   array        $displayData  The data in array format. DO NOT USE.
 *
 * Layout specific data
 *
 * @var   array        $buttons      The HTML of each button.
 */

// Extract the data. Do not remove until the unset() line.
extract(array_merge(array(
        'buttons' => array()
), $displayData));

// Start writing your template override code below this line
?>
<div class="akeeba-sociallogin-buttons">
<?php foreach ($buttons as $button): ?>
    <?php echo $button; ?>
<?php endforeach; ?>
</div>
