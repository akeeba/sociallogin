<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
namespace Joomla\Plugin\Sociallogin\Twitter\Extension;

defined('_JEXEC') || die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Plugin\Sociallogin\Twitter\Integration\OAuth;
use Joomla\Plugin\System\SocialLogin\Library\Data\UserData;
use Joomla\Plugin\System\SocialLogin\Library\Exception\Login\LoginError;
use Joomla\Plugin\System\SocialLogin\Library\Plugin\AbstractPlugin;

if (!class_exists(AbstractPlugin::class, true))
{
	return;
}

/**
 * Akeeba Social Login plugin for Twitter integration
 */
class Plugin extends AbstractPlugin
{
	/** @inheritDoc */
	public static function getSubscribedEvents(): array
	{
		return array_merge(
			parent::getSubscribedEvents(),
			[
				'onAjaxTwitter'             => 'onSocialLoginAjax',
				'onSocialLoginAuthenticate' => 'onSocialLoginAuthenticate',
			]
		);
	}

	/** @inheritDoc */
	public function init(): void
	{
		$this->fgColor = '#FFFFFF';
		$this->bgColor = '#1DA1F2';

		parent::init();

		// Per-plugin customization
		$this->buttonImage = 'plg_sociallogin_twitter/twitter.svg';
	}

	/**
	 * Initiate the user authentication (steps 1 & 2 per the Twitter documentation). Step 3 in the documentation is
	 * Twitter calling back our site, i.e. the call to the onAjaxTwitter method.
	 *
	 * @throws Exception
	 * @see          https://dev.twitter.com/web/sign-in/implementing
	 *
	 * @noinspection PhpUnused
	 */
	public function onSocialLoginAuthenticate(Event $event): void
	{
		/**
		 * @var string $slug The slug of the integration method being called.
		 */
		[$slug] = $event->getArguments();

		// Make sure we are properly set up
		if (!$this->isProperlySetUp())
		{
			return;
		}

		// Make sure it's our integration
		if ($slug != $this->integrationName)
		{
			return;
		}

		// Perform the user redirection
		$this->getConnector()->authenticate();
	}

	/**
	 * Returns an OAuth object
	 *
	 * @return  OAuth
	 *
	 * @throws Exception
	 */
	protected function getConnector(): OAuth
	{
		if (is_null($this->connector))
		{
			$options = [
				'callback'        => Uri::base() . 'index.php?option=com_ajax&group=sociallogin&plugin=' . $this->integrationName . '&format=raw',
				'consumer_key'    => $this->appId,
				'consumer_secret' => $this->appSecret,
				'sendheaders'     => true,
			];

			$httpClient      = HttpFactory::getHttp();
			$this->connector = new OAuth($options, $httpClient, $this->getApplication()->input, $this->getApplication());
		}

		return $this->connector;
	}

	/**
	 * Return the URL for the login button
	 *
	 * @return  string
	 *
	 * @throws  Exception
	 */
	protected function getLoginButtonURL(): string
	{
		/**
		 * Authentication has to go through a special com_ajax URL since the OAuth1 client needs to performs a Twitter
		 * server query and then a redirection to authenticate the user.
		 */
		$token = Factory::getApplication()->getSession()->getToken();

		return Uri::base() . 'index.php?option=com_ajax&group=system&plugin=sociallogin&format=raw&akaction=authenticate&encoding=redirect&slug=' . $this->integrationName . '&' . $token . '=1';
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
		/** @var OAuth $connector */
		$token = $connector->getToken();

		$parameters    = [
			'oauth_token' => $token['key'],
		];
		$data          = [
			'skip_status'   => 'true',
			'include_email' => 'true',
		];
		$path          = 'https://api.twitter.com/1.1/account/verify_credentials.json';
		$response      = $connector->oauthRequest($path, 'GET', $parameters, $data);
		$twitterFields = json_decode($response->body, true);

		if ($response->code != 200)
		{
			throw new LoginError(Text::_('PLG_SOCIALLOGIN_TWITTER_ERROR_NOT_LOGGED_IN_TWITTER'));
		}

		return $twitterFields;
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
		$userData->name     = $socialProfile['name'];
		$userData->id       = $socialProfile['id'];
		$userData->timezone = $socialProfile['utc_offset'] / 3600;
		$userData->email    = '';
		$userData->verified = false;

		if (isset($socialProfile['email']) && !empty($socialProfile['email']))
		{
			$userData->email    = $socialProfile['email'];
			$userData->verified = true;
		}

		return $userData;
	}

}
