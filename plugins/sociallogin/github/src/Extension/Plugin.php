<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
namespace Akeeba\Plugin\Sociallogin\Github\Extension;

defined('_JEXEC') || die();

use Akeeba\Plugin\Sociallogin\Github\Integration\OAuth as GitHubOAuth;
use Akeeba\Plugin\Sociallogin\Github\Integration\UserQuery;
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
 * Akeeba Social Login plugin for GitHub integration
 */
class Plugin extends AbstractPlugin
{
	/** @inheritDoc */
	public static function getSubscribedEvents(): array
	{
		return array_merge(
			parent::getSubscribedEvents(),
			[
				'onAjaxGithub' => 'onSocialLoginAjax',
			]
		);
	}

	/** @inheritDoc */
	public function init(): void
	{
		$this->fgColor = '#FFFFFF';
		$this->bgColor = '#000000';

		parent::init();

		// Per-plugin customization
		$this->buttonImage = 'plg_sociallogin_github/octocat.svg';
	}

	/**
	 * Returns a GitHubOAuth object
	 *
	 * @return  GitHubOAuth
	 *
	 * @throws  Exception
	 */
	protected function getConnector(): GitHubOAuth
	{
		if (is_null($this->connector))
		{
			$options         = [
				'clientid'     => $this->appId,
				'clientsecret' => $this->appSecret,
				'redirecturi'  => Uri::root() . 'index.php?option=com_ajax&group=sociallogin&plugin=' . $this->integrationName . '&format=raw',
			];
			$httpClient      = (new HttpFactory())->getHttp();
			$this->connector = new GitHubOAuth($options, $httpClient, $this->getapplication()->getInput(), $this->getApplication());
			$this->connector->setScope('user');
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
			$tokenArray   = $connector->getToken();
			$options      = new Registry(
				[
					'userAgent' => 'Akeeba-Social-Login',
				]
			);
			$client       = (new HttpFactory())->getHttp($options);
			$ghUserQuery  = new UserQuery($client, $tokenArray['access_token']);
			$ghUserFields = $ghUserQuery->getUserInformation();

			return (array) $ghUserFields;
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
		$userData           = new UserData();
		$userData->name     = $socialProfile['name'] ?? '';
		$userData->id       = $socialProfile['id'] ?? '';
		$userData->email    = $socialProfile['email'] ?? '';
		$userData->verified = true;

		return $userData;
	}
}
