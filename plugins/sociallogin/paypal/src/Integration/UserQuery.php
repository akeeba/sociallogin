<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Sociallogin\Paypal\Integration;

defined('_JEXEC') || die();

use Joomla\CMS\Http\Http;
use RuntimeException;

class UserQuery
{

	private static $endpoint = 'https://api-m.paypal.com/v1/identity/openidconnect/userinfo?schema=openid';

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

	public function getUserAvatarUrl()
	{
		return '';
	}

	public function getUserInformation()
	{
		$headers = [
			'Authorization' => 'Bearer ' . $this->token,
		];
		$reply   = $this->client->get(self::$endpoint, $headers);

		if ($reply->code > 299)
		{
			throw new RuntimeException("HTTP {$reply->code}: {$reply->body}");
		}

		$response = json_decode($reply->body, true);

		return $response;
	}

}