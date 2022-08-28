<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\System\SocialLogin\Features;

// Prevent direct access
defined('_JEXEC') || die;

use Exception;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\Event;

/**
 * Feature: AJAX request handling
 *
 * @since   3.0.1
 * @package Akeeba\SocialLogin\Features
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
	 * @param   Event  $e
	 *
	 * @return  void
	 *
	 */
	public function onAfterInitialise(Event $e): void
	{
		// Make sure this is the backend of the site...
		if (!$this->app->isClient('administrator'))
		{
			return;
		}

		// ...and we are not already logged in...
		if (!$this->app->getIdentity()->guest)
		{
			return;
		}

		$input = $this->app->input;

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

		// Reset the session flag; the AJAX operation may change whether the Joomla user is linked to a social media account
		$this->app->getSession()->set('sociallogin.islinked', null);

		// Load the plugin and execute the AJAX method
		$plugin = $input->getCmd('plugin', '');

		PluginHelper::importPlugin('sociallogin', $plugin);
		$methodName = 'onAjax' . ucfirst($plugin);

		$this->app->triggerEvent($methodName);
	}

	/**
	 * Processes the callbacks from social login buttons.
	 *
	 * Note: this method is called from Joomla's com_ajax
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 *
	 * @noinspection PhpUnused
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function onAjaxSociallogin(Event $event): void
	{
		$ajax  = new \Joomla\Plugin\System\SocialLogin\Library\Helper\Ajax();
		$app   = $this->app;
		$input = $app->input;

		// Get the return URL from the session
		$returnURL = $this->app->getSession()->get('plg_system_sociallogin.returnUrl', Uri::base());
		$this->app->getSession()->set('plg_system_sociallogin.returnUrl', null);

		try
		{
			Log::add(
				'Received AJAX callback.',
				Log::DEBUG,
				'sociallogin.system'
			);

			$result = $ajax->handle($app);
		}
		catch (Exception $e)
		{
			Log::add(
				sprintf(
					'Callback failure, redirecting to %s.',
					$returnURL
				),
				Log::DEBUG,
				'sociallogin.system'
			);

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
					Log::add(
						'Callback complete, returning JSON.',
						Log::DEBUG,
						'sociallogin.system'
					);

					echo json_encode($result);

					break;

				case 'jsonhash':
					Log::add(
						'Callback complete, returning JSON inside ### markers.',
						Log::DEBUG,
						'sociallogin.system'
					);

					echo '###' . json_encode($result) . '###';

					break;

				case 'raw':
					Log::add(
						'Callback complete, returning raw response.',
						Log::DEBUG,
						'sociallogin.system'
					);

					echo $result;

					break;

				case 'redirect':
					$modifiers = '';

					if (isset($result['message']))
					{
						$type = $result['type'] ?? 'info';
						$app->enqueueMessage($result['message'], $type);

						$modifiers = " and setting a system message of type $type";
					}

					if (isset($result['url']))
					{
						Log::add(
							sprintf(
								'Callback complete, performing redirection to %s%s.',
								$result['url'], $modifiers
							),
							Log::DEBUG,
							'sociallogin.system'
						);

						$app->redirect($result['url']);
					}

					Log::add(
						sprintf(
							'Callback complete, performing redirection to %s%s.',
							$result, $modifiers
						),
						Log::DEBUG,
						'sociallogin.system'
					);

					$app->redirect($result);

					return;
			}

			$app->close(200);
		}

		Log::add(
			sprintf(
				'Null response from AJAX callback, redirecting to %s',
				$returnURL
			),
			Log::DEBUG,
			'sociallogin.system'
		);

		$app->redirect($returnURL);
	}

}
