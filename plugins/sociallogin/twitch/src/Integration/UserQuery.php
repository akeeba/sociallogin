<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Sociallogin\Twitch\Integration;

defined('_JEXEC') || die();

use Joomla\CMS\Http\Http;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
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
		$reply   = $this->client->get(self::$endpoint, $headers);
		if ($reply->code > 299)
		{
			throw new RuntimeException("HTTP {$reply->code}: {$reply->body}");
		}

		return json_decode($reply->body);
	}

}