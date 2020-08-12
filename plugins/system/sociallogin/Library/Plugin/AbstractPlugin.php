<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\SocialLogin\Library\Plugin;

use Akeeba\SocialLogin\Library\Data\PluginConfiguration;
use Akeeba\SocialLogin\Library\Data\UserData;
use Akeeba\SocialLogin\Library\Exception\Login\GenericMessage;
use Akeeba\SocialLogin\Library\Exception\Login\LoginError;
use Akeeba\SocialLogin\Library\Helper\Integrations;
use Akeeba\SocialLogin\Library\Helper\Joomla;
use Akeeba\SocialLogin\Library\Helper\Login;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Authentication\Authentication;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Utilities\ArrayHelper;

// Protect from unauthorized access
defined('_JEXEC') or die();

/**
 * Abstract Social Login plugin class
 */
abstract class AbstractPlugin extends CMSPlugin
{
	/**
	 * The CMS application object
	 *
	 * @var  CMSApplication
	 */
	public $app;

	/**
	 * The integration slug used by this plugin.
	 *
	 * @var   string
	 */
	protected $integrationName = '';

	/**
	 * Should I log in users who have not yet linked their social network account to their site account? THIS MAY BE
	 * DANGEROUS (impersonation risk), therefore it is disabled by default.
	 *
	 * @var   bool
	 */
	protected $canLoginUnlinked = false;

	/**
	 * Can I use this integration to create new user accounts? This will happen when someone tries to login through
	 * the social network but their social network account is not linked to a user account yet.
	 *
	 * @var   bool
	 */
	protected $canCreateNewUsers = false;

	/**
	 * Allow the plugin to override Joomla's new user account registration flag. This is useful to prevent new user
	 * accounts from being created _unless_ they have a social network account and use it on your site (force new users
	 * to link their social media accounts).
	 *
	 * @var   bool
	 */
	protected $canCreateAlways = false;

	/**
	 * When creating new users, am I allowed to bypass email verification if the social network reports the user as
	 * verified on their end?
	 *
	 * @var   bool
	 */
	protected $canBypassValidation = true;

	/**
	 * Should I output inline custom CSS in the page header to style this plugin's login, link and unlink buttons?
	 *
	 * @var   bool
	 */
	protected $useCustomCSS = true;

	/**
	 * The icon class to be used in the buttons.
	 *
	 * @var   string
	 */
	protected $iconClass = '';

	/**
	 * Relative media URL to the image used in buttons, e.g. 'plg_sociallogin_foobar/my_logo.png'.
	 *
	 * @var   string
	 */
	protected $buttonImage = '';

	/**
	 * OAuth application ID
	 *
	 * @var   string
	 */
	protected $appId = '';

	/**
	 * OAuth application secret key
	 *
	 * @var   string
	 */
	protected $appSecret = '';

	/**
	 * The OAuth/Oauth2 connector object for this integration
	 *
	 * @var   object
	 */
	protected $connector;

	/**
	 * Constructor. Loads the language files as well.
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array    $config   An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 */
	public function __construct($subject, array $config = [])
	{
		parent::__construct($subject, $config);

		// Load the language files
		$this->loadLanguage();

		// Set the integration name from the plugin name (without the plg_sociallogin_ part, of course)
		$this->integrationName = isset($config['sociallogin.integrationName']) ? $config['sociallogin.integrationName'] : $this->_name;

		// Register a debug log file writer
		Joomla::addLogger($this->integrationName);

		// Load the plugin options into properties
		$this->canLoginUnlinked    = $this->params->get('loginunlinked', false);
		$this->canCreateNewUsers   = $this->params->get('createnew', false);
		$this->canCreateAlways     = $this->params->get('forcenew', true);
		$this->canBypassValidation = $this->params->get('bypassvalidation', true);
		$this->useCustomCSS        = $this->params->get('customcss', true);
		$this->iconClass           = $this->params->get('icon_class', '');
		$this->appId               = $this->params->get('appid', '');
		$this->appSecret           = $this->params->get('appsecret', '');
	}

	/**
	 * Get the information required to render a login / link account button
	 *
	 * @param   string  $loginURL    The URL to be redirected to upon successful login / account link
	 * @param   string  $failureURL  The URL to be redirected to on error
	 *
	 * @return  array
	 *
	 * @throws  Exception
	 */
	public function onSocialLoginGetLoginButton($loginURL = null, $failureURL = null)
	{
		// Make sure we are properly set up
		if (!$this->isProperlySetUp())
		{
			return [];
		}

		// If there's no return URL use the current URL
		if (empty($loginURL))
		{
			$loginURL = Uri::getInstance()->toString([
				'scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'fragment',
			]);
		}

		// If there's no failure URL use the same as the regular return URL
		if (empty($failureURL))
		{
			$failureURL = $loginURL;
		}

		// Save the return URLs into the session
		Joomla::setSessionVar('loginUrl', $loginURL, 'plg_sociallogin_' . $this->integrationName);
		Joomla::setSessionVar('failureUrl', $failureURL, 'plg_sociallogin_' . $this->integrationName);

		// Add custom CSS
		$this->addCustomCSS();

		return [
			// The name of the plugin rendering this button. Used for customized JLayouts.
			'slug'       => $this->integrationName,
			// The href attribute for the anchor tag.
			'link'       => $this->getLoginButtonURL(),
			// The tooltip of the anchor tag.
			'tooltip'    => Joomla::_(sprintf('PLG_SOCIALLOGIN_%s_LOGIN_DESC', $this->integrationName)),
			// What to put inside the anchor tag. Leave empty to put the image returned by onSocialLoginGetIntegration.
			'label'      => Joomla::_(sprintf('PLG_SOCIALLOGIN_%s_LOGIN_LABEL', $this->integrationName)),
			// The image to use if there is no icon class
			'img'        => HTMLHelper::image($this->buttonImage, '', [], true),
			// Raw button image URL
			'rawimage'   => $this->buttonImage,
			// An icon class for the span before the label inside the anchor tag. Nothing is shown if this is blank.
			'icon_class' => $this->iconClass,
		];
	}

	/**
	 * Get the information required to render a link / unlink account button
	 *
	 * @param   User  $user  The user to be linked / unlinked
	 *
	 * @return  array
	 *
	 * @throws  Exception
	 */
	public function onSocialLoginGetLinkButton($user = null)
	{
		// Make sure we are properly set up
		if (!$this->isProperlySetUp())
		{
			return [];
		}

		if (empty($user))
		{
			$user = Joomla::getUser();
		}

		// Get the return URL
		$returnURL = Uri::getInstance()->toString([
			'scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'fragment',
		]);

		// Save the return URL and user ID into the session
		Joomla::setSessionVar('returnUrl', $returnURL, 'plg_system_sociallogin');
		Joomla::setSessionVar('userID', $user->id, 'plg_system_sociallogin');

		if ($this->isLinked($user))
		{
			$token     = Joomla::getToken();
			$unlinkURL = Uri::base() . 'index.php?option=com_ajax&group=system&plugin=sociallogin&format=raw&akaction=unlink&encoding=redirect&slug=' . $this->integrationName . '&' . $token . '=1';

			// Add custom CSS
			$this->addCustomCSS();

			// Render an unlink button
			return [
				// The name of the plugin rendering this button. Used for customized JLayouts.
				'slug'       => $this->integrationName,
				// The type of the button: 'link' or 'unlink'
				'type'       => 'unlink',
				// The href attribute for the anchor tag.
				'link'       => $unlinkURL,
				// The tooltip of the anchor tag.
				'tooltip'    => Joomla::_(sprintf('PLG_SOCIALLOGIN_%s_UNLINK_DESC', $this->integrationName)),
				// What to put inside the anchor tag. Leave empty to put the image returned by onSocialLoginGetIntegration.
				'label'      => Joomla::_(sprintf('PLG_SOCIALLOGIN_%s_UNLINK_LABEL', $this->integrationName)),
				// The image to use if there is no icon class
				'img'        => HTMLHelper::image($this->buttonImage, '', [], true),
				// An icon class for the span before the label inside the anchor tag. Nothing is shown if this is blank.
				'icon_class' => $this->iconClass,
			];
		}

		// Make sure we return to the same profile edit page
		$loginURL = Uri::getInstance()->toString([
			'scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'fragment',
		]);
		Joomla::setSessionVar('loginUrl', $loginURL, 'plg_sociallogin_' . $this->integrationName);
		Joomla::setSessionVar('failureUrl', $loginURL, 'plg_sociallogin_' . $this->integrationName);

		// Add custom CSS
		$this->addCustomCSS();

		return [
			// The name of the plugin rendering this button. Used for customized JLayouts.
			'slug'       => $this->integrationName,
			// The type of the button: 'link' or 'unlink'
			'type'       => 'link',
			// The href attribute for the anchor tag.
			'link'       => $this->getLinkButtonURL(),
			// The tooltip of the anchor tag.
			'tooltip'    => Joomla::_(sprintf('PLG_SOCIALLOGIN_%s_LINK_DESC', $this->integrationName)),
			// What to put inside the anchor tag. Leave empty to put the image returned by onSocialLoginGetIntegration.
			'label'      => Joomla::_(sprintf('PLG_SOCIALLOGIN_%s_LINK_LABEL', $this->integrationName)),
			// The image to use if there is no icon class
			'img'        => HTMLHelper::image($this->buttonImage, '', [], true),
			// An icon class for the span before the label inside the anchor tag. Nothing is shown if this is blank.
			'icon_class' => $this->iconClass,
		];
	}

	/**
	 * Unlink a user account from a social login integration
	 *
	 * @param   string     $slug  The integration to unlink from
	 * @param   User|null  $user  The user to unlink, null to use the current user
	 *
	 * @return  void
	 */
	public function onSocialLoginUnlink($slug, $user = null)
	{
		// Make sure we are properly set up
		if (!$this->isProperlySetUp())
		{
			return;
		}

		// Make sure it's our integration
		if ($slug != $this->integrationName)
		{
			return;
		}

		// Make sure we have a user
		if (is_null($user))
		{
			$user = Joomla::getUser();
		}

		Integrations::removeUserProfileData($user->id, 'sociallogin.' . $this->integrationName);
	}

	/**
	 * Is this integration properly set up and ready for use?
	 *
	 * @return  bool
	 */
	protected function isProperlySetUp()
	{
		return !(empty($this->appId) || empty($this->appSecret));
	}

	/**
	 * Return the URL for the login button
	 *
	 * @return  string
	 *
	 * @throws  Exception
	 */
	protected function getLoginButtonURL()
	{
		// Get a Facebook OAUth2 connector object and retrieve the URL
		$connector = $this->getConnector();

		return $connector->createUrl();
	}

	/**
	 * Returns the OAuth/OAuth2 connector object used by this integration.
	 *
	 * @return  object
	 *
	 * @throws  Exception
	 */
	protected abstract function getConnector();

	/**
	 * Return the URL for the link button
	 *
	 * @return  string
	 *
	 * @throws  Exception
	 */
	protected function getLinkButtonURL()
	{
		return $this->getLoginButtonURL();
	}

	/**
	 * Get the OAuth / OAuth2 token from the social network. Used in the onAjax* handler.
	 *
	 * @return  array|bool  False if we could not retrieve it. Otherwise [$token, $connector]
	 *
	 * @throws  Exception
	 */
	protected function getToken()
	{
		$oauthConnector = $this->getConnector();

		return [$oauthConnector->authenticate(), $oauthConnector];
	}

	/**
	 * Get the raw user profile information from the social network.
	 *
	 * @param   object  $connector  The internal connector object.
	 *
	 * @return  array
	 */
	protected abstract function getSocialNetworkProfileInformation($connector);

	/**
	 * Maps the raw social network profile fields retrieved with getSocialNetworkProfileInformation() into a UserData
	 * object we use in the Social Login library.
	 *
	 * @param   array  $socialProfile  The raw social profile fields
	 *
	 * @return  UserData
	 */
	protected abstract function mapSocialProfileToUserData(array $socialProfile);

	/**
	 * Return the user's profile picture URL given the social network profile fields retrieved with
	 * getSocialNetworkProfileInformation(). Return null if no such thing is supported.
	 *
	 * @param   array  $socialProfile  The raw social profile fields
	 *
	 * @return  string|null
	 */
	protected function getPictureUrl(array $socialProfile)
	{
		return null;
	}

	/**
	 * Is the user linked to the social login account?
	 *
	 * @param   User  $user  The user account we are checking
	 *
	 * @return  bool
	 */
	protected function isLinked($user = null)
	{
		// Make sure we are set up
		if (!$this->isProperlySetUp())
		{
			return false;
		}

		return Login::isLinkedUser($this->integrationName, $user);
	}

	/**
	 * Handles the social network login callback, gets social network user information and performs the Social Login
	 * flow (including logging in non-linked users or creating a new user from the social media profile)
	 *
	 * @throws  Exception
	 */
	protected function onSocialLoginAjax()
	{
		Joomla::log($this->integrationName, 'Begin handing of authentication callback');

		// This is the return URL used by the Link button
		$returnURL = Joomla::getSessionVar('returnUrl', Uri::base(), 'plg_system_sociallogin');
		// And this is the login success URL used by the Login button
		$loginUrl   = Joomla::getSessionVar('loginUrl', $returnURL, 'plg_sociallogin_' . $this->integrationName);
		$failureUrl = Joomla::getSessionVar('failureUrl', $loginUrl, 'plg_sociallogin_' . $this->integrationName);

		// Remove the return URLs from the session
		Joomla::setSessionVar('loginUrl', null, 'plg_sociallogin_' . $this->integrationName);
		Joomla::setSessionVar('failureUrl', null, 'plg_sociallogin_' . $this->integrationName);

		// Try to exchange the code with a token
		$app = $this->app;

		/**
		 * Handle the login callback from the social network. There are three possibilities:
		 *
		 * 1. LoginError exception is thrown. We must go through Joomla's user plugins and let
		 *    them handle the login failure. They MAY change the error response. Then we report that error response to
		 *    the user while redirecting them to the error handler page.
		 *
		 * 2. GenericMessage exception is thrown. We must NOT go through the user
		 *    plugins, this is not a login error. Most likely we have to tell the user to validate their account.
		 *
		 * 3. No exception is thrown. Proceed to the login success page ($loginUrl).
		 */
		try
		{
			try
			{
				Joomla::log($this->integrationName, 'Receive token from the social network', Log::INFO);

				[$token, $connector] = $this->getToken();

				if ($token === false)
				{
					Joomla::log($this->integrationName, 'Received token from social network is invalid or the user has declined application authorization', Log::ERROR);

					$errorMessage = Joomla::_(sprintf('PLG_SOCIALLOGIN_%s_ERROR_NOT_LOGGED_IN_FB', $this->integrationName));

					if (defined('JDEBUG') && JDEBUG)
					{
						$error = $this->app->input->getString('error_description', '');

						if (!empty($error))
						{
							$errorMessage .= "<br/><small>$error</small>";
						}
					}

					throw new LoginError($errorMessage);
				}

				// Get information about the user from the social network
				Joomla::log($this->integrationName, 'Retrieving social network profile information', Log::INFO);

				$socialUserProfile = $this->getSocialNetworkProfileInformation($connector);
			}
			catch (Exception $e)
			{
				Joomla::log($this->integrationName, "Returning login error '{$e->getMessage()}'", Log::ERROR);

				throw new LoginError($e->getMessage());
			}

			Joomla::log($this->integrationName, sprintf("Retrieved information: %s", ArrayHelper::toString($socialUserProfile)));

			// The data used to login or create a user
			$userData = $this->mapSocialProfileToUserData($socialUserProfile);

			// Options which control login and user account creation
			$pluginConfiguration                      = new PluginConfiguration;
			$pluginConfiguration->canLoginUnlinked    = $this->canLoginUnlinked;
			$pluginConfiguration->canCreateAlways     = $this->canCreateAlways;
			$pluginConfiguration->canCreateNewUsers   = $this->canCreateNewUsers;
			$pluginConfiguration->canBypassValidation = $this->canBypassValidation;

			/**
			 * Data to save to the user profile. The first row is the primary key which links the Joomla! user account to
			 * the social media account.
			 */
			$userProfileData = [
				'userid'     => $userData->id,
				'token'      => json_encode($token),
				'pictureUrl' => $this->getPictureUrl($socialUserProfile),
			];

			if (empty($userProfileData['pictureUrl']))
			{
				unset($userProfileData['pictureUrl']);
			}

			Joomla::log($this->integrationName, sprintf("Calling Social Login login handler with the following information: %s", ArrayHelper::toString($userProfileData)));

			Login::handleSocialLogin($this->integrationName, $pluginConfiguration, $userData, $userProfileData);
		}
		catch (LoginError  $e)
		{
			// Log failed login
			$response                = Login::getAuthenticationResponseObject();
			$response->status        = Authentication::STATUS_UNKNOWN;
			$response->error_message = $e->getMessage();

			Joomla::log($this->integrationName, sprintf("Received login failure. Message: %s", $e->getMessage()), Log::ERROR);

			// This also enqueues the login failure message for display after redirection. Look for JLog in that method.
			Login::processLoginFailure($response, null, $this->integrationName);

			$app->redirect($failureUrl);

			return;
		}
		catch (GenericMessage $e)
		{
			// Do NOT go through processLoginFailure. This is NOT a login failure.
			Joomla::log($this->integrationName, sprintf("Report non-login failure message to user: %s", $e->getMessage()), Log::NOTICE);
			Joomla::log($this->integrationName, sprintf("Redirecting to %s", $failureUrl));
			$app->enqueueMessage($e->getMessage(), 'info');
			$app->redirect($failureUrl);

			return;
		}

		Joomla::log($this->integrationName, sprintf("Successful login. Redirecting to %s", $loginUrl), Log::INFO);
		$app->redirect($loginUrl);
	}

	/**
	 * Adds custom CSS to the page's head unless we're explicitly told not to. The CSS helps render the buttons with the
	 * correct branding color.
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 */
	protected function addCustomCSS()
	{
		// Make sure we only output the custom CSS once
		static $hasOutputCustomCSS = false;

		if ($hasOutputCustomCSS)
		{
			return;
		}

		$hasOutputCustomCSS = true;

		// Am I allowed to add the built-in custom button styling?
		if (!$this->useCustomCSS)
		{
			return;
		}

		// Is this an HTML document?
		$jDocument = $this->app->getDocument();

		if (empty($jDocument) || !is_object($jDocument) || !($jDocument instanceof HtmlDocument))
		{
			return;
		}

		// Load the built-in stylesheet
		$pluginSlug = 'plg_sociallogin_' . strtolower($this->integrationName);
		$stylesheet = sprintf("%s/button.css", $pluginSlug);
		$absoluteFile = sprintf("%s/media/%s/css/button.css", JPATH_ROOT, $pluginSlug);
		$mediaVersion = md5_file($absoluteFile);

		HTMLHelper::_('stylesheet', $stylesheet, [
			'version'  => $mediaVersion,
			'relative' => true,
		]);
	}

}
