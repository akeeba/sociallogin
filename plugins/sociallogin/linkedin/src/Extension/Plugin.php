<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
namespace Joomla\Plugin\Sociallogin\Linkedin\Extension;

defined('_JEXEC') || die();

use Exception;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Sociallogin\Linkedin\Integration\OAuth as LinkedInOAuth;
use Joomla\Plugin\Sociallogin\Linkedin\Integration\UserQuery;
use Joomla\Plugin\System\SocialLogin\Library\Data\UserData;
use Joomla\Plugin\System\SocialLogin\Library\Plugin\AbstractPlugin;
use Joomla\Registry\Registry;

if (!class_exists(AbstractPlugin::class, true))
{
	return;
}

/**
 * Akeeba Social Login plugin for LinkedIn integration
 */
class Plugin extends AbstractPlugin
{
	/** @inheritDoc */
	public static function getSubscribedEvents(): array
	{
		return array_merge(
			parent::getSubscribedEvents(),
			[
				'onAjaxLinkedin' => 'onSocialLoginAjax',
			]
		);
	}

	/** @inheritDoc */
	public function init(): void
	{
		$this->fgColor = '#FFFFFF';
		$this->bgColor = '#3077B0';

		parent::init();

		// Per-plugin customization
		$this->buttonImage = 'plg_sociallogin_linkedin/linkedin.svg';
	}

	/**
	 * Returns a LinkedInOAuth object
	 *
	 * @return  LinkedInOAuth
	 *
	 * @throws  Exception
	 *
	 * @see  https://docs.microsoft.com/en-us/linkedin/shared/authentication/authorization-code-flow?context=linkedin/consumer/context
	 */
	protected function getConnector(): LinkedInOAuth
	{
		if (is_null($this->connector))
		{
			$options         = [
				'clientid'     => $this->appId,
				'clientsecret' => $this->appSecret,
				'redirecturi'  => Uri::base() . 'index.php?option=com_ajax&group=sociallogin&plugin=' . $this->integrationName . '&format=raw',
			];
			$httpClient      = HttpFactory::getHttp();
			$this->connector = new LinkedInOAuth($options, $httpClient, $this->getApplication()->input, $this->getApplication());
			$this->connector->setScope('r_liteprofile r_emailaddress');
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
	 *
	 * @see  https://docs.microsoft.com/en-us/linkedin/consumer/integrations/self-serve/sign-in-with-linkedin
	 */
	protected function getSocialNetworkProfileInformation(object $connector): array
	{
		$tokenArray = $connector->getToken();

		$options      = new Registry([
			'userAgent' => 'Akeeba-Social-Login',
		]);
		$client       = HttpFactory::getHttp($options);
		$liUserQuery  = new UserQuery($client, $tokenArray['access_token']);
		$liUserFields = $liUserQuery->getUserInformation();
		$emailFields  = $liUserQuery->getEmailAddress();

		return array_merge($liUserFields, $emailFields);
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

		foreach (['localizedFirstName', 'localizedLastName'] as $fieldName)
		{
			if (!isset($socialProfile[$fieldName]))
			{
				continue;
			}

			$nameParts[] = $socialProfile[$fieldName];
		}

		$userData           = new UserData();
		$userData->name     = implode(' ', $nameParts);
		$userData->name     = $userData->name ?: 'John Doe';
		$userData->id       = $socialProfile['id'];
		$userData->verified = false;
		$userData->email    = 'invalid@example.com';

		if (isset($socialProfile['elements']) &&
			isset($socialProfile['elements'][0]) &&
			isset($socialProfile['elements'][0]['handle~']) &&
			isset($socialProfile['elements'][0]['handle~']['emailAddress']))
		{
			$userData->email    = $socialProfile['elements'][0]['handle~']['emailAddress'];
			$userData->verified = true;
		}

		return $userData;
	}
}
