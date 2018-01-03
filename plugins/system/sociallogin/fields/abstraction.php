<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;

// Work around eAccelerator under PHP 5.4 *WHICH WAS NEVER INTENDED TO WORK (THE PROJECT WAS ABANDONED)*!
if (defined('_AkeebaSocialLoginJFormFieldParent_'))
{
	return;
}

if (!class_exists('AkeebaSocialLoginJFormFieldParent'))
{
	if (class_exists('Joomla\\CMS\\Form\\FormField'))
	{
		class AkeebaSocialLoginJFormFieldParent extends FormField {};
	}
	else
	{
		class AkeebaSocialLoginJFormFieldParent extends JFormField {};
	}
}

define('_AkeebaSocialLoginJFormFieldParent_', 1);
