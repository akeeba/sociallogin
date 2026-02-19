<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Sociallogin\Yahoo\Extension;

defined('_JEXEC') || die();

use Akeeba\Plugin\Sociallogin\Yahoo\Integration\OAuth as YahooOAuth;
use Akeeba\Plugin\Sociallogin\Yahoo\Integration\UserQuery;
use Akeeba\Plugin\System\SocialLogin\Library\Data\UserData;
use Akeeba\Plugin\System\SocialLogin\Library\Plugin\AbstractPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Http\HttpFactory;
use Joomla\Registry\Registry;

if (!class_exists(AbstractPlugin::class, true))
{
	return;
}

class Plugin extends AbstractPlugin
{

	public static function getSubscribedEvents(): array
	{
		return array_merge(parent::getSubscribedEvents(), ['onAjaxYahoo' => 'onSocialLoginAjax']);
	}

	public function init(): void
	{
		$this->fgColor = '';
		$this->bgColor = '';
		parent::init();
		$this->buttonImage = 'plg_sociallogin_yahoo/yahoo.png';
	}

	protected function getConnector(): YahooOAuth
	{
		if (is_null($this->connector))
		{
			$options         = [
				'clientid'      => $this->appId,
				'clientsecret'  => $this->appSecret,
				'redirecturi'   => Uri::root() . 'index.php?option=com_ajax&group=sociallogin&plugin='
				                   . $this->integrationName . '&format=raw',
				'requestparams' => ['state' => $this->getApplication()->getSession()->getToken()],
			];
			$httpClient      = (new HttpFactory())->getHttp();
			$this->connector = new YahooOAuth(
				$options, $httpClient, $this->getapplication()->getInput(), $this->getApplication()
			);
			$this->connector->setScope('openid');
		}

		return $this->connector;
	}

	protected function getSocialNetworkProfileInformation(object $connector): ?array
	{
		try
		{
			$tokenArray  = $connector->getToken();
			$options     = new Registry(['userAgent' => 'Akeeba-Social-Login']);
			$client      = (new HttpFactory())->getHttp($options);
			$dUserQuery  = new UserQuery($client, $tokenArray['access_token']);
			$dUserFields = $dUserQuery->getUserInformation();

			return (array) $dUserFields;
		}
		catch (\Throwable $e)
		{
			return null;
		}
	}

	protected function mapSocialProfileToUserData(array $socialProfile): UserData
	{
		$userData           = new UserData();
		$userData->name     = $socialProfile['name'] ?? '';
		$userData->id       = $socialProfile['sub'] ?? '';
		$userData->email    = $socialProfile['email'] ?? '';
		$userData->verified = $socialProfile['email_verified'] ?? false;

		return $userData;
	}

}