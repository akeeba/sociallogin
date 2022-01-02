<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Akeeba\SocialLogin\Google;

// Protect from unauthorized access
defined('_JEXEC') || die();

use Akeeba\SocialLogin\Library\OAuth\OAuth2Client;
use Exception;

/**
 * Google OAuth authentication class. Adapted from the Joomla! Framework.
 */
class OAuth2
{
	/**
	 * Options for the Google authentication object.
	 *
	 * @var    array|\ArrayAccess
	 */
	protected $options;

	/**
	 * @var    OAuth2Client  OAuth client for the Google authentication object.
	 */
	protected $client;

	/**
	 * Constructor.
	 *
	 * @param   array|\ArrayAccess  $options  Auth options object.
	 * @param   OAuth2Client        $client   OAuth client for Google authentication.
	 *
	 * @since   1.0
	 */
	public function __construct($options, OAuth2Client $client)
	{
		$this->options = $options;
		$this->client  = $client;
	}

	/**
	 * Method to authenticate to Google
	 *
	 * @return  array  Token on success.
	 *
	 * @since   1.0
	 */
	public function authenticate()
	{
		$this->googlize();

		return $this->client->authenticate();
	}

	/**
	 * Verify if the client has been authenticated
	 *
	 * @return  boolean  Is authenticated
	 *
	 * @since   1.0
	 */
	public function isAuthenticated()
	{
		return $this->client->isAuthenticated();
	}

	/**
	 * Method to retrieve data from Google
	 *
	 * @param   string  $url      The URL for the request.
	 * @param   mixed   $data     The data to include in the request.
	 * @param   array   $headers  The headers to send with the request.
	 * @param   string  $method   The type of http request to send.
	 *
	 * @return  mixed  Data from Google.
	 *
	 * @throws  Exception
	 */
	public function query($url, $data = null, $headers = null, $method = 'get')
	{
		$this->googlize();

		return $this->client->query($url, $data, $headers, $method);
	}

	/**
	 * Get an option from the Auth object.
	 *
	 * @param   string  $key  The name of the option to get.
	 *
	 * @return  mixed  The option value.
	 *
	 */
	public function getOption($key)
	{
		return $this->options[$key] ?? null;
	}

	/**
	 * Set an option for the Auth object.
	 *
	 * @param   string  $key    The name of the option to set.
	 * @param   mixed   $value  The option value to set.
	 *
	 * @return  self  This object for method chaining.
	 */
	public function setOption($key, $value)
	{
		$this->options[$key] = $value;

		return $this;
	}

	/**
	 * Method to fill in Google-specific OAuth settings
	 *
	 * @return  OAuth2Client  Google-configured OAuth2 client.
	 */
	protected function googlize()
	{
		if (!$this->client->getOption('authurl'))
		{
			$this->client->setOption('authurl', 'https://accounts.google.com/o/oauth2/auth');
		}

		if (!$this->client->getOption('tokenurl'))
		{
			$this->client->setOption('tokenurl', 'https://accounts.google.com/o/oauth2/token');
		}

		if (!$this->client->getOption('requestparams'))
		{
			$this->client->setOption('requestparams', []);
		}

		$params = $this->client->getOption('requestparams');

		if (!array_key_exists('access_type', $params))
		{
			$params['access_type'] = 'offline';
		}

		if ($params['access_type'] == 'offline' && $this->client->getOption('userefresh') === null)
		{
			$this->client->setOption('userefresh', true);
		}

		if (!array_key_exists('approval_prompt', $params))
		{
			$params['approval_prompt'] = 'auto';
		}

		$this->client->setOption('requestparams', $params);

		return $this->client;
	}
}
