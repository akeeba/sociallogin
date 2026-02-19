<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Sociallogin\Github\Integration;

// Protect from unauthorized access
defined('_JEXEC') || die();

use Joomla\Http\Http;
use RuntimeException;

/**
 * Implements a query to the currently logged in user through GitHub's v4 API (which is implemented atop GraphQL).
 */
class UserQuery
{
	private static $endpoint = 'https://api.github.com/graphql';

	/**
	 * The HTTP client object to use in sending HTTP requests.
	 *
	 * @var    Http
	 */
	protected $client;

	/**
	 * The OAuth token
	 *
	 * @var    string
	 */
	protected $token;

	/**
	 * Constructor.
	 *
	 * @param   Http    $client  The HTTP client object.
	 * @param   string  $token   The OAuth token.
	 */
	public function __construct($client = null, $token = null)
	{
		$this->client = $client;
		$this->token  = $token;
	}

	/**
	 * Get the URL for the user's GitHub avatar
	 *
	 * @param   int  $size  The requested avatar size. Recommended values: 16, 32, 48, 64, 128 and 256.
	 *
	 * @return  string
	 */
	public function getUserAvatarUrl($size)
	{
		$size = (int) $size;

		$query = <<< JSON
{
	"query" : "query { viewer { avatarUrl(size: $size) } }"
}

JSON;

		$headers = [
			'Authorization' => 'bearer ' . $this->token,
		];

		$response = $this->client->post(self::$endpoint, $query, $headers);

		if ($response->getStatusCode() > 299)
		{
			throw new RuntimeException(sprintf(
				"HTTP %s: %s",
				$response->getStatusCode(),
				(string) $response->getBody()
			));
		}

		$response = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

		// Validate the response.
		if (isset($response['errors']))
		{
			throw new \RuntimeException($response['errors'][0]['message']);
		}

		return $response['data']['viewer']['avatarUrl'];
	}

	/**
	 * Get information about the currently logged in user. The information returned is:
	 * id           The GitHub user ID.
	 * login        The GitHub username.
	 * name         The full, real name of the GitHub user.
	 * email        The GitHub user's email. May be empty.
	 * avatarUrl    The URL to the 256px wide avatar of the user.
	 *
	 * @return  \stdClass  See above.
	 */
	public function getUserInformation()
	{
		$query = <<< JSON
{
	"query" : "query { viewer { id, name, email, login, avatarUrl(size: 256) } }"
}

JSON;

		$headers = [
			'Authorization' => 'bearer ' . $this->token,
		];

		$response = $this->client->post(self::$endpoint, $query, $headers);

		if ($response->getStatusCode() > 299)
		{
			throw new RuntimeException(sprintf(
				"HTTP %s: %s",
				$response->getStatusCode(),
				(string) $response->getBody()
			));
		}

		$response = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

		// Validate the response.
		if (isset($response['errors']))
		{
			throw new \RuntimeException($response['errors'][0]['message']);
		}

		return $response['data']['viewer'];
	}

}
