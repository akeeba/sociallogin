<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Sociallogin\SynologyOIDC\Extension;

defined('_JEXEC') || die();

use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Cache\CacheControllerFactoryAwareTrait;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;
use Akeeba\Plugin\Sociallogin\SynologyOIDC\Integration\OAuth as SynologyOAuth;
use Akeeba\Plugin\Sociallogin\SynologyOIDC\Integration\UserQuery;
use Akeeba\Plugin\System\SocialLogin\Library\Data\UserData;
use Akeeba\Plugin\System\SocialLogin\Library\OAuth\OpenIDConnectTrait;
use Akeeba\Plugin\System\SocialLogin\Library\Plugin\AbstractPlugin;
use Joomla\Registry\Registry;

if (!class_exists(AbstractPlugin::class, true))
{
	return;
}

class Plugin extends AbstractPlugin
{
	use CacheControllerFactoryAwareTrait;
	use OpenIDConnectTrait;

	private ?string $wellknown;

	/** @inheritDoc */
	public static function getSubscribedEvents(): array
	{
		return array_merge(
			parent::getSubscribedEvents(),
			[
				'onAjaxSynology' => 'onSocialLoginAjax',
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
		$this->wellknown = $this->params->get('wellknown') ?: null;
	}

	/**
	 * Returns a GitHubOAuth object
	 *
	 * @return  SynologyOAuth
	 *
	 * @throws  Exception
	 */
	protected function getConnector(): SynologyOAuth
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
				Uri::base(),
				$this->integrationName
			),
			'wellknown'    => $this->wellknown,
			'scope'        => 'email openid',
		];
		$httpClient      = HttpFactory::getHttp();
		$this->connector = new SynologyOAuth($options, $httpClient, $application->input, $application);

		return $this->connector;
	}

	/**
	 * @inheritDoc
	 */
	protected function getSocialNetworkProfileInformation(object $connector): array
	{
		$tokenArray = $connector->getToken();

		try
		{
			$endpoints = $this->getOIDCEndpoints($this->wellknown);
		}
		catch (Exception $e)
		{
			$endpoints = null;
		}

		if (empty($endpoints))
		{
			return [];
		}

		$options      = new Registry(
			[
				'userAgent' => 'Akeeba-Social-Login',
			]
		);
		$client       = HttpFactory::getHttp($options);
		$ghUserQuery  = new UserQuery($client, $tokenArray['access_token'], $endpoints->userinfourl);
		$ghUserFields = $ghUserQuery->getUserInformation();

		return (array) $ghUserFields;
	}

	/**
	 * @inheritDoc
	 */
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
		$basicCheck = parent::isProperlySetUp() && !empty($this->wellknown);

		if ($basicCheck)
		{
			try
			{
				$endpoints = $this->getOIDCEndpoints($this->wellknown);
			}
			catch (Exception $e)
			{
				return false;
			}

			$basicCheck = !empty($endpoints);
		}

		return $basicCheck;
	}


}