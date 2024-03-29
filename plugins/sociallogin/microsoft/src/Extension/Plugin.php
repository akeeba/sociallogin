<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
namespace Akeeba\Plugin\Sociallogin\Microsoft\Extension;

defined('_JEXEC') || die();

use Exception;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\DispatcherInterface;
use Akeeba\Plugin\Sociallogin\Microsoft\Integration\OAuth as MicrosoftOAuth;
use Akeeba\Plugin\Sociallogin\Microsoft\Integration\UserGraphQuery;
use Akeeba\Plugin\Sociallogin\Microsoft\Integration\UserQuery;
use Akeeba\Plugin\System\SocialLogin\Library\Data\UserData;
use Akeeba\Plugin\System\SocialLogin\Library\Plugin\AbstractPlugin;
use Joomla\Registry\Registry;

if (!class_exists(AbstractPlugin::class, true))
{
	return;
}

/**
 * Akeeba Social Login plugin for Microsoft Live integration
 */
class Plugin extends AbstractPlugin
{
	/**
	 * Is this an Azure AD application? False for Live SDK.
	 *
	 * @var bool
	 */
	protected bool $isAzure = false;

	/** @inheritDoc */
	public static function getSubscribedEvents(): array
	{
		return array_merge(
			parent::getSubscribedEvents(),
			[
				'onAjaxMicrosoft' => 'onSocialLoginAjax',
			]
		);
	}

	/** @inheritDoc */
	public function init(): void
	{
		$this->fgColor = '#FFFFFF';
		$this->bgColor = '#2F2F2F';

		parent::init();

		// Per-plugin customization
		$this->buttonImage = 'plg_sociallogin_microsoft/microsoft_mark.svg';

		// Customization for Microsoft Azure AD vs Live SDK applications
		$this->isAzure   = $this->params->get('apptype', 'live') === 'azure';
		$this->appId     = $this->params->get($this->isAzure ? 'azappid' : 'appid', '');
		$this->appSecret = $this->params->get($this->isAzure ? 'azappsecret' : 'appsecret', '');
	}

	/**
	 * Returns a MicrosoftOAuth object
	 *
	 * @return  MicrosoftOAuth
	 *
	 * @throws  Exception
	 */
	protected function getConnector(): MicrosoftOAuth
	{
		if (is_null($this->connector))
		{
			$appType = $this->params->get('apptype', 'live');

			$options = [
				'clientid'     => $this->appId,
				'clientsecret' => $this->appSecret,
				'redirecturi'  => Uri::base() . 'index.php?option=com_ajax&group=sociallogin&plugin=' . $this->integrationName . '&format=raw',
				'scope'        => 'wl.basic wl.emails wl.signin',
			];


			if ($appType === 'azure')
			{
				$options = [
					'clientid'      => $this->appId,
					'clientsecret'  => $this->appSecret,
					'redirecturi'   => Uri::base() . 'index.php/aksociallogin_finishLogin/microsoft.raw',
					'authurl'       => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
					'tokenurl'      => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
					'scope'         => 'user.read',
					'grant_scope'   => 'user.read',
					'requestparams' => [
						'response_mode' => 'query',
					],
				];
			}

			$httpClient      = HttpFactory::getHttp();
			$this->connector = new MicrosoftOAuth($options, $httpClient, $this->getApplication()->input, $this->getApplication());
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
		$tokenArray = $connector->getToken();

		$options = new Registry([
			'userAgent' => 'Akeeba-Social-Login',
		]);
		$client  = HttpFactory::getHttp($options);

		$className    = $this->isAzure ? UserGraphQuery::class : UserQuery::class;
		$msUserQuery  = new $className($client, $tokenArray['access_token']);
		$msUserFields = $msUserQuery->getUserInformation();

		return json_decode(json_encode($msUserFields), true);
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
		$nameParts = [];

		if (!$this->isAzure)
		{
			if (isset($socialProfile['first_name']))
			{
				$nameParts[] = $socialProfile['first_name'];
			}

			if (isset($socialProfile['last_name']))
			{
				$nameParts[] = $socialProfile['last_name'];
			}

			$name = implode(' ', $nameParts);

			$email = isset($socialProfile['emails']) && isset($socialProfile['emails']['account']) ? $socialProfile['emails']['account'] : '';
		}
		else
		{
			if (isset($socialProfile['givenName']))
			{
				$nameParts[] = $socialProfile['givenName'];
			}

			if (isset($socialProfile['surname']))
			{
				$nameParts[] = $socialProfile['surname'];
			}

			$name = implode(' ', $nameParts);

			if (isset($socialProfile['displayName']))
			{
				$name = $socialProfile['displayName'];
			}

			$email = $socialProfile['mail'] ?? '';

			if (empty($email) && !empty($socialProfile['userPrincipalName'] ?? ''))
			{
				$email = is_string($socialProfile['userPrincipalName']) ? $socialProfile['userPrincipalName'] : '';
			}
		}

		$userData           = new UserData();
		$userData->id       = $socialProfile['id'];
		$userData->name     = $name;
		$userData->email    = $email;
		$userData->verified = true;

		return $userData;
	}
}
