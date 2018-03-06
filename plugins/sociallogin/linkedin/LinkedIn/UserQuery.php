<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\SocialLogin\LinkedIn;

// Protect from unauthorized access
defined('_JEXEC') or die();

use Joomla\CMS\Http\Http;

/**
 * Implements a query to the currently logged in user through LinkedIn's REST API
 */
class UserQuery
{
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

	private static $endpoint = 'https://api.linkedin.com/v1';

	/**
	 * Constructor.
	 *
	 * @param   Http   $client The HTTP client object.
	 * @param   string $token  The OAuth token.
	 */
	public function __construct($client = null, $token = null)
	{
		$this->client = $client;
		$this->token  = $token;
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
		$path = '/people/~:(id,first-name,last-name,email-address,picture-url)?format=json';

		$headers  = array(
			'Authorization' => 'Bearer ' . $this->token
		);

		$reply    = $this->client->get(self::$endpoint . $path, $headers);

		if ($reply->code > 299)
		{
			throw new \RuntimeException("HTTP {$reply->code}: {$reply->body}");
		}

		$response = json_decode($reply->body);

		return $response;
	}

	/**
	 * Get the URL for the user's GitHub avatar
	 *
	 * @return  string
	 */
	public function getUserAvatarUrl()
	{
		$path = '/people/~:(picture-url)?format=json';

		$headers  = array(
			'Authorization' => 'Bearer ' . $this->token
		);

		$reply    = $this->client->get(self::$endpoint . $path, $headers);

		if ($reply->code > 299)
		{
			throw new \RuntimeException("HTTP {$reply->code}: {$reply->body}");
		}

		$response = json_decode($reply->body);

		return $response->pictureUrl;
	}

}
