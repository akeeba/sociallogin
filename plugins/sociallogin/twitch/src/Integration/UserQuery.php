<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Sociallogin\Twitch\Integration;

defined('_JEXEC') || die();

use RuntimeException;

class UserQuery
{

	private static $endpoint = 'https://id.twitch.tv/oauth2/userinfo';

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

		if (!isset($info->picture))
		{
			return '';
		}

		return $info->picture;
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