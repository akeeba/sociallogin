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
	 * Are the substitutions enabled?
	 *
	 * @var   bool
	 */
	private $enabled = true;

	/**
	 * Cached copy of the social login buttons' HTML
	 *
	 * @var   string
	 */
	private $cachedSocialLoginButtons = null;

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

		if (!class_exists('SocialLoginHelperLogin', true))
		{
			JLoader::register('SocialLoginHelperLogin', __DIR__ . '/helper/login.php');
		}

		// Am I enabled?
		$this->enabled = $this->isEnabled();

		if (!$this->enabled)
		{
			return;
		}

		// Load the language files
		$this->loadLanguage();

		// Get the configured list of login modules and convert it to an actual array
		$loginModules = $this->params->get('loginmodules', 'mod_login');
		$loginModules = trim($loginModules);
		$loginModules = empty($loginModules) ? 'mod_login' : $loginModules;
		$loginModules = explode(',', $loginModules);
		$this->loginModules = array_map('trim', $loginModules);

		// Load the other plugin parameters
		$this->interceptLogin = $this->params->get('itnerceptlogin', 1);
	}

	/**
	 * Intercepts module rendering, appending the Social Login buttons to the configured login modules.
	 *
	 * @param   object  $module   The module being renderd
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
		$socialLoginButtons = $this->getSocialLoginButtons();
		$module->content    .= "<div class=\"akeeba-social-login-buttons\">$socialLoginButtons</div>";
	}

	/**
	 * Replaces the special placeholder {socialloginbuttons} with the social login buttons.
	 */
	public function onBeforeRender()
	{
		// Are we enabled?
		if (!$this->enabled)
		{
			return;
		}

		// Is the document type REALLY 'html'?
		$app       = JFactory::getApplication();
		$jDocument = $app->getDocument();

		if (!is_object($jDocument) || !($jDocument instanceof JDocument))
		{
			return;
		}

		if ($jDocument->getType() != 'html')
		{
			return;
		}

		$buffer = $jDocument->getBuffer();

		// Old Joomla! versions, buffer is a string. We cannot intercept com_users.
		if (is_string($buffer))
		{
			if (strpos($buffer, '{socialloginbuttons') === false)
			{
				return;
			}

			$socialLoginButtons = $this->getSocialLoginButtons();

			$buffer = preg_replace('/{socialloginbuttons(\s)?}/', $socialLoginButtons, $buffer);
			$jDocument->setBuffer($buffer);

			return;
		}

		// Am I intercepting com_users?
		$interceptingLogin = false;

		if ($this->interceptLogin)
		{
			$option = $app->input->getCmd('option');
			$view = $app->input->getCmd('view');

			if ($option == 'com_users')
			{
				if (empty($view) || ($view == 'login'))
				{
					$interceptingLogin = true;
				}
			}
		}

		// New Joomla! versions, buffer is a 3D array
		$socialLoginButtons = $this->getSocialLoginButtons();

		foreach ($buffer as $type => $subBuffer1)
		{
			foreach ($subBuffer1 as $name => $subBuffer2)
			{
				foreach ($subBuffer2 as $title => $content)
				{
					if ($interceptingLogin && ($type == 'component'))
					{
						$content .= '{socialloginbuttons}';
					}

					if (strpos($content, '{socialloginbuttons') === false)
					{
						return;
					}

					$content = preg_replace('/{socialloginbuttons(\s)?}/', $socialLoginButtons, $content);
					$jDocument->setBuffer($content, array(
						'type' => $type,
						'name' => $name,
						'title' => $title,
					));
				}
			}
		}
	}

	/**
	 * Recursively replace the placeholder in the buffer text
	 *
	 * @param   string $buffer
	 *
	 * @return  string
	 */
	private function replacePlaceholderRecursive($buffer)
	{
		if (is_array($buffer))
		{
			$temp = array();

			foreach ($buffer as $k => $v)
			{
				$temp[$k] = $this->replacePlaceholderRecursive($v);
			}

			return $temp;
		}

		if (!is_string($buffer))
		{
			return $buffer;
		}

		if (strpos($buffer, '{socialloginbuttons') === false)
		{
			return $buffer;
		}

		$socialLoginButtons = $this->getSocialLoginButtons();
		return preg_replace('/{socialloginbuttons(\s)?}/', $socialLoginButtons, $buffer);
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

	/**
	 * Gets the Social Login buttons placeholder
	 *
	 * @return  string
	 */
	private function getSocialLoginButtons()
	{
		if (is_null($this->cachedSocialLoginButtons))
		{
			// TODO
			$this->cachedSocialLoginButtons = "<h1>Social login buttons placeholder</h1>";
		}

		return $this->cachedSocialLoginButtons;
	}
}
