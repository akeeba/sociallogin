<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Sociallogin\Auth0\Extension;

defined('_JEXEC') || die();

use Akeeba\Plugin\Sociallogin\Auth0\Integration\OAuth as Auth0OAuth;
use Akeeba\Plugin\Sociallogin\Auth0\Integration\UserQuery;
use Akeeba\Plugin\System\SocialLogin\Library\Data\UserData;
use Akeeba\Plugin\System\SocialLogin\Library\Plugin\AbstractPlugin;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Cache\CacheControllerFactoryAwareTrait;
use Joomla\CMS\Uri\Uri;
use Joomla\Http\HttpFactory;
use Joomla\Registry\Registry;

if (!class_exists(AbstractPlugin::class, true))
{
	return;
}

class Plugin extends AbstractPlugin
{
	use CacheControllerFactoryAwareTrait;

	private ?string $domain;

	/** @inheritDoc */
	public static function getSubscribedEvents(): array
	{
		return array_merge(
			parent::getSubscribedEvents(),
			[
				'onAjaxAuth0' => 'onSocialLoginAjax',
			]
		);
	}

	/** @inheritDoc */
	public function init(): void
	{
		$this->bgColor = '#FFFFFF';
		$this->fgColor = '#2F2F2F';
		$this->icon    = 'fa fa-key fa-fw me-1';

		parent::init();

		// Per-plugin customization
		$this->domain = $this->params->get('domain') ?: null;
	}

	/**
	 * Returns a GitHubOAuth object
	 *
	 * @return  Auth0OAuth
	 *
	 * @throws  Exception
	 */
	protected function getConnector(): Auth0OAuth
	{
		if (!is_null($this->connector))
		{
			return $this->connector;
		}

		/** @var CMSApplication $application */
		$application     = $this->getApplication();
		$options         = [
			'clientid'     => $this->appId,
			'clientsecret' => $this->appSecret,
			'redirecturi'  => sprintf(
				"%sindex.php?option=com_ajax&group=sociallogin&plugin=%s&format=raw",
				Uri::root(),
				$this->integrationName
			),
			'domain'    => $this->domain,
			'authurl'  => sprintf(
				"https://%s/authorize",
				$this->domain
			),
			'tokenurl'  => sprintf(
				"https://%s/oauth/token",
				$this->domain
			),
			'scope'        => 'email openid',
		];
		$httpClient      = (new HttpFactory())->getHttp();
		$this->connector = new Auth0OAuth($options, $httpClient, $application->getInput(), $application);

		return $this->connector;
	}

	protected function getSocialNetworkProfileInformation(object $connector): ?array
	{
		try
		{
			$tokenArray   = $connector->getToken();
			$options      = new Registry(['userAgent' => 'Akeeba-Social-Login']);
			$client       = (new HttpFactory())->getHttp($options);
			$ghUserQuery  = new UserQuery(
				$client, $tokenArray['access_token'], sprintf("https://%s/userinfo", $this->domain)
			);
			$ghUserFields = $ghUserQuery->getUserInformation();

			return (array) $ghUserFields;
		}
		catch (\Throwable $e)
		{
			return null;
		}
	}

	protected function mapSocialProfileToUserData(array $socialProfile): UserData
	{
		$userData           = new UserData();
		$userData->name     = $socialProfile['username'] ?? '';
		$userData->id       = $socialProfile['sub'] ?? '';
		$userData->email    = $socialProfile['email'] ?? '';
		$userData->verified = true;

		return $userData;
	}

	protected function isProperlySetUp(): bool
	{
		return parent::isProperlySetUp() && !empty($this->domain);
	}


}
