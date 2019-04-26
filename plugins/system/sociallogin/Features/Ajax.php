<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\SocialLogin\Features;

// Prevent direct access
defined('_JEXEC') or die;

use Akeeba\SocialLogin\Library\Helper\Joomla;
use Exception;
use Joomla\CMS\Uri\Uri;

/**
 * Feature: AJAX request handling
 *
 * @package Akeeba\SocialLogin\Features
 * @since   3.0.1
 */
trait Ajax
{
	/**
	 * We need to log into the backend BUT com_ajax is not accessible unless we are already logged in. Moreover, since
	 * the backend is a separate application from the frontend we cannot share the user session between them. Meanwhile
	 * we need to leave the site, go to a social network and have the social network post back the OAuth2 code to our
	 * site. So how am I going to retrieve the code from the OAuth2 response if I can't run com_ajax before logging in?
	 * Yes, you guessed it right. I AM GOING TO ABUSE onAfterInitialize. Pay attention, kids, that's how grown-ups make
	 * Joomla submit to their will.
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 */
	public function onAfterInitialise()
	{
		// Make sure this is the backend of the site...
		if (!Joomla::isAdminPage())
		{
			return;
		}

		// ...and we are not already logged in...
		if (!Joomla::getUser()->guest)
		{
			return;
		}

		$input = Joomla::getApplication()->input;

		// ...and this is a request to com_ajax...
		if ($input->getCmd('option', '') != 'com_ajax')
		{
			return;
		}

		// ...about a sociallogin plugin.
		if ($input->getCmd('group', '') != 'sociallogin')
		{
			return;
		}

		// Load the plugin and execute the AJAX method
		$plugin = $input->getCmd('plugin', '');

		Joomla::importPlugins('sociallogin', $plugin);
		$methodName = 'onAjax' . ucfirst($plugin);

		Joomla::getApplication()->triggerEvent($methodName);
	}

	/**
	 * Processes the callbacks from social login buttons.
	 *
	 * Note: this method is called from Joomla's com_ajax
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 */
	public function onAjaxSociallogin()
	{
		$ajax  = new \Akeeba\SocialLogin\Library\Helper\Ajax();
		$app   = Joomla::getApplication();
		$input = $app->input;

		// Get the return URL from the session
		$returnURL = Joomla::getSessionVar('returnUrl', Uri::base(), 'plg_system_sociallogin');
		Joomla::setSessionVar('returnUrl', null, 'plg_system_sociallogin');
		$result = null;

		try
		{
			Joomla::log('system', "Received AJAX callback.");
			$result = $ajax->handle($app);
		}
		catch (Exception $e)
		{
			Joomla::log('system', "Callback failure, redirecting to $returnURL.");
			$app->enqueueMessage($e->getMessage(), 'error');
			$app->redirect($returnURL);

			return;
		}

		if ($result != null)
		{
			switch ($input->getCmd('encoding', 'json'))
			{
				default:
				case 'json':
					Joomla::log('system', "Callback complete, returning JSON.");
					echo json_encode($result);

					break;

				case 'jsonhash':
					Joomla::log('system', "Callback complete, returning JSON inside ### markers.");
					echo '###' . json_encode($result) . '###';

					break;

				case 'raw':
					Joomla::log('system', "Callback complete, returning raw response.");
					echo $result;

					break;

				case 'redirect':
					$modifiers = '';

					if (isset($result['message']))
					{
						$type = isset($result['type']) ? $result['type'] : 'info';
						$app->enqueueMessage($result['message'], $type);

						$modifiers = " and setting a system message of type $type";
					}

					if (isset($result['url']))
					{
						Joomla::log('system', "Callback complete, performing redirection to {$result['url']}{$modifiers}.");
						$app->redirect($result['url']);
					}


					Joomla::log('system', "Callback complete, performing redirection to {$result}{$modifiers}.");
					$app->redirect($result);

					return;
					break;
			}

			$app->close(200);
		}

		Joomla::log('system', "Null response from AJAX callback, redirecting to $returnURL");

		$app->redirect($returnURL);
	}

}