<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

// I have to do some special handling to accommodate for the discrepancies between how Joomla creates menu items and how
// Joomla handles component controllers. Ugh!
$app = JFactory::getApplication();
$view = $app->input->getCmd('view');
$task = $app->input->getCmd('task');

if (!empty($view))
{
	if (strpos($task, '.') === false)
	{
		$task = $view . '.' . $task;
	}
	else
	{
		list($view, $task2) = explode('.', $task, 2);
	}

	$app->input->set('view', $view);
	$app->input->set('task', $task);
}

// Get an instance of the Social Login controller
$controller = JControllerLegacy::getInstance('SocialLogin');

// Get and execute the requested task
$input = JFactory::getApplication()->input;
$controller->execute($input->getCmd('task'));

// Apply any redirection set in the Controller
$controller->redirect();