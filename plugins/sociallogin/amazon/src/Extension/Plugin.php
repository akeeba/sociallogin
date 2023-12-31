<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Sociallogin\Amazon\Extension;

defined('_JEXEC') || die();

use Akeeba\Plugin\Sociallogin\Amazon\Integration\OAuth as AmazonOAuth;
use Akeeba\Plugin\Sociallogin\Amazon\Integration\UserQuery;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;
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
		return array_merge(parent::getSubscribedEvents(), ['onAjaxAmazon' => 'onSocialLoginAjax']);
	}

	public function init(): void
	{
		$this->fgColor = '';
		$this->bgColor = '';
		parent::init();
		$this->buttonImage = 'plg_sociallogin_amazon/amazon.png';
	}

	protected function getConnector(): AmazonOAuth
	{
		if (is_null($this->connector))
		{
			$options         = [
				'clientid'      => $this->appId,
				'clientsecret'  => $this->appSecret,
				'redirecturi'   => Uri::base() . 'index.php?option=com_ajax&group=sociallogin&plugin='
				                   . $this->integrationName . '&format=raw',
				'requestparams' => ['state' => $this->getApplication()->getSession()->getToken()],
			];
			$httpClient      = HttpFactory::getHttp();
			$this->connector = new AmazonOAuth(
				$options, $httpClient, $this->getApplication()->input, $this->getApplication()
			);
			$this->connector->setScope('profile');
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
		$userData->name     = $socialProfile['name'] ?? '';
		$userData->id       = $socialProfile['user_id'] ?? '';
		$userData->email    = $socialProfile['email'] ?? '';
		$userData->verified = true;

		return $userData;
	}

}