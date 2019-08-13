<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
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

	private static $endpoint = 'https://api.linkedin.com/v2';

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
	 * Get information about the currently logged in user.
	 *
	 * @return  array  See above.
	 *
	 * @see https://docs.microsoft.com/en-us/linkedin/consumer/integrations/self-serve/sign-in-with-linkedin
	 */
	public function getUserInformation()
	{
		$path = '/me';

		$headers  = array(
			'Authorization' => 'Bearer ' . $this->token
		);

		$reply    = $this->client->get(self::$endpoint . $path, $headers);

		if ($reply->code > 299)
		{
			throw new \RuntimeException("HTTP {$reply->code}: {$reply->body}");
		}

		$response = json_decode($reply->body, true);

		return $response;
	}

	/**
	 * Get the email information about the currently logged in user.
	 *
	 * @return  array
	 *
	 * @see https://docs.microsoft.com/en-us/linkedin/consumer/integrations/self-serve/sign-in-with-linkedin
	 */
	public function getEmailAddress()
	{
		$path = '/emailAddress?q=members&projection=(elements*(handle~))';

		$headers  = array(
			'Authorization' => 'Bearer ' . $this->token
		);

		$reply    = $this->client->get(self::$endpoint . $path, $headers);

		if ($reply->code > 299)
		{
			throw new \RuntimeException("HTTP {$reply->code}: {$reply->body}");
		}

		$response = json_decode($reply->body, true);

		return $response;
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
		$path = '/me?projection=(id,firstName,lastName,profilePicture(displayImage~:playableStreams))';

		$headers  = array(
			'Authorization' => 'Bearer ' . $this->token
		);

		$reply    = $this->client->get(self::$endpoint . $path, $headers);

		if ($reply->code > 299)
		{
			throw new \RuntimeException("HTTP {$reply->code}: {$reply->body}");
		}

		$response = json_decode($reply->body, true);

		return $response->pictureUrl;
	}

}
