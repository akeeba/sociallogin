<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialLogin\Library\OAuth;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Cache\Controller\CallbackController;
use Joomla\CMS\Http\HttpFactory;

trait OpenIDConnectTrait
{
	/**
	 * The cache controller for caching the endpoint URLs of the OpenID Connect server
	 *
	 * @var CallbackController|null
	 * @internal
	 */
	private ?CallbackController $oidcCacheController = null;

	/**
	 * Get the OpenID Connect endpoints given a Well-known URL for the service.
	 *
	 * @param   string  $wellKnownURL
	 *
	 * @return  object|null  Keys: authurl, tokenurl, userinfourl
	 */
	protected function getOIDCEndpoints(string $wellKnownURL): ?object
	{
		$callbackController = $this->getOIDCCacheController();

		if (!$callbackController instanceof CallbackController)
		{
			return null;
		}

		return $callbackController
			->get(
				fn($url) => $this->getOIDCEndpointsFromURL($url), [$wellKnownURL]
			);
	}

	/**
	 * Get, possibly creating afresh, the cache controller for the OpenID Connect endpoints
	 *
	 * @return  CallbackController|null
	 */
	private function getOIDCCacheController(): ?CallbackController
	{
		if ($this->oidcCacheController instanceof CallbackController)
		{
			return $this->oidcCacheController;
		}

		$classBits   = explode('\\', get_class($this));
		$pluginName  = 'plg_sociallogin_' . strtolower($classBits[3]);
		$application = method_exists($this, 'getApplication') ? $this->getApplication() : ($this->application ?? null);

		if (!$application instanceof CMSApplication)
		{
			return null;
		}

		$options = [
			'defaultgroup' => $pluginName,
			'cachebase'    => $application->get('cache_path', JPATH_CACHE),
			'lifetime'     => 86400,
			'language'     => $application->get('language', 'en-GB'),
			'storage'      => $application->get('cache_handler', 'file'),
			'locking'      => true,
			'locktime'     => 15,
			'checkTime'    => true,
			'caching'      => true,
		];

		/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
		$this->oidcCacheController = $this->getCacheControllerFactory()
			->createCacheController('callback', $options);

		return $this->oidcCacheController;
	}

	/**
	 * Get the OpenID Connect endpoint URLs given a Well-known URL describing the service
	 *
	 * @param   string  $wellKnownURL
	 *
	 * @return  object|null
	 */
	private function getOIDCEndpointsFromURL(string $wellKnownURL): ?object
	{
		$http     = HttpFactory::getHttp();
		$response = $http->get($wellKnownURL);

		if ($response->getStatusCode() !== 200)
		{
			return null;
		}

		try
		{
			$info = @json_decode($response->body, false, 512, JSON_THROW_ON_ERROR);
		}
		catch (\JsonException $e)
		{
			return null;
		}

		return (object) [
			'authurl'     => $info->authorization_endpoint ?? null,
			'tokenurl'    => $info->token_endpoint ?? null,
			'userinfourl' => $info->userinfo_endpoint ?? null,
		];
	}
}