<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\SocialLogin\Library\Helper;

// Protect from unauthorized access
use Exception;
use JApplicationBase;
use JApplicationCms;
use JDatabaseDriver;
use JFactory;
use JLayoutFile;
use Joomla\CMS\Application\BaseApplication;
use Joomla\CMS\Application\CliApplication;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\Http;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Mail\Mail;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseDriver;
use Joomla\Registry\Registry;
use JPluginHelper;
use JRegistry;
use JSession;
use JStringPunycode;
use JUser;
use JUserHelper;
use RuntimeException;

defined('_JEXEC') or die();

/**
 * A helper class for abstracting core features in Joomla! 3.4 and later, including 4.x
 */
abstract class Joomla
{
	/**
	 * A fake session storage for CLI apps. Since CLI applications cannot have a session we are using a Registry object
	 * we manage internally.
	 *
	 * @var   JRegistry|Registry
	 */
	protected static $fakeSession = null;

	/**
	 * Are we inside the administrator application
	 *
	 * @var   bool
	 */
	protected static $isAdmin = null;

	/**
	 * Are we inside a CLI application
	 *
	 * @var   bool
	 */
	protected static $isCli = null;

	/**
	 * Are we inside an administrator page?
	 *
	 * @param   \JApplicationCms|CMSApplication $app The current CMS application which tells us if we are inside an admin page
	 *
	 * @return  bool
	 *
	 * @throws  Exception
	 */
	public static function isAdminPage($app = null)
	{
		if (is_null(self::$isAdmin))
		{
			if (is_null($app))
			{
				$app = self::getApplication();
			}

			self::$isAdmin = method_exists($app, 'isClient') ? $app->isClient('administrator') : $app->isAdmin();
		}

		return self::$isAdmin;
	}

	/**
	 * Are we inside a CLI application
	 *
	 * @param   \JApplicationCms|CMSApplication $app The current CMS application which tells us if we are inside an admin page
	 *
	 * @return  bool
	 */
	public static function isCli($app = null)
	{
		if (is_null(self::$isCli))
		{
			if (is_null($app))
			{
				try
				{
					$app = self::getApplication();
				}
				catch (Exception $e)
				{
					$app = null;
				}
			}

			if (is_null($app))
			{
				self::$isCli = true;
			}

			if (is_object($app))
			{
				self::$isCli = $app instanceof \Exception;

				if (class_exists('JApplicationCli'))
				{
					self::$isCli = self::$isCli || $app instanceof \JApplicationCli;
				}

				if (class_exists('Joomla\\CMS\\Application\\CliApplication'))
				{
					self::$isCli = self::$isCli || $app instanceof CliApplication;
				}
			}
		}

		return self::$isCli;
	}

	/**
	 * Is the current user allowed to edit the social login configuration of $user? To do so I must either be editing my
	 * own account OR I have to be a Super User.
	 *
	 * @param   JUser|User $user The user you want to know if we're allowed to edit
	 *
	 * @return  bool
	 */
	public static function canEditUser($user = null)
	{
		// I can edit myself
		if (empty($user))
		{
			return true;
		}

		// Guests can't have social logins associated
		if ($user->guest)
		{
			return false;
		}

		// Get the currently logged in used
		$myUser = self::getUser();

		// Same user? I can edit myself
		if ($myUser->id == $user->id)
		{
			return true;
		}

		// To edit a different user I must be a Super User myself. If I'm not, I can't edit another user!
		if (!$myUser->authorise('core.admin'))
		{
			return false;
		}

		// I am a Super User editing another user. That's allowed.
		return true;
	}

	/**
	 * Helper method to render a JLayout.
	 *
	 * @param   string $layoutFile  Dot separated path to the layout file, relative to base path (plugins/system/sociallogin/layout)
	 * @param   object $displayData Object which properties are used inside the layout file to build displayed output
	 * @param   string $includePath Additional path holding layout files
	 * @param   mixed  $options     Optional custom options to load. Registry or array format. Set 'debug'=>true to output debug information.
	 *
	 * @return  string
	 */
	public static function renderLayout($layoutFile, $displayData = null, $includePath = '', $options = null)
	{
		$basePath = JPATH_SITE . '/plugins/system/sociallogin/layout';
		$layout   = self::getJLayoutFromFile($layoutFile, $options, $basePath);

		if (!empty($includePath))
		{
			$layout->addIncludePath($includePath);
		}

		return $layout->render($displayData);
	}

	/**
	 * Execute a plugin event and return the results
	 *
	 * @param   string                           $event   The plugin event to trigger.
	 * @param   array                            $data    The data to pass to the event handlers.
	 * @param   JApplicationBase|BaseApplication $app     The application to run plugins against,
	 *                                                    default the currently loaded application.
	 *
	 * @return  array  The plugin responses
	 *
	 * @throws  RuntimeException  When we cannot run the plugins
	 * @throws  Exception         When we cannot create the application
	 */
	public static function runPlugins($event, $data, $app = null)
	{
		if (!is_object($app))
		{
			$app = self::getApplication();
		}

		if (method_exists($app, 'triggerEvent'))
		{
			return $app->triggerEvent($event, $data);
		}

		if (class_exists('JEventDispatcher'))
		{
			return \JEventDispatcher::getInstance()->trigger($event, $data);
		}

		throw new RuntimeException('Cannot run plugins');
	}

	/**
	 * Tells Joomla! to load a plugin group.
	 *
	 * This is just a wrapper around JPluginHelper. We use our own helper method for future-proofing...
	 *
	 * @param   string       $group   The plugin group to import
	 * @param   string|null  $plugin  The specific plugin to import
	 *
	 * @return  void
	 */
	public static function importPlugins($group, $plugin = null)
	{
		if (class_exists('Joomla\\CMS\\Plugin\\PluginHelper'))
		{
			PluginHelper::importPlugin($group, $plugin);

			return;
		}

		JPluginHelper::importPlugin($group, $plugin);
	}

	/**
	 * Get the CMS application object
	 *
	 * @return  JApplicationCms|CMSApplication
	 *
	 * @throws  Exception
	 */
	public static function getApplication()
	{
		if (class_exists('Joomla\\CMS\\Factory'))
		{
			return Factory::getApplication();
		}

		return JFactory::getApplication();
	}

	/**
	 * Returns the user, delegates to JFactory/Factory.
	 *
	 * @param   int|null $id The ID of the Joomla! user to load, default null (currently logged in user)
	 *
	 * @return  JUser|User
	 */
	public static function getUser($id = null)
	{
		if (class_exists('Joomla\\CMS\\Factory'))
		{
			return Factory::getUser($id);
		}

		return JFactory::getUser($id);
	}

	/**
	 * Get the Joomla! session
	 *
	 * @return JSession|\Joomla\CMS\Session\Session
	 */
	protected static function getSession()
	{
		if (class_exists('Joomla\\CMS\\Factory'))
		{
			return Factory::getSession();
		}

		return JFactory::getSession();
	}

	/**
	 * Return a Joomla! layout object, creating from a layout file
	 *
	 * @param   string  $layoutFile  Path to the layout file
	 * @param   array   $options     Options to the layout file
	 * @param   string  $basePath    Base path for the layout file
	 *
	 * @return JLayoutFile|FileLayout
	 */
	public static function getJLayoutFromFile($layoutFile, $options, $basePath)
	{
		if (class_exists('Joomla\\CMS\\Layout\\FileLayout'))
		{
			return new FileLayout($layoutFile, $basePath, $options);
		}

		return new JLayoutFile($layoutFile, $basePath, $options);
	}

	/**
	 * Set a variable in the user session
	 *
	 * @param   string  $name       The name of the variable to set
	 * @param   string  $value      (optional) The value to set it to, default is null
	 * @param   string  $namespace  (optional) The variable's namespace e.g. the component name. Default: 'default'
	 *
	 * @return  void
	 */
	public static function setSessionVar($name, $value = null, $namespace = 'default')
	{
		$qualifiedKey = "$namespace.$name";

		if (self::isCli())
		{
			self::getFakeSession()->set($qualifiedKey, $value);

			return;
		}

		if (version_compare(JVERSION, '3.99999.99999', 'lt'))
		{
			self::getSession()->set($name, $value, $namespace);

			return;
		}

		self::getSession()->set($qualifiedKey, $value);
	}

	/**
	 * Get a variable from the user session
	 *
	 * @param   string  $name       The name of the variable to set
	 * @param   string  $default    (optional) The default value to return if the variable does not exit, default: null
	 * @param   string  $namespace  (optional) The variable's namespace e.g. the component name. Default: 'default'
	 *
	 * @return  mixed
	 */
	public static function getSessionVar($name, $default = null, $namespace = 'default')
	{
		$qualifiedKey = "$namespace.$name";

		if (self::isCli())
		{
			return self::getFakeSession()->get("$namespace.$name", $default);
		}

		if (version_compare(JVERSION, '3.99999.99999', 'lt'))
		{
			return self::getSession()->get($name, $default, $namespace);
		}

		return self::getSession()->get($qualifiedKey, $default);
	}

	/**
	 * Unset a variable from the user session
	 *
	 * @param   string  $name       The name of the variable to unset
	 * @param   string  $namespace  (optional) The variable's namespace e.g. the component name. Default: 'default'
	 *
	 * @return  void
	 */
	public static function unsetSessionVar($name, $namespace = 'default')
	{
		self::setSessionVar($name, null, $namespace);
	}

	/**
	 * @return Registry|JRegistry
	 */
	protected static function getFakeSession()
	{
		if (!is_object(self::$fakeSession))
		{
			if (class_exists('Joomla\\Registry\\Registry'))
			{
				self::$fakeSession = new Registry();
			}

			self::$fakeSession = new JRegistry();
		}

		return self::$fakeSession;
	}

	/**
	 * Return the session token. Two types of tokens can be returned:
	 *
	 * @return  mixed
	 */
	public static function getToken()
	{
		// For CLI apps we implement our own fake token system
		if (self::isCli())
		{
			$token = self::getSessionVar('session.token');

			// Create a token
			if (is_null($token))
			{
				$token = self::generateRandom(32);

				self::setSessionVar('session.token', $token);
			}

			return $token;
		}

		// Web application, go through the regular Joomla! API.
		$session = self::getSession();

		return $session->getToken();
	}

	/**
	 * Generate a random string
	 *
	 * @param   int  $length  Random string length
	 *
	 * @return  string
	 */
	public static function generateRandom($length)
	{
		if (class_exists('Joomla\\CMS\\User\\UserHelper'))
		{
			return UserHelper::genRandomPassword($length);
		}

		return JUserHelper::genRandomPassword($length);
	}

	/**
	 * Converts an email to punycode
	 *
	 * @param   string  $email  The original email, with Unicode characters
	 *
	 * @return  string  The punycode-transcribed email address
	 */
	public static function emailToPunycode($email)
	{
		if (class_exists('Joomla\\CMS\\String\\PunycodeHelper'))
		{
			return PunycodeHelper::emailToPunycode($email);
		}

		return JStringPunycode::emailToPunycode($email);
	}

	/**
	 * Is the variable an CMS application object?
	 *
	 * @param   mixed  $app
	 *
	 * @return  bool
	 */
	public static function isCmsApplication($app)
	{
		if (!is_object($app))
		{
			return false;
		}

		if (class_exists('Joomla\\CMS\\Application\\CMSApplication'))
		{
			return $app instanceof CMSApplication;
		}

		return $app instanceof JApplicationCms;
	}

	/**
	 * @return JDatabaseDriver|DatabaseDriver
	 */
	public static function getDbo()
	{
		if (class_exists('Joomla\\CMS\\Factory'))
		{
			return Factory::getDbo();
		}

		return JFactory::getDbo();
	}

	/**
	 * Get the Joomla! global configuration object
	 *
	 * @return JRegistry|Registry
	 */
	public static function getConfig()
	{
		if (class_exists('Joomla\\CMS\\Factory'))
		{
			return Factory::getConfig();
		}

		return JFactory::getConfig();
	}

	/**
	 * Get the Joomla! mailer object
	 *
	 * @return \JMail|Mail
	 */
	public static function getMailer()
	{
		if (class_exists('Joomla\\CMS\\Factory'))
		{
			return Factory::getMailer();
		}

		return JFactory::getMailer();
	}

	public static function getUserId($username)
	{
		if (class_exists('Joomla\\CMS\\User\\UserHelper'))
		{
			return UserHelper::getUserId($username);
		}

		return JUserHelper::getUserId($username);
	}

	/**
	 * Return a translated string
	 *
	 * @param   string  $string  The translation key
	 *
	 * @return  string
	 */
	public static function _($string)
	{
		if (class_exists('Joomla\\CMS\\Language\\Text'))
		{
			return call_user_func_array(array('Joomla\\CMS\\Language\\Text', '_'), array($string));
		}

		return call_user_func_array(array('JText', '_'), array($string));
	}

	/**
	 * Passes a string thru a sprintf.
	 *
	 * Note that this method can take a mixed number of arguments as for the sprintf function.
	 *
	 * The last argument can take an array of options:
	 *
	 * array('jsSafe'=>boolean, 'interpretBackSlashes'=>boolean, 'script'=>boolean)
	 *
	 * where:
	 *
	 * jsSafe is a boolean to generate a javascript safe strings.
	 * interpretBackSlashes is a boolean to interpret backslashes \\->\, \n->new line, \t->tabulation.
	 * script is a boolean to indicate that the string will be push in the javascript language store.
	 *
	 * @param   string  $string  The format string.
	 *
	 * @return  string
	 *
	 * @see     Text::sprintf().
	 */
	public static function sprintf($string)
	{
		$args = func_get_args();

		if (class_exists('Joomla\\CMS\\Language\\Text'))
		{
			return call_user_func_array(array('Joomla\\CMS\\Language\\Text', 'sprintf'), $args);
		}

		return call_user_func_array(array('JText', 'sprintf'), $args);
	}

	/**
	 * Get an HTTP client
	 *
	 * @param   array  $options  The options to pass to the factory when building the client.
	 *
	 * @return  Http|\JHttp
	 */
	public static function getHttpClient(array $options = array())
	{
		if (class_exists('Joomla\\Registry\\Registry'))
		{
			$optionRegistry = new Registry($options);
		}
		else
		{
			$optionRegistry = new JRegistry($options);
		}

		if (class_exists('Joomla\\CMS\\Http\\Http'))
		{
			return HttpFactory::getHttp($optionRegistry);
		}

		return \JHttpFactory::getHttp($optionRegistry);

	}
}
