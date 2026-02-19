<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
namespace Akeeba\Plugin\Sociallogin\Facebook\Extension;

defined('_JEXEC') || die();

use Akeeba\Plugin\Sociallogin\Facebook\Integration\OAuth as FacebookOAuth;
use Akeeba\Plugin\Sociallogin\Facebook\Integration\User as FacebookUser;
use Akeeba\Plugin\System\SocialLogin\Library\Data\UserData;
use Akeeba\Plugin\System\SocialLogin\Library\Plugin\AbstractPlugin;
use Exception;
use Joomla\CMS\Uri\Uri;
use Joomla\Http\HttpFactory;
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
				'redirecturi'  => Uri::root() . 'index.php?option=com_ajax&group=sociallogin&plugin=' . $this->integrationName . '&format=raw',
			];
			$httpClient      = (new HttpFactory())->getHttp();
			$this->connector = new FacebookOAuth($options, $httpClient, $this->getapplication()->getInput(), $this->getApplication());
			$this->connector->setScope('public_profile,email');
		}

		return $this->connector;
	}

	/**
	 * Get the raw user profile information from the social network.
	 *
	 * @param   object  $connector  The internal connector object.
	 *
	 * @return  array|null
	 *
	 * @throws  Exception
	 */
	protected function getSocialNetworkProfileInformation(object $connector): ?array
	{

		try
		{
			$options = new Registry();
			$options->set('api.url', 'https://graph.facebook.com/v2.7/');
			$fbUserApi    = new FacebookUser($options, null, $connector);
			$fbUserFields = $fbUserApi->getUser('me?fields=id,name,email');

			return (array) $fbUserFields;
		}
		catch (\Throwable $e)
		{
			return null;
		}
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
