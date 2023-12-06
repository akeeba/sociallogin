<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Sociallogin\Twitch\Extension;

defined('_JEXEC') || die();

use Exception;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\DispatcherInterface;
use Akeeba\Plugin\Sociallogin\Twitch\Integration\OAuth as TwitchOAuth;
use Akeeba\Plugin\Sociallogin\Twitch\Integration\UserQuery;
use Joomla\Plugin\System\SocialLogin\Library\Data\UserData;
use Joomla\Plugin\System\SocialLogin\Library\Plugin\AbstractPlugin;
use Joomla\Registry\Registry;

if (!class_exists(AbstractPlugin::class, true))
{
	return;
}

class Plugin extends AbstractPlugin
{

	public static function getSubscribedEvents(): array
	{
		return array_merge(parent::getSubscribedEvents(), ['onAjaxTwitch' => 'onSocialLoginAjax']);
	}

	public function init(): void
	{
		$this->fgColor = '';
		$this->bgColor = '';
		parent::init();
		$this->buttonImage = 'plg_sociallogin_twitch/twitch.png';
	}

	protected function getConnector(): TwitchOAuth
	{
		if (is_null($this->connector))
		{
			$options         = [
				'clientid'      => $this->appId,
				'clientsecret'  => $this->appSecret,
				'redirecturi'   => Uri::base() . 'index.php?option=com_ajax&group=sociallogin&plugin='
				                   . $this->integrationName . '&format=raw',
				'requestparams' => [
					'claims' => '{"id_token":{"email":null,"email_verified":null},"userinfo":{"email":null,"email_verified":null,"picture":null,"preferred_username":null,"updated_at":null}}',
					'state'  => $this->getApplication()->getSession()->getToken(),
				],
			];
			$httpClient      = HttpFactory::getHttp();
			$this->connector = new TwitchOAuth(
				$options, $httpClient, $this->getApplication()->input, $this->getApplication()
			);
			$this->connector->setScope('openid user:read:email');
		}

		return $this->connector;
	}

	protected function getSocialNetworkProfileInformation(object $connector): array
	{
		$tokenArray  = $connector->getToken();
		$options     = new Registry(['userAgent' => 'Akeeba-Social-Login']);
		$client      = HttpFactory::getHttp($options);
		$dUserQuery  = new UserQuery($client, $tokenArray['access_token']);
		$dUserFields = $dUserQuery->getUserInformation();

		return (array) $dUserFields;
	}

	protected function mapSocialProfileToUserData(array $socialProfile): UserData
	{
		$userData           = new UserData();
		$userData->name     = $socialProfile['preferred_username'] ?? '';
		$userData->id       = $socialProfile['sub'] ?? '';
		$userData->email    = $socialProfile['email'] ?? '';
		$userData->verified = $socialProfile['email_verified'] ?? false;

		return $userData;
	}

}