<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;

// Work around eAccelerator under PHP 5.4 *WHICH WAS NEVER INTENDED TO WORK (THE PROJECT WAS ABANDONED)*!
if (defined('_AkeebaSocialLoginJPlugin_'))
{
	return;
}

if (!class_exists('AkeebaSocialLoginJPlugin'))
{
	if (class_exists('Joomla\\CMS\\Plugin\\CMSPlugin'))
	{
		class AkeebaSocialLoginJPlugin extends CMSPlugin {};
	}
	else
	{
		class AkeebaSocialLoginJPlugin extends JPlugin {};
	}
}

define('_AkeebaSocialLoginJPlugin_', 1);
