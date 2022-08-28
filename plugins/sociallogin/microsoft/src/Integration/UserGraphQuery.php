<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\Sociallogin\Microsoft\Integration;

// Protect from unauthorized access
defined('_JEXEC') || die();

use Joomla\CMS\Http\Http;

/**
 * Implements a query to the currently logged in user through Microsoft Graph API
 */
class UserGraphQuery
{
	private static $endpoint = 'https://graph.microsoft.com/v1.0/';

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
		$path  = '/me';
		$reply = $this->client->get(self::$endpoint . $path, [
			'Authorization' => 'Bearer ' . $this->token,
		]);

		if ($reply->code > 299)
		{
			throw new \RuntimeException("HTTP {$reply->code}: {$reply->body}");
		}

		$response = json_decode($reply->body);

		return $response;
	}
}
