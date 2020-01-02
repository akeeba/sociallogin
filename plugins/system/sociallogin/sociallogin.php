<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

use Akeeba\SocialLogin\Features\Ajax;
use Akeeba\SocialLogin\Features\ButtonInjection;
use Akeeba\SocialLogin\Features\DynamicUsergroups;
use Akeeba\SocialLogin\Features\UserFields;
use Akeeba\SocialLogin\Library\Helper\Joomla;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Table\Menu;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;

// Prevent direct access
defined('_JEXEC') or die;

// Register the autoloader
JLoader::registerNamespace('Akeeba\\SocialLogin\\Features', __DIR__ . '/Features', false, false, 'psr4');

/**
 * SocialLogin System Plugin
 */
class plgSystemSociallogin extends CMSPlugin
{
	// Load the features, implemented as traits (for easier code management)
	use Ajax, DynamicUsergroups {
		Ajax::onAfterInitialise as protected onAfterIntialise_Ajax;
		DynamicUsergroups::onAfterInitialise as protected onAfterInitialise_DynamicUserGroups;
	}
	use ButtonInjection;
	use UserFields;

	/**
	 * The names of the login modules to intercept. Default: mod_login
	 *
	 * @var   array
	 */
	private $loginModules = array('mod_login');

	/**
	 * Should I intercept the login page of com_users and add social login buttons there?
	 *
	 * @var   bool
	 */
	private $interceptLogin = true;

	/**
	 * Should I add link/unlink buttons in the Edit User Profile page of com_users?
	 *
	 * @var   bool
	 */
	private $addLinkUnlinkButtons = true;

	/**
	 * Should I relocate the social login buttons next to the Login button in the login module?
	 *
	 * @var   bool
	 */
	protected $relocateButton = true;

	/**
	 * CSS selectors for the relocate feature
	 *
	 * @var   array
	 */
	protected $relocateSelectors = [];

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
	 * Are the substitutions enabled?
	 *
	 * @var   bool
	 */
	private $enabled = true;

	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 *
	 * @throws  Exception
	 */
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);

		// Register the autoloader
		JLoader::registerNamespace('Akeeba\\SocialLogin\\Library', __DIR__ . '/Library', false, false, 'psr4');

		// Legacy mappings
		JLoader::registerAlias('SocialLoginHelperIntegrations', 'Akeeba\\SocialLogin\\Library\\Helper\\Integrations', '3.0');

		Joomla::addLogger('system');

		// Am I enabled?
		$this->enabled = $this->isEnabled();

		// Load the language files
		$this->loadLanguage();

		// Get the configured list of login modules and convert it to an actual array
		$isAdminPage           = Joomla::isAdminPage();
		$loginModulesParameter = $isAdminPage ? 'backendloginmodules' : 'loginmodules';
		$defaultModules        = $isAdminPage ? 'none' : 'mod_login';
		$loginModules          = $this->params->get($loginModulesParameter);
		$loginModules          = trim($loginModules);
		$loginModules          = empty($loginModules) ? $defaultModules : $loginModules;
		$loginModules          = explode(',', $loginModules);
		$this->loginModules    = array_map('trim', $loginModules);

		// Load the other plugin parameters
		$this->interceptLogin       = $this->params->get('interceptlogin', 1);
		$this->addLinkUnlinkButtons = $this->params->get('linkunlinkbuttons', 1);
		$this->relocateButton       = $this->params->get('relocate', 1) == 1;
		$this->relocateSelectors    = explode("\n", str_replace(',', "\n", $this->params->get('relocate_selectors', '')));
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
		$this->onAfterInitialise_DynamicUserGroups();
		$this->onAfterIntialise_Ajax();
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
