<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\SocialLogin\Library\Helper;

// Protect from unauthorized access
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Application\BaseApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use RuntimeException;


/**
 * Helper class for handling AJAX requests
 */
final class Ajax
{
	/**
	 * Handle an AJAX request
	 *
	 * @param   BaseApplication  $app  The application
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

		$input          = $app->input;
		$akaction       = $input->getCmd('akaction');
		$token          = Joomla::getToken();
		$noTokenActions = ['dontremind'];

		if (!in_array($akaction, $noTokenActions) && ($input->getInt($token, 0) != 1))
		{
			throw new RuntimeException(Joomla::_('JERROR_ALERTNOAUTHOR'));
		}

		// Empty action? No bueno.
		if (empty($akaction))
		{
			throw new RuntimeException(Joomla::_('PLG_SYSTEM_SOCIALLOGIN_ERR_AJAX_INVALIDACTION'));
		}

		// A method ajaxSomething must exist.
		$method_name = 'ajax' . ucfirst($akaction);

		if (!method_exists($this, $method_name))
		{
			throw new RuntimeException(Joomla::_('PLG_SYSTEM_SOCIALLOGIN_ERR_AJAX_INVALIDACTION'));
		}

		return call_user_func([$this, $method_name], $app);
	}

	/**
	 * Unlink a user account from its social media presence
	 *
	 * @param   BaseApplication  $app  The application
	 *
	 * @throws  Exception
	 */
	protected function ajaxUnlink($app)
	{
		if (!Joomla::isCmsApplication($app))
		{
			return;
		}

		$input = $app->input;
		$slug  = $input->getCmd('slug');

		// No slug? No good.
		if (empty($slug))
		{
			throw new RuntimeException(Joomla::_('PLG_SYSTEM_SOCIALLOGIN_ERR_AJAX_INVALIDSLUG'));
		}

		// Get the user ID and make sure it's ours or we are Super Users
		$userId = Joomla::getSessionVar('userID', null, 'plg_system_sociallogin');
		Joomla::setSessionVar('userID', null, 'plg_system_sociallogin');

		/** @var   User $myUser Currently logged in user */
		$myUser = Joomla::getSessionVar('user');

		// Make sure we are unlinking our own user or we are Super Users
		if (empty($userId) || (!$myUser->authorise('core.manage') && ($myUser->id != $userId)))
		{
			throw new RuntimeException(Joomla::_('PLG_SYSTEM_SOCIALLOGIN_ERR_AJAX_INVALIDUSER'));
		}

		// Reset the session flag; the AJAX operation will change whether the Joomla user is linked to a social media account
		Joomla::setSessionVar('islinked', null, 'sociallogin');

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
	 * @param   BaseApplication  $app  The application
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
			throw new RuntimeException(Joomla::_('PLG_SYSTEM_SOCIALLOGIN_ERR_AJAX_INVALIDSLUG'));
		}

		// Call the plugin events to unlink the user
		Joomla::importPlugins('sociallogin');
		Joomla::runPlugins('onSocialLoginAuthenticate', array($slug), $app);
	}

	/**
	 * Set the "don't remind me again" flag
	 *
	 * Call by accessing index.php?option=com_ajax&group=system&plugin=sociallogin&akaction=dontremind&format=raw
	 *
	 * @param   BaseApplication  $app  The application
	 */
	protected function ajaxDontremind($app)
	{
		if (!Joomla::isCmsApplication($app))
		{
			return;
		}

		$myUser = Factory::getUser();
		$db     = Factory::getDbo();

		if ($myUser->guest)
		{
			return;
		}

		try
		{
			// Delete an existing profile value
			$query = $db->getQuery(true)
				->delete($db->qn('#__user_profiles'))
				->where($db->qn('user_id') . ' = ' . $db->q($myUser->id))
				->where($db->qn('profile_key') . ' = ' . $db->q('sociallogin.dontremind'));
			$db->setQuery($query)->execute();

			// Set the new profile value
			$o = (object) [
				'user_id'       => $myUser->id,
				'profile_key'   => 'sociallogin.dontremind',
				'profile_value' => 1,
			];

			$db->insertObject('#__user_profiles', $o);
		}
		catch (Exception $e)
		{
			// Do nothing; we can let this fail
		}

		// Reset the session flag; we need to re-evaluate the flag in the next page load.
		Joomla::setSessionVar('islinked', null, 'sociallogin');

	}
}
