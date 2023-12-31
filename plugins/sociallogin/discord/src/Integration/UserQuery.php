<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\Sociallogin\Discord\Integration;

// Protect from unauthorized access
defined('_JEXEC') || die();

use Joomla\CMS\Http\Http;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;

/**
 * Implements a query to the currently logged-in user through Discord's v10 API.
 */
class UserQuery
{
	private static $endpoint = 'https://discord.com/api/v10';

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
	 * Get the URL for the user's Discord avatar
	 *
	 * @param   int  $size  The requested avatar size. Recommended values: 16, 32, 48, 64, 128 and 256.
	 *
	 * @return  string
	 */
	public function getUserAvatarUrl($size)
	{
		$info = $this->getUserInformation();

		if (!isset($info->avatar) || !isset($info->id))
		{
			return '';
		}

		return sprintf("https://cdn.discordapp.com/avatars/%s/%s.png?size=%d", $info->id, $info->avatar, $size);
	}

	/**
	 * Get information about the currently logged-in user. The information returned is:
	 * id             The Discord user ID.
	 * username       The Discord username, not unique across the platform
	 * discriminator  The user's 4-digit discord-tag
	 * avatar         The user's avatar hash
	 * bot            Whether the user belongs to an OAuth2 application
	 * system         Whether the user is an Official Discord System user
	 * mfa_enabled    Whether the user has TFA enabled on their account
	 * banner         The user's banner hash
	 * accent_color   The user's banner color encoded as an integer represantation of the hex color code
	 * locale         The user's language option
	 * verified       Whether the email on this account has been verified
	 * email          The user's email
	 * flags          The flags on a user's account
	 * premium_type   The type of Nitro subscription on a user's account
	 * public_flags   The public flags on a user's account
	 *
	 * @return  \stdClass  See above.
	 */
	public function getUserInformation()
	{
		$headers = [
			'Authorization' => 'Bearer ' . $this->token,
			//'User-Agent' => sprintf('DiscordBot (%s, %s) AkeebaSocialLogin', Uri::base(false), '4.2.0')
		];

		$reply = $this->client->get(self::$endpoint . '/users/@me', $headers);

		if ($reply->code > 299)
		{
			throw new \RuntimeException("HTTP {$reply->code}: {$reply->body}");
		}

		return json_decode($reply->body);
	}

}
