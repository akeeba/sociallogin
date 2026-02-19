<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialLogin\Extension;

// Prevent direct access
defined('_JEXEC') || die;

use Akeeba\Plugin\System\SocialLogin\Features\Ajax;
use Akeeba\Plugin\System\SocialLogin\Features\ButtonInjection;
use Akeeba\Plugin\System\SocialLogin\Features\DynamicUsergroups;
use Akeeba\Plugin\System\SocialLogin\Features\UserFields;
use Akeeba\Plugin\System\SocialLogin\Library\Plugin\AddLoggerTrait;
use Akeeba\Plugin\System\SocialLogin\Library\Plugin\RunPluginsTrait;
use Akeeba\Plugin\System\SocialLogin\Library\Plugin\SocialLoginButtonsTrait;
use Exception;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

class SocialLogin extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
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
	use DatabaseAwareTrait;

	/**
	 * User group ID to add the user to if they have linked social network accounts to their profile
	 *
	 * @var   int
	 * @since 3.0.1
	 */
	protected int $linkedUserGroup = 0;

	/**
	 * User group ID to add the user to if they have NOT linked social network accounts to their profile
	 *
	 * @var   int
	 * @since 3.0.1
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
		$this->redirectBackendCallbackFromFrontend();
		$this->onAfterInitialise_DynamicUserGroups($e);
		$this->onAfterIntialise_Ajax($e);
	}

	/**
	 * Initialise the plugin.
	 *
	 * @return void
	 *
	 * @since  4.3.0
	 */
	public function init(): void
	{
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
		elseif (strpos($path, 'index.php') === 0 && strpos($currentUri->getQuery(), '/aksociallogin_finishLogin') === 0)
		{
			$path = substr($currentUri->getQuery(), 1);
		}

		// Remove the language part on multilingual sites
		if ($this->getApplication()->isClient('site') && $this->getApplication()->getLanguageFilter())
		{
			$languageTag    = $this->getApplication()->getLanguage()->getTag() ?? 'invalid_language';
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

		if (strpos($plugin, 'admin:') === 0)
		{
			[$admin, $plugin] = explode(':', $plugin, 2);
			[$cleanPlugin,] = explode('.', $plugin, 2);

			$pluginDef    = PluginHelper::getPlugin('sociallogin', $cleanPlugin) ?: null;
			$paramsString = !is_object($pluginDef) ? null : ($pluginDef->params ?? null);
			$pParams      = new Registry($paramsString ?: '{}');
			$adminKey     = trim($pParams->get('adminkey', null) ?? '');

			$newUri = rtrim(Uri::root(false), '/') . '/administrator/index.php?/aksociallogin_finishLogin/' .
			          $plugin . '/' . $currentUri->getQuery();

			if ($adminKey)
			{
				$newUri .= '&' . $adminKey;
			}

			$this->getApplication()->redirect($newUri);

			// No-op; this is just to address static code analysis issues.
			return;
		}

		[$plugin, $theRest] = explode('.', $plugin, 2);

		$input = $this->getapplication()->getInput();

		if ($theRest !== 'raw')
		{
			[, $query] = explode('/', $theRest, 2);
			$currentUri->setQuery($query);
			$params = $currentUri->getQuery(true);

			foreach ($params as $k => $v)
			{
				$input->set($k, $v);
			}
		}

		$input->set('option', 'com_ajax');
		$input->set('group', 'sociallogin');
		$input->set('plugin', $plugin);
		$input->set('format', 'raw');

		$currentUri->setPath(rtrim($rootPath, '/') . '/index.php');
		$currentUri->setVar('option', 'com_ajax');
		$currentUri->setVar('group', 'sociallogin');
		$currentUri->setVar('plugin', $plugin);
		$currentUri->setVar('format', 'raw');
	}

	/**
	 * Redirect OAuth callbacks with state=a from the frontend to the admin backend.
	 *
	 * When social login buttons are rendered in the admin backend, the OAuth state parameter is set to 'a'. Since all
	 * providers now use the frontend callback URL (Uri::root()), the callback arrives at the frontend. This method
	 * detects the admin state flag and redirects the request to the admin backend so the login completes there.
	 *
	 * For GET callbacks, a standard redirect is used. For POST callbacks (e.g., Apple's form_post response_mode),
	 * an auto-submitting HTML form is rendered to forward all POST data to the admin backend.
	 *
	 * @return  void
	 * @since   4.11.0
	 */
	protected function redirectBackendCallbackFromFrontend(): void
	{
		$app = $this->getApplication();

		// Only intercept on the site (frontend) application
		if (!$app->isClient('site'))
		{
			return;
		}

		$input = $app->getInput();

		// Must be a com_ajax request for the sociallogin group
		if ($input->getCmd('option') !== 'com_ajax' || $input->getCmd('group') !== 'sociallogin')
		{
			return;
		}

		// Must have the admin state flag
		if ($input->getString('state') !== 'a')
		{
			return;
		}

		$plugin = $input->getCmd('plugin', '');
		$format = $input->getCmd('format', 'raw');

		// Build the admin callback URL
		$adminUrl = Uri::root() . 'administrator/index.php?option=com_ajax&group=sociallogin&plugin='
		            . urlencode($plugin) . '&format=' . urlencode($format);

		if (strtoupper($input->getMethod()) === 'GET')
		{
			// For GET requests, append all query parameters and redirect
			$queryParams = $input->getArray();

			// Remove the params we already have in the base URL
			unset($queryParams['option'], $queryParams['group'], $queryParams['plugin'], $queryParams['format']);

			if (!empty($queryParams))
			{
				$adminUrl .= '&' . http_build_query($queryParams, '', '&');
			}

			$app->redirect($adminUrl);

			return;
		}

		// For POST requests (e.g., Apple Sign In form_post), output an auto-submitting form
		$postData = $input->post->getArray();
		$html     = '<!DOCTYPE html><html><head><title>Redirecting...</title></head><body>';
		$html     .= '<form id="slForm" method="post" action="' . htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8') . '">';

		foreach ($postData as $key => $value)
		{
			if (is_array($value))
			{
				$value = json_encode($value);
			}

			$html .= '<input type="hidden" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8')
			          . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
		}

		$html .= '</form>';
		$html .= '<script>document.getElementById("slForm").submit();</script>';
		$html .= '<noscript><p>Please click the button to continue.</p>';
		$html .= '<button type="submit" form="slForm">Continue</button></noscript>';
		$html .= '</body></html>';

		echo $html;
		$app->close();
	}

	/**
	 * Should I enable the substitutions performed by this plugin?
	 *
	 * @return  bool
	 */
	private function isEnabled(): bool
	{
		// Only allow this plugin in the site and admin applications
		if (!$this->getApplication()->isClient('site') && !$this->getApplication()->isClient('administrator'))
		{
			return false;
		}

		// It only makes sense to let people log in when they are not already logged in ;)
		return (bool) $this->getApplication()->getIdentity()->guest;
	}
}