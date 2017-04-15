<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

/**
 * Helper class for handling AJAX requests
 */
final class SocialLoginHelperAjax
{
	/**
	 * Handle an AJAX request
	 *
	 * @param   JApplicationBase  $input  The application
	 *
	 * @return  mixed
	 *
	 * @throws  RuntimeException  on error
	 */
	public function handle(JApplicationBase $app)
	{
		if (!($app instanceof JApplicationCms))
		{
			return null;
		}

		$input    = $app->input;
		$akaction = $input->getCmd('akaction');
		$session  = $app->getSession();
		$token = $session->getToken();

		if ($input->getInt($token, 0) != 1)
		{
			throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'));
		}

		// Empty action? No bueno.
		if (empty($akaction))
		{
			throw new RuntimeException(JText::_('PLG_SYSTEM_SOCIALLOGIN_ERR_AJAX_INVALIDACTION'));
		}

		// A method ajaxSomething must exist.
		$method_name = 'ajax' . ucfirst($akaction);

		if (!method_exists($this, $method_name))
		{
			throw new RuntimeException(JText::_('PLG_SYSTEM_SOCIALLOGIN_ERR_AJAX_INVALIDACTION'));
		}

		return call_user_func($method_name, $app);
	}

	/**
	 * Unlink a user account from its social media presence
	 *
	 * @param   JApplicationBase  $app  The application
	 */
	protected function ajaxUnlink(JApplicationBase $app)
	{
		if (!($app instanceof JApplicationCms))
		{
			return;
		}

		$input   = $app->input;
		$slug    = $input->getCmd('slug');
		$session = $app->getSession();

		// No slug? No good.
		if (empty($slug))
		{
			throw new RuntimeException(JText::_('PLG_SYSTEM_SOCIALLOGIN_ERR_AJAX_INVALIDSLUG'));
		}

		// Get the user ID and make sure it's ours or we are Super Users
		$userId = $session->get('userID', null, 'plg_system_sociallogin');
		$session->set('userID', null, 'plg_system_sociallogin');

		/** @var   JUser  $myUser  Currently logged in user */
		$myUser = $session->get('user');

		// Make sure we are unlinking our own user or we are Super Users
		if (empty($userId) || (!$myUser->authorise('core.manage') && ($myUser->id != $userId)))
		{
			throw new RuntimeException(JText::_('PLG_SYSTEM_SOCIALLOGIN_ERR_AJAX_INVALIDUSER'));
		}

		// Get the user to unlink
		$user = JFactory::getUser($userId);

		// Call the plugin events to unlink the user
		JPluginHelper::importPlugin('sociallogin');
		$app->triggerEvent('onSocialLoginUnlink', array($slug, $user));
	}
}