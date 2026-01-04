<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Sociallogin\Yahoo\Integration;

defined('_JEXEC') || die();

use RuntimeException;

class UserQuery
{

	private static $endpoint = 'https://api.login.yahoo.com/openid/v1/userinfo';

	protected $client;

	protected $token;

	public function __construct($client = null, $token = null)
	{
		$this->client = $client;
		$this->token  = $token;
	}

	public function getUserAvatarUrl()
	{
		$info = $this->getUserInformation();
		if (!isset($info->profile_images))
		{
			return '';
		}

		return $info->profile_images->image64;
	}

	public function getUserInformation()
	{
		$headers = ['Authorization' => 'Bearer ' . $this->token];
		$response   = $this->client->get(self::$endpoint, $headers);

		if ($response->getStatusCode() > 299)
		{
			throw new RuntimeException(sprintf(
				"HTTP %s: %s",
				$response->getStatusCode(),
				(string) $response->getBody()
			));
		}

		return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
	}

}