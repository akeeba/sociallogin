<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\Sociallogin\SynologyOIDC\Integration;

use Joomla\CMS\Http\Http;

class UserQuery
{
	private Http $client;

	private string $accessToken;

	private string $userInfoURL;

	public function __construct(Http $client, string $accessToken, string $userInfoURL)
	{
		$this->client      = $client;
		$this->accessToken = $accessToken;
		$this->userInfoURL = $userInfoURL;
	}

	public function getUserInformation(): array
	{
		$headers = [
			'Authorization' => 'Bearer ' . $this->accessToken,
		];

		$response = $this->client->get($this->userInfoURL, $headers);

		if ($response->code > 299)
		{
			throw new \RuntimeException(sprintf("HTTP %s: %s", $response->code, $response->body));
		}

		return json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
	}
}