<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Sociallogin\Amazon\Integration;

defined('_JEXEC') || die();

use Joomla\CMS\Http\Http;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use RuntimeException;

class UserQuery
{

	private static $endpoint = 'https://api.amazon.com/user/profile';

	protected $client;

	protected $token;

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
		$headers = ['Authorization' => 'Bearer ' . $this->token];
		$reply   = $this->client->get(self::$endpoint, $headers);
		if ($reply->code > 299)
		{
			throw new RuntimeException("HTTP {$reply->code}: {$reply->body}");
		}

		return json_decode($reply->body);
	}

}