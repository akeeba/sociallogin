<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') || die();

use Joomla\CMS\Form\FormHelper;

// Prevent PHP fatal errors if this somehow gets accidentally loaded multiple times
if (class_exists('JFormFieldFancyradio'))
{
	return;
}

// Load the base form field class
FormHelper::loadFieldClass('radio');

/**
 * Yes/No switcher, compatible with Joomla 3 and 4
 *
 * ## How to use
 *
 * 1. Create a folder in your project for custom Joomla form fields, e.g. components/com_example/fields
 * 2. Create a new file called `fancyradio.php` with the content
 *    ```php
 *    defined('_JEXEC') || die();
 *    require_once JPATH_LIBRARIES . '/fof40/Html/Fields/fancyradio.php';
 *    ```
 *
 * @package      Joomla\CMS\Form\Field
 *
 * @since        1.0.0
 * @noinspection PhpUnused
 * @noinspection PhpIllegalPsrClassPathInspection
 */
class JFormFieldFancyradio extends JFormFieldRadio
{
	public function __construct($form = null)
	{
		if (version_compare(JVERSION, '3.999.999', 'gt'))
		{
			// Joomla 4.0 and later.
			$this->layout = 'joomla.form.field.radio.switcher';
		}
		else
		{
			// Joomla 3.x. Yes, 3.10 does have the layout but I am playing it safe.
			$this->layout = 'joomla.form.field.radio';
		}

		parent::__construct($form);
	}
}