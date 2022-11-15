<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
namespace Joomla\Plugin\Sociallogin\Facebook\Extension;

defined('_JEXEC') || die();

use Exception;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Sociallogin\Facebook\Integration\OAuth as FacebookOAuth;
use Joomla\Plugin\Sociallogin\Facebook\Integration\User as FacebookUser;
use Joomla\Plugin\System\SocialLogin\Library\Data\UserData;
use Joomla\Plugin\System\SocialLogin\Library\Plugin\AbstractPlugin;
use Joomla\Registry\Registry;

if (!class_exists(AbstractPlugin::class, true))
{
	return;
}

/**
 * Akeeba Social Login plugin for Facebook integration
 */
class Plugin extends AbstractPlugin
{
	/** @inheritDoc */
	public static function getSubscribedEvents(): array
	{
		return array_merge(
			parent::getSubscribedEvents(),
			[
				'onAjaxFacebook' => 'onSocialLoginAjax',
			]
		);
	}

	/** @inheritDoc */
	public function init(): void
	{
		$this->fgColor = '#FFFFFF';
		$this->bgColor = '#1877F2';

		parent::init();

		// Per-plugin customization
		$this->buttonImage = 'plg_sociallogin_facebook/facebook_logo.svg';
	}

	/**
	 * Returns a FacebookOAuth object
	 *
	 * @return  FacebookOAuth
	 *
	 * @throws  Exception
	 */
	protected function getConnector(): FacebookOAuth
	{
		if (is_null($this->connector))
		{
			$options         = [
				'clientid'     => $this->appId,
				'clientsecret' => $this->appSecret,
				'redirecturi'  => Uri::base() . 'index.php?option=com_ajax&group=sociallogin&plugin=' . $this->integrationName . '&format=raw',
			];
			$httpClient      = HttpFactory::getHttp();
			$this->connector = new FacebookOAuth($options, $httpClient, $this->getApplication()->input, $this->getApplication());
			$this->connector->setScope('public_profile,email');
		}

		return $this->connector;
	}

	/**
	 * Get the raw user profile information from the social network.
	 *
	 * @param   object  $connector  The internal connector object.
	 *
	 * @return  array
	 *
	 * @throws  Exception
	 */
	protected function getSocialNetworkProfileInformation(object $connector): array
	{
		$options = new Registry();

		$options->set('api.url', 'https://graph.facebook.com/v2.7/');

		$fbUserApi    = new FacebookUser($options, null, $connector);
		$fbUserFields = $fbUserApi->getUser('me?fields=id,name,email');

		return (array) $fbUserFields;
	}

	/**
	 * Maps the raw social network profile fields retrieved with getSocialNetworkProfileInformation() into a UserData
	 * object we use in the Social Login library.
	 *
	 * @param   array  $socialProfile  The raw social profile fields
	 *
	 * @return  UserData
	 */
	protected function mapSocialProfileToUserData(array $socialProfile): UserData
	{
		$userData        = new UserData();
		$userData->name  = $socialProfile['name'] ?? '';
		$userData->id    = $socialProfile['id'] ?? '';
		$userData->email = $socialProfile['email'] ?? '';
		// DO NOT USE empty() SINCE $userData->email IS A MAGIC PROPERTY (fetched through __get).
		$userData->verified = $userData->email != '';

		return $userData;
	}
}
