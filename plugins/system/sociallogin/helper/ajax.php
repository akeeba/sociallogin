<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
use Akeeba\SocialLogin\Library\Helper\Joomla;
use Joomla\CMS\Application\BaseApplication;

defined('_JEXEC') or die();

/**
 * Helper class for handling AJAX requests
 */
final class SocialLoginHelperAjax
{
	/**
	 * Handle an AJAX request
	 *
	 * @param   JApplicationBase|BaseApplication $app The application
	 *
	 * @return  mixed
	 *
	 * @throws  RuntimeException  on error
	 */
	public function handle($app)
	{
		if (!Joomla::isCmsApplication($app))
		{
			return null;
		}

		$input    = $app->input;
		$akaction = $input->getCmd('akaction');
		$token = Joomla::getToken();

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

		return call_user_func(array($this, $method_name), $app);
	}

	/**
	 * Unlink a user account from its social media presence
	 *
	 * @param   JApplicationBase|BaseApplication  $app  The application
	 *
	 * @throws  Exception
	 */
	protected function ajaxUnlink($app)
	{
		if (!Joomla::isCmsApplication($app))
		{
			return;
		}

		$input   = $app->input;
		$slug    = $input->getCmd('slug');

		// No slug? No good.
		if (empty($slug))
		{
			throw new RuntimeException(JText::_('PLG_SYSTEM_SOCIALLOGIN_ERR_AJAX_INVALIDSLUG'));
		}

		// Get the user ID and make sure it's ours or we are Super Users
		$userId = Joomla::getSessionVar('userID', null, 'plg_system_sociallogin');
		Joomla::setSessionVar('userID', null, 'plg_system_sociallogin');

		/** @var   JUser  $myUser  Currently logged in user */
		$myUser = Joomla::getSessionVar('user');

		// Make sure we are unlinking our own user or we are Super Users
		if (empty($userId) || (!$myUser->authorise('core.manage') && ($myUser->id != $userId)))
		{
			throw new RuntimeException(JText::_('PLG_SYSTEM_SOCIALLOGIN_ERR_AJAX_INVALIDUSER'));
		}

		// Get the user to unlink
		$user = Joomla::getUser($userId);

		// Call the plugin events to unlink the user
		Joomla::importPlugins('sociallogin');
		Joomla::runPlugins('onSocialLoginUnlink', array($slug, $user), $app);
	}

	/**
	 * Initiate a user authentication against a remote server. Your plugin is supposed to perform a redirection to the
	 * remote server or throw a RuntimeException in case of an error.
	 *
	 * @param   JApplicationBase|BaseApplication  $app  The application
	 *
	 * @throws  Exception
	 */
	protected function ajaxAuthenticate($app)
	{
		if (!Joomla::isCmsApplication($app))
		{
			return;
		}

		$input   = $app->input;
		$slug    = $input->getCmd('slug');

		// No slug? No good.
		if (empty($slug))
		{
			throw new RuntimeException(JText::_('PLG_SYSTEM_SOCIALLOGIN_ERR_AJAX_INVALIDSLUG'));
		}

		// Call the plugin events to unlink the user
		Joomla::importPlugins('sociallogin');
		Joomla::runPlugins('onSocialLoginAuthenticate', array($slug), $app);
	}

}
