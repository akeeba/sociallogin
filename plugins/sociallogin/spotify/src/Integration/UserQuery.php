<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Sociallogin\Spotify\Integration;

use RuntimeException;

defined('_JEXEC') || die();

class UserQuery
{

	private static $endpoint = 'https://api.spotify.com/v1/me';

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

		return $info->picture[0]->url;
	}

	public function getUserInformation()
	{
		$headers = ['Authorization' => 'Bearer ' . $this->token];
		$reply   = $this->client->get(self::$endpoint, $headers);
		if ($reply->code > 299)
		{
			throw new RuntimeException("HTTP {$reply->code}: {$reply->body}");
		}
		$response = json_decode($reply->body, true);

		return $response;
	}

}