<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Sociallogin\Linkedin\Integration;

// Protect from unauthorized access
defined('_JEXEC') || die();

use Joomla\CMS\Http\Http;

/**
 * Implements a query to the currently logged in user through LinkedIn's REST API
 */
class UserQuery
{
	private static $endpoint = 'https://api.linkedin.com/v2';

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
	 * Get information about for the user's LinkedIn avatar
	 *
	 * @return  array
	 *
	 * @see https://docs.microsoft.com/en-us/linkedin/consumer/integrations/self-serve/sign-in-with-linkedin
	 */
	public function getUserAvatarUrl()
	{
		$path = '/userinfo';

		$headers = [
			'Authorization' => 'Bearer ' . $this->token,
		];

		$reply = $this->client->get(self::$endpoint . $path, $headers);

		if ($reply->code > 299)
		{
			throw new \RuntimeException("HTTP {$reply->code}: {$reply->body}");
		}

		$response = json_decode($reply->body, true);

		return $response->picture;
	}

	/**
	 * Get information about the currently logged in user.
	 *
	 * @return  array  See above.
	 *
	 * @see https://docs.microsoft.com/en-us/linkedin/consumer/integrations/self-serve/sign-in-with-linkedin
	 */
	public function getUserInformation()
	{
		$path = '/userinfo';

		$headers = [
			'Authorization' => 'Bearer ' . $this->token,
		];

		$reply = $this->client->get(self::$endpoint . $path, $headers);

		if ($reply->code > 299)
		{
			throw new \RuntimeException("HTTP {$reply->code}: {$reply->body}");
		}

		$response = json_decode($reply->body, true);

		return $response;
	}

}
