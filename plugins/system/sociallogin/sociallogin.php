<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
use Akeeba\SocialLogin\Library\Helper\Ajax;
use Akeeba\SocialLogin\Library\Helper\Integrations;
use Akeeba\SocialLogin\Library\Helper\Joomla;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Utilities\ArrayHelper;

defined('_JEXEC') or die;

/**
 * SocialLogin System Plugin
 */
class plgSystemSociallogin extends CMSPlugin
{
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

		if (!$this->enabled)
		{
			return;
		}

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
	}

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
	 * Intercepts module rendering, appending the Social Login buttons to the configured login modules.
	 *
	 * @param   object  $module   The module being rendered
	 * @param   object  $attribs  The module rendering attributes
	 *
	 * @throws  Exception
	 */
	public function onRenderModule(&$module, &$attribs)
	{
		if (!$this->enabled)
		{
			return;
		}

		// We need this convoluted check because the JDocument is not initialized on plugin object construction or even
		// during onAfterInitialize. This is the only safe way to determine the document type.
		static $docType = null;

		if (is_null($docType))
		{
			try
			{
				$document = Joomla::getApplication()->getDocument();
			}
			catch (Exception $e)
			{
				$document = null;
			}

			$docType = (is_null($document)) ? 'error' : $document->getType();

			if ($docType != 'html')
			{
				$this->enabled = false;

				return;
			}
		}

		// If it's not a module I need to intercept bail out
		if (!in_array($module->module, $this->loginModules))
		{
			return;
		}

		// Append the social login buttons content
		Joomla::log('system', "Injecting buttons to {$module->module} module.");
		$selectors = empty($this->relocateSelectors) ? [] : $this->relocateSelectors;
		$socialLoginButtons = Integrations::getSocialLoginButtons(null, null, 'akeeba.sociallogin.button', 'akeeba.sociallogin.buttons', null, $this->relocateButton, $selectors);
		$module->content    .= $socialLoginButtons;
	}

	/**
	 * Processes the callbacks from social login buttons.
	 *
	 * Note: this method is called from Joomla's com_ajax, not com_sociallogin itself
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 */
	public function onAjaxSociallogin()
	{
		$ajax  = new Ajax();
		$app   = Joomla::getApplication();
		$input = $app->input;

		// Get the return URL from the session
		$returnURL = Joomla::getSessionVar('returnUrl', JUri::base(), 'plg_system_sociallogin');
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

	/**
	 * Adds additional fields to the user editing form
	 *
	 * @param   JForm  $form  The form to be altered.
	 * @param   mixed  $data  The associated data for the form.
	 *
	 * @return  boolean
	 *
	 * @throws  Exception
	 */
	public function onContentPrepareForm($form, $data)
	{
		if (!$this->addLinkUnlinkButtons)
		{
			return true;
		}

		// Check we are manipulating a valid form.
		if (!($form instanceof JForm))
		{
			return true;
		}

		$name = $form->getName();

		if (!in_array($name, array('com_admin.profile', 'com_users.user', 'com_users.profile', 'com_users.registration')))
		{
			return true;
		}

		if (!Joomla::isAdminPage() && (Joomla::getApplication()->input->getCmd('layout', 'default') != 'edit'))
		{
			return true;
		}

		// Get the user ID
		$id = null;

		if (is_array($data))
		{
			$id = isset($data['id']) ? $data['id'] : null;
		}
		elseif (is_object($data) && is_null($data) && ($data instanceof JRegistry))
		{
			$id = $data->get('id');
		}
		elseif (is_object($data) && !is_null($data))
		{
			$id = isset($data->id) ? $data->id : null;
		}

		$user = Joomla::getUser($id);

		// Make sure the loaded user is the correct one
		if ($user->id != $id)
		{
			return true;
		}

		// Make sure I am either editing myself OR I am a Super User
		if (!Joomla::canEditUser($user))
		{
			return true;
		}

		// Add the fields to the form. The custom Sociallogin field uses the Integrations to render the buttons.
		Joomla::log('system', 'Injecting Social Login fields in user profile edit page');
		$this->loadLanguage();
		JForm::addFormPath(dirname(__FILE__) . '/fields');
		$form->loadFile('sociallogin', false);

		return true;
	}

	/**
	 * Remove all user profile information for the given user ID
	 *
	 * Method is called after user data is deleted from the database
	 *
	 * @param   array   $user     Holds the user data
	 * @param   bool    $success  True if user was successfully stored in the database
	 * @param   string  $msg      Message
	 *
	 * @return  bool
	 *
	 * @throws  Exception
	 */
	public function onUserAfterDelete($user, $success, $msg)
	{
		if (!$success)
		{
			return false;
		}

		if (class_exists('Joomla\\Utilities\\ArrayHelper'))
		{
			$userId = ArrayHelper::getValue($user, 'id', 0, 'int');
		}
		else
		{
			$userId	= JArrayHelper::getValue($user, 'id', 0, 'int');
		}

		if ($userId)
		{
			Joomla::log('system', "Removing Social Login information for deleted user #{$userId}");
			$db = Joomla::getDbo();

			$query = $db->getQuery(true)
			            ->delete($db->qn('#__user_profiles'))
			            ->where($db->qn('user_id').' = '.$db->q($userId))
			            ->where($db->qn('profile_key').' LIKE '.$db->q('sociallogin.%', false));

			$db->setQuery($query)->execute();
		}

		return true;
	}

	/**
	 * Called after a component has finished running, right after Joomla has set the component output to the buffer.
	 * Used to inject our social login buttons in the front-end login page rendered by com_users.
	 *
	 * @return  void
	 */
	public function onAfterDispatch()
	{
		// Are we enabled?
		if (!$this->interceptLogin)
		{
			return;
		}

		// Make sure I can get basic information
		try
		{
			$app     = Joomla::getApplication();
			$user    = Joomla::getUser();
			$isAdmin = Joomla::isAdminPage($app);
			$input   = $app->input;
		}
		catch (Exception $e)
		{
			return;
		}

		// No point showing a login button when you're already logged in
		if (!$user->guest)
		{
			return;
		}

		// I can only operate in frontend pages
		if ($isAdmin)
		{
			return;
		}

		// Make sure this is the Users component
		$option = $input->getCmd('option');

		if ($option !== 'com_users')
		{
			return;
		}

		// Make sure it is the right view / task
		$view = $input->getCmd('view');
		$task = $input->getCmd('task');

		$check1 = is_null($view) && is_null($task);
		$check2 = is_null($view) && ($task === 'login');
		$check3 = ($view === 'login') && is_null($task);

		if (!$check1 && !$check2 && !$check3)
		{
			return;
		}

		// Make sure it's an HTML document
		$document = $app->getDocument();

		if ($document->getType() != 'html')
		{
			return;
		}

		// Get the component output and append our buttons
		$buttons = Integrations::getSocialLoginButtons(null, null, 'akeeba.sociallogin.button', 'akeeba.sociallogin.buttons', null, true);

		$buffer          = $document->getBuffer();

		$componentOutput = $buffer['component'][''][''];
		$componentOutput .= $buttons;
		$document->setBuffer($componentOutput, 'component');
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
