<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\System\SocialLogin\Extension;

// Prevent direct access
defined('_JEXEC') || die;

use Exception;
use Joomla\Plugin\System\SocialLogin\Features\Ajax;
use Joomla\Plugin\System\SocialLogin\Features\ButtonInjection;
use Joomla\Plugin\System\SocialLogin\Features\DynamicUsergroups;
use Joomla\Plugin\System\SocialLogin\Features\UserFields;
use Joomla\Plugin\System\SocialLogin\Library\Helper\Joomla;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class SocialLogin extends CMSPlugin
{
	/** @var \Joomla\CMS\Application\CMSApplication */
	public $app;

	// Load the features, implemented as traits (for easier code management)
	use Ajax, DynamicUsergroups
	{
		Ajax::onAfterInitialise as protected onAfterIntialise_Ajax;
		DynamicUsergroups::onAfterInitialise as protected onAfterInitialise_DynamicUserGroups;
	}
	use ButtonInjection;
	use UserFields;

	/**
	 * User group ID to add the user to if they have linked social network accounts to their profile
	 *
	 * @var   int
	 * @since 3.0.1
	 */
	protected $linkedUserGroup = 0;

	/**
	 * User group ID to add the user to if they have NOT linked social network accounts to their profile
	 *
	 * @var   int
	 * @since 3.0.1
	 */
	protected $unlinkedUserGroup = 0;

	/**
	 * Should I add link/unlink buttons in the Edit User Profile page of com_users?
	 *
	 * @var   bool
	 */
	private $addLinkUnlinkButtons = true;

	/**
	 * Are the substitutions enabled?
	 *
	 * @var   bool
	 */
	private $enabled = true;

	public function __construct(&$subject, $config = [])
	{
		parent::__construct($subject, $config);

		// Register the Composer autoloader
		require_once __DIR__ . '/../../vendor/autoload.php';

		Joomla::addLogger('system');

		// Am I enabled?
		$this->enabled = $this->isEnabled();

		// Load the language files
		$this->loadLanguage();

		// Load the other plugin parameters
		$this->addLinkUnlinkButtons = $this->params->get('linkunlinkbuttons', 1);
		$this->linkedUserGroup      = (int) $this->params->get('linkedAccountUserGroup', 0);
		$this->unlinkedUserGroup    = (int) $this->params->get('noLinkedAccountUserGroup', 0);
	}

	/**
	 * Assemble the onAfterInitialise event from code belonging to many features' traits.
	 *
	 * @throws Exception
	 */
	public function onAfterInitialise()
	{
		$this->magicRoute();
		$this->onAfterInitialise_DynamicUserGroups();
		$this->onAfterIntialise_Ajax();
	}

	protected function magicRoute()
	{
		$currentUri = Uri::getInstance();
		$path       = $currentUri->getPath();

		if (empty($path))
		{
			return;
		}

		$rootPath = Uri::base(true);

		if (!empty($rootPath))
		{
			$path = substr($path, strlen($rootPath));
		}

		$path = trim($path, '/');

		if (strpos($path, 'index.php/') === 0)
		{
			$path = substr($path, 10);
		}

		// Remove the language part on multilingual sites
		if ($this->app->isClient('site') && $this->app->getLanguageFilter())
		{
			$languageTag    = $this->app->getLanguage()->getTag() ?? 'invalid_language';
			$allLanguages   = LanguageHelper::getLanguages('lang_code');
			$langDefinition = $allLanguages[$languageTag] ?? (object) ['sef' => ''];
			$langPrefix     = $langDefinition->sef ?? '';
			$langPrefix     = empty($langPrefix) ? $langPrefix : ($langPrefix . '/');

			if (!empty($langPrefix) && strpos($path, $langPrefix) === 0)
			{
				$path = substr($path, strlen($langPrefix));
			}
		}

		if (strpos($path, 'aksociallogin_finishLogin/') !== 0)
		{
			return;
		}

		[, $plugin] = explode('/', $path, 2);

		if (empty($plugin))
		{
			return;
		}

		[$plugin,] = explode('.', $plugin);

		$this->app->input->set('option', 'com_ajax');
		$this->app->input->set('group', 'sociallogin');
		$this->app->input->set('plugin', $plugin);
		$this->app->input->set('format', 'raw');

		$currentUri->setPath(rtrim($rootPath, '/') . '/index.php');
		$currentUri->setVar('option', 'com_ajax');
		$currentUri->setVar('group', 'sociallogin');
		$currentUri->setVar('plugin', $plugin);
		$currentUri->setVar('format', 'raw');
	}

	/**
	 * Should I enable the substitutions performed by this plugin?
	 *
	 * @return  bool
	 */
	private function isEnabled()
	{
		// It only make sense to let people log in when they are not already logged in ;)
		if (!Joomla::getUser()->guest)
		{
			return false;
		}

		return true;
	}
}