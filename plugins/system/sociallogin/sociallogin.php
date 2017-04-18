<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

/**
 * SocialLogin System Plugin
 */
class plgSystemSociallogin extends JPlugin
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
	 */
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);

		// Register helper classes
		JLoader::register('SocialLoginHelperAjax', __DIR__ . '/helper/ajax.php');
		JLoader::register('SocialLoginHelperIntegrations', __DIR__ . '/helper/integrations.php');
		JLoader::register('SocialLoginHelperJoomla', __DIR__ . '/helper/joomla.php');
		JLoader::register('SocialLoginHelperLogin', __DIR__ . '/helper/login.php');
		JLoader::register('SocialLoginFailedLoginException', __DIR__ . '/helper/exception/login.php');
		JLoader::register('SocialLoginGenericMessageException', __DIR__ . '/helper/exception/generic.php');
		JLoader::register('SocialLoginPluginConfiguration', __DIR__ . '/helper/data/pluginconfig.php');
		JLoader::register('SocialLoginUserData', __DIR__ . '/helper/data/userdata.php');

		// Am I enabled?
		$this->enabled = $this->isEnabled();

		if (!$this->enabled)
		{
			return;
		}

		// Load the language files
		$this->loadLanguage();

		// Get the configured list of login modules and convert it to an actual array
		$loginModulesParameter = SocialLoginHelperJoomla::isAdminPage() ? 'backendloginmodules' : 'loginmodules';
		$loginModules = $this->params->get($loginModulesParameter);
		$loginModules = trim($loginModules);
		$loginModules = empty($loginModules) ? 'mod_login' : $loginModules;
		$loginModules = explode(',', $loginModules);
		$this->loginModules = array_map('trim', $loginModules);

		// Load the other plugin parameters
		$this->interceptLogin = $this->params->get('interceptlogin', 1);
		$this->addLinkUnlinkButtons = $this->params->get('linkunlinkbuttons', 1);
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
	 */
	public function onAfterInitialise()
	{
		// Make sure this is the backend of the site...
		if (!SocialLoginHelperJoomla::isAdminPage())
		{
			return;
		}

		// ...and we are not already logged in...
		if (!JFactory::getUser()->guest)
		{
			return;
		}

		$input = JFactory::getApplication()->input;

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

		JPluginHelper::importPlugin('sociallogin', $plugin);
		$methodName = 'onAjax' . ucfirst($plugin);

		JFactory::getApplication()->triggerEvent($methodName);
	}

	/**
	 * Intercepts module rendering, appending the Social Login buttons to the configured login modules.
	 *
	 * @param   object  $module   The module being rendered
	 * @param   object  $attribs  The module rendering attributes
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
			$docType = JFactory::getApplication()->getDocument()->getType();

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
		$socialLoginButtons = SocialLoginHelperIntegrations::getSocialLoginButtons();
		$module->content    .= $socialLoginButtons;
	}

	/**
	 * Processes the callbacks from social login buttons.
	 *
	 * Note: this method is called from Joomla's com_ajax, not com_sociallogin itself
	 *
	 * @return  void
	 */
	public function onAjaxSociallogin()
	{
		$ajax  = new SocialLoginHelperAjax();
		$app   = JFactory::getApplication();
		$input = $app->input;

		// Get the return URL from the session
		$session = JFactory::getSession();
		$returnURL = $session->get('returnUrl', JUri::base(), 'plg_system_sociallogin');
		$session->set('returnUrl', null, 'plg_system_sociallogin');
		$result = null;

		try
		{
			$result = $ajax->handle($app);
		}
		catch (Exception $e)
		{
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
					echo json_encode($result);

					break;

				case 'jsonhash':
					echo '###' . json_encode($result) . '###';

					break;

				case 'raw':
					echo $result;

					break;

				case 'redirect':
					if (isset($result['message']))
					{
						$type = isset($result['type']) ? $result['type'] : 'info';
						$app->enqueueMessage($result['message'], $type);
					}

					if (isset($result['url']))
					{
						$app->redirect($result['url']);
					}

					$app->redirect($result);

					return;
					break;
			}

			$app->close(200);
		}

		$app->redirect($returnURL);
	}

	/**
	 * Adds additional fields to the user editing form
	 *
	 * @param   JForm  $form  The form to be altered.
	 * @param   mixed  $data  The associated data for the form.
	 *
	 * @return  boolean
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

		if (!SocialLoginHelperJoomla::isAdminPage() && (JFactory::getApplication()->input->getCmd('layout', 'default') != 'edit'))
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

		$user = JFactory::getUser($id);

		// Make sure the loaded user is the correct one
		if ($user->id != $id)
		{
			return true;
		}

		// Make sure I am either editing myself OR I am a Super User
		if (!SocialLoginHelperJoomla::canEditUser($user))
		{
			return true;
		}

		// Add the fields to the form. The custom Sociallogin field uses the SocialLoginHelperIntegrations to render the buttons.
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

		$userId	= JArrayHelper::getValue($user, 'id', 0, 'int');

		if ($userId)
		{
			$db = JFactory::getDbo();

			$query = $db->getQuery(true)
			            ->delete($db->qn('#__user_profiles'))
			            ->where($db->qn('user_id').' = '.$db->q($userId))
			            ->where($db->qn('profile_key').' LIKE '.$db->q('sociallogin.%', false));

			$db->setQuery($query)->execute();
		}

		return true;
	}

	/**
	 * Should I enable the substitutions performed by this plugin?
	 *
	 * @return  bool
	 */
	private function isEnabled()
	{
		// It only make sense to let people log in when they are not already logged in ;)
		if (!JFactory::getUser()->guest)
		{
			return false;
		}

		return true;
	}
}
