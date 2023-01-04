<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\System\SocialLogin\Library\Helper;

// Protect from unauthorized access
defined('_JEXEC') || die();

use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use RuntimeException;


/**
 * Helper class for handling AJAX requests
 */
final class Ajax
{
	private ?CMSApplication $app = null;

	private ?DatabaseInterface $db = null;

	private ?CMSPlugin $plugin = null;

	public function __construct(CMSPlugin $plugin, CMSApplication $app, DatabaseInterface $db)
	{
		$this->plugin = $plugin;
		$this->app    = $app;
		$this->db     = $db;
	}

	/**
	 * Handle an AJAX request
	 *
	 * @return  mixed
	 *
	 * @throws  RuntimeException  on error
	 */
	public function handle()
	{
		$input          = $this->app->input;
		$akaction       = $input->getCmd('akaction');
		$token          = $this->app->getSession()->getToken();
		$noTokenActions = ['dontremind'];

		if (!in_array($akaction, $noTokenActions) && ($input->getInt($token, 0) != 1))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'));
		}

		// Empty action? No bueno.
		if (empty($akaction))
		{
			throw new RuntimeException(Text::_('PLG_SYSTEM_SOCIALLOGIN_ERR_AJAX_INVALIDACTION'));
		}

		// A method ajaxSomething must exist.
		$method_name = 'ajax' . ucfirst($akaction);

		if (!method_exists($this, $method_name))
		{
			throw new RuntimeException(Text::_('PLG_SYSTEM_SOCIALLOGIN_ERR_AJAX_INVALIDACTION'));
		}

		return call_user_func([$this, $method_name]);
	}

	/**
	 * Initiate a user authentication against a remote server. Your plugin is supposed to perform a redirection to the
	 * remote server or throw a RuntimeException in case of an error.
	 *
	 * @throws  Exception
	 */
	protected function ajaxAuthenticate()
	{
		$input = $this->app->input;
		$slug  = $input->getCmd('slug');

		// No slug? No good.
		if (empty($slug))
		{
			throw new RuntimeException(Text::_('PLG_SYSTEM_SOCIALLOGIN_ERR_AJAX_INVALIDSLUG'));
		}

		// Call the plugin events to unlink the user
		PluginHelper::importPlugin('sociallogin');
		$this->plugin->runPlugins('onSocialLoginAuthenticate', [$slug]);
	}

	/**
	 * Set the "don't remind me again" flag
	 *
	 * Call by accessing index.php?option=com_ajax&group=system&plugin=sociallogin&akaction=dontremind&format=raw
	 */
	protected function ajaxDontremind()
	{
		$myUser = $this->app->getIdentity();
		$db     = $this->db;

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
		$this->app->getSession()->set('sociallogin.islinked', null);

	}

	/**
	 * Unlink a user account from its social media presence
	 *
	 * @throws  Exception
	 */
	protected function ajaxUnlink()
	{
		$input = $this->app->input;
		$slug  = $input->getCmd('slug');

		// No slug? No good.
		if (empty($slug))
		{
			throw new RuntimeException(Text::_('PLG_SYSTEM_SOCIALLOGIN_ERR_AJAX_INVALIDSLUG'));
		}

		// Get the user ID and make sure it's ours or we are Super Users
		$userId = $this->app->getSession()->get('plg_system_sociallogin.userID', null);
		$this->app->getSession()->set('plg_system_sociallogin.userID', null);

		/** @var   User $myUser Currently logged in user */
		$myUser = $this->app->getSession()->get('user');

		// Make sure we are unlinking our own user or we are Super Users
		if (empty($userId) || (!$myUser->authorise('core.manage') && ($myUser->id != $userId)))
		{
			throw new RuntimeException(Text::_('PLG_SYSTEM_SOCIALLOGIN_ERR_AJAX_INVALIDUSER'));
		}

		// Reset the session flag; the AJAX operation will change whether the Joomla user is linked to a social media account
		$this->app->getSession()->set('sociallogin.islinked', null);

		// Get the user to unlink
		$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);

		// Call the plugin events to unlink the user
		PluginHelper::importPlugin('sociallogin');
		$this->plugin->runPlugins('onSocialLoginUnlink', [$slug, $user]);
	}
}
