<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\System\SocialLogin\Extension;

// Prevent direct access
defined('_JEXEC') || die;

use Exception;
use JLoader;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\System\SocialLogin\Features\Ajax;
use Joomla\Plugin\System\SocialLogin\Features\ButtonInjection;
use Joomla\Plugin\System\SocialLogin\Features\DynamicUsergroups;
use Joomla\Plugin\System\SocialLogin\Features\UserFields;
use Joomla\Plugin\System\SocialLogin\Library\Plugin\AddLoggerTrait;
use Joomla\Plugin\System\SocialLogin\Library\Plugin\RunPluginsTrait;
use Joomla\Plugin\System\SocialLogin\Library\Plugin\SocialLoginButtonsTrait;

class SocialLogin extends CMSPlugin implements SubscriberInterface
{
	// Load the features, implemented as traits (for easier code management)
	use Ajax, DynamicUsergroups
	{
		Ajax::onAfterInitialise as protected onAfterIntialise_Ajax;
		DynamicUsergroups::onAfterInitialise as protected onAfterInitialise_DynamicUserGroups;
	}
	use ButtonInjection;
	use UserFields;
	use AddLoggerTrait;
	use SocialLoginButtonsTrait;
	use RunPluginsTrait;

	/** @var CMSApplication|SiteApplication|AdministratorApplication */
	public $app;

	/** @var DatabaseDriver|DatabaseInterface */
	public $db;

	/**
	 * User group ID to add the user to if they have linked social network accounts to their profile
	 *
	 * @since 3.0.1
	 * @var   int
	 */
	protected int $linkedUserGroup = 0;

	/**
	 * User group ID to add the user to if they have NOT linked social network accounts to their profile
	 *
	 * @since 3.0.1
	 * @var   int
	 */
	protected int $unlinkedUserGroup = 0;

	/**
	 * Should I add link/unlink buttons in the Edit User Profile page of com_users?
	 *
	 * @var   bool
	 */
	private bool $addLinkUnlinkButtons = true;

	/**
	 * Are the substitutions enabled?
	 *
	 * @var   bool
	 */
	private bool $enabled = true;

	public function __construct(&$subject, $config = [])
	{
		parent::__construct($subject, $config);

		// Register the Composer autoloader
		if (version_compare(JVERSION, '4.2', 'lt'))
		{
			require_once __DIR__ . '/../../vendor/autoload.php';
		}
		else
		{
			JLoader::registerNamespace('CoderCat\\JWKToPEM', __DIR__ . '/../../vendor/codercat/jwk-to-pem/src');
		}

		$this->addLogger('system');

		// Am I enabled?
		$this->enabled = $this->isEnabled();

		// Load the language files
		$this->loadLanguage();

		// Load the other plugin parameters
		$this->addLinkUnlinkButtons = $this->params->get('linkunlinkbuttons', 1);
		$this->linkedUserGroup      = (int) $this->params->get('linkedAccountUserGroup', 0);
		$this->unlinkedUserGroup    = (int) $this->params->get('noLinkedAccountUserGroup', 0);
	}

	public static function getSubscribedEvents(): array
	{
		return [
			'onAfterInitialise'    => 'onAfterInitialise',
			'onAjaxSociallogin'    => 'onAjaxSociallogin',
			'onUserLoginButtons'   => 'onUserLoginButtons',
			'onContentPrepareData' => 'onContentPrepareData',
			'onContentPrepareForm' => 'onContentPrepareForm',
			'onUserAfterSave'      => 'onUserAfterSave',
			'onUserAfterDelete'    => 'onUserAfterDelete',
		];
	}

	/**
	 * Assemble the onAfterInitialise event from code belonging to many features' traits.
	 *
	 * @throws Exception
	 */
	public function onAfterInitialise(Event $e)
	{
		$this->magicRoute();
		$this->onAfterInitialise_DynamicUserGroups($e);
		$this->onAfterIntialise_Ajax($e);
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
	private function isEnabled(): bool
	{
		// Only allow this plugin in the site and admin applications
		if (!$this->app->isClient('site') && !$this->app->isClient('administrator'))
		{
			return false;
		}

		// It only makes sense to let people log in when they are not already logged in ;)
		return (bool) $this->app->getIdentity()->guest;
	}
}