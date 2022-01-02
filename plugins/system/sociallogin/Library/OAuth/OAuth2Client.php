<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Akeeba\SocialLogin\Library\OAuth;

// Protect from unauthorized access
defined('_JEXEC') || die();

use ArrayAccess;
use Exception;
use InvalidArgumentException;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\WebApplication;
use Joomla\CMS\Http\Http;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Http\Response;
use Joomla\CMS\Input\Input;
use RuntimeException;

/**
 * OAuth 2 client.
 *
 * This class is adapter from the Joomla! Framework.
 */
class OAuth2Client
{
	/**
	 * Options for the Client object.
	 *
	 * @var    array|ArrayAccess
	 */
	protected $options;

	/**
	 * The HTTP client object to use in sending HTTP requests.
	 *
	 * @var    Http
	 */
	protected $http;

	/**
	 * The input object to use in retrieving GET/POST data.
	 *
	 * @var    Input
	 */
	protected $input;

	/**
	 * The application object to send HTTP headers for redirects.
	 *
	 * @var    CMSApplication
	 */
	protected $application;

	/**
	 * Constructor.
	 *
	 * @param   array|ArrayAccess  $options      OAuth2 Client options object
	 * @param   Http               $http         The HTTP client object
	 * @param   Input              $input        The input object
	 * @param   CMSApplication     $application  The application object
	 *
	 */
	public function __construct($options = [], $http = null, $input = null, $application = null)
	{
		if (!is_array($options) && !($options instanceof ArrayAccess))
		{
			throw new InvalidArgumentException(
				'The options param must be an array or implement the ArrayAccess interface.'
			);
		}

		$this->options = $options;
		$this->http    = $http ?: HttpFactory::getHttp($this->options);
		$this->input   = $input ?: ($application ? $application->input : new Input());

		$this->application = $application;
	}

	/**
	 * Get the access token or redirect to the authentication URL.
	 *
	 * @return  array|null  The access token
	 *
	 * @throws  RuntimeException
	 */
	public function authenticate()
	{
		if ($data['code'] = $this->input->get('code', false, 'raw'))
		{
			$data['grant_type']    = $this->getOption('grant_type', 'authorization_code');
			$data['redirect_uri']  = $this->getOption('redirecturi');
			$data['client_id']     = $this->getOption('clientid');
			$data['client_secret'] = $this->getOption('clientsecret');

			$grantScope = $this->getOption('grant_scope', '');

			if (!empty($grantScope))
			{
				$data['scope'] = $grantScope;
			}

			$response = $this->http->post($this->getOption('tokenurl'), $data);

			if (!($response->code >= 200 && $response->code < 400))
			{
				throw new RuntimeException('Error code ' . $response->code . ' received requesting access token: ' . $response->body . '.');
			}

			$contentType = '';

			if (isset($response->headers['Content-Type']))
			{
				$contentType = $response->headers['Content-Type'];
			}

			if (isset($response->headers['content-type']))
			{
				$contentType = $response->headers['content-type'];
			}

			$contentType = is_array($contentType) ? array_shift($contentType) : $contentType;

			if (strpos($contentType, 'application/json') !== false)
			{
				$token = array_merge(json_decode($response->body, true), ['created' => time()]);
			}
			else
			{
				parse_str($response->body, $token);
				$token = array_merge($token, ['created' => time()]);
			}

			$this->setToken($token);

			return $token;
		}

		if ($this->getOption('sendheaders'))
		{
			$isValidApplication = false;

			if (class_exists('Joomla\\CMS\\Application\\CMSApplication'))
			{
				$isValidApplication = $this->application instanceof CMSApplication;
			}

			if (class_exists('JApplicationCms'))
			{
				$isValidApplication = $isValidApplication || ($this->application instanceof WebApplication);
			}

			if (!$isValidApplication)
			{
				throw new RuntimeException('CMSApplication/JApplicationCms object required for authentication process.');
			}

			$this->application->redirect($this->createUrl());
		}

		return null;
	}

	/**
	 * Verify if the client has been authenticated
	 *
	 * @return  boolean  Is authenticated
	 *
	 */
	public function isAuthenticated()
	{
		$token = $this->getToken();

		if (!$token || !array_key_exists('access_token', $token))
		{
			return false;
		}

		if (array_key_exists('expires_in', $token) && $token['created'] + $token['expires_in'] < time() + 20)
		{
			return false;
		}

		return true;
	}

	/**
	 * Create the URL for authentication.
	 *
	 * @return  string  The URL for authentication
	 *
	 * @throws  InvalidArgumentException
	 */
	public function createUrl()
	{
		if (!$this->getOption('authurl') || !$this->getOption('clientid'))
		{
			throw new InvalidArgumentException('Authorization URL and client_id are required');
		}

		$url = $this->getOption('authurl');
		$url .= (strpos($url, '?') !== false) ? '&' : '?';
		$url .= 'response_type=code';
		$url .= '&client_id=' . urlencode($this->getOption('clientid'));

		if ($this->getOption('redirecturi'))
		{
			$url .= '&redirect_uri=' . urlencode($this->getOption('redirecturi'));
		}

		if ($this->getOption('scope'))
		{
			$scope = is_array($this->getOption('scope')) ? implode(' ', $this->getOption('scope')) : $this->getOption('scope');

			$url .= '&scope=' . str_replace('+', '%20', urlencode($scope));
		}

		if ($this->getOption('state'))
		{
			$url .= '&state=' . urlencode($this->getOption('state'));
		}

		if (is_array($this->getOption('requestparams')))
		{
			foreach ($this->getOption('requestparams') as $key => $value)
			{
				$url .= '&' . $key . '=' . urlencode($value);
			}
		}

		return $url;
	}

	/**
	 * Send a signed OAuth request.
	 *
	 * @param   string   $url      The URL for the request
	 * @param   mixed    $data     Either an associative array or a string to be sent with the request
	 * @param   array    $headers  The headers to send with the request
	 * @param   string   $method   The method with which to send the request
	 * @param   integer  $timeout  The timeout for the request
	 *
	 * @return  Response|bool
	 *
	 * @throws  InvalidArgumentException
	 * @throws  RuntimeException
	 * @throws  Exception
	 */
	public function query($url, $data = null, $headers = [], $method = 'get', $timeout = null)
	{
		$token = $this->getToken();

		if (array_key_exists('expires_in', $token) && $token['created'] + $token['expires_in'] < time() + 20)
		{
			if (!$this->getOption('userefresh'))
			{
				return false;
			}

			$token = $this->refreshToken($token['refresh_token']);
		}

		if (!$this->getOption('authmethod') || $this->getOption('authmethod') == 'bearer')
		{
			$headers['Authorization'] = 'Bearer ' . $token['access_token'];
		}
		elseif ($this->getOption('authmethod') == 'get')
		{
			if (strpos($url, '?'))
			{
				$url .= '&';
			}
			else
			{
				$url .= '?';
			}

			$url .= $this->getOption('getparam') ?: 'access_token';
			$url .= '=' . $token['access_token'];
		}

		switch ($method)
		{
			case 'head':
			case 'get':
			case 'delete':
			case 'trace':
				$response = call_user_func_array([$this->http, $method], [$url, $headers, $timeout]);
				break;

			case 'post':
			case 'put':
			case 'patch':
				$response = call_user_func_array([$this->http, $method], [$url, $data, $headers, $timeout]);
				break;

			default:
				throw new InvalidArgumentException('Unknown HTTP request method: ' . $method . '.');
		}

		if ($response->code < 200 || $response->code >= 400)
		{
			throw new RuntimeException('Error code ' . $response->code . ' received requesting data: ' . $response->body . '.');
		}

		return $response;
	}

	/**
	 * Get an option from the OAuth2 Client instance.
	 *
	 * @param   string  $key      The name of the option to get
	 * @param   mixed   $default  Optional default value, returned if the requested option does not exist.
	 *
	 * @return  mixed  The option value
	 *
	 */
	public function getOption($key, $default = null)
	{
		return $this->options[$key] ?? $default;
	}

	/**
	 * Set an option for the OAuth2 Client instance.
	 *
	 * @param   string  $key    The name of the option to set
	 * @param   mixed   $value  The option value to set
	 *
	 * @return  self  This object for method chaining
	 *
	 */
	public function setOption($key, $value)
	{
		$this->options[$key] = $value;

		return $this;
	}

	/**
	 * Get the access token from the Client instance.
	 *
	 * @return  array  The access token
	 *
	 */
	public function getToken()
	{
		return $this->getOption('accesstoken');
	}

	/**
	 * Set an option for the Client instance.
	 *
	 * @param   array  $value  The access token
	 *
	 * @return  self  This object for method chaining
	 *
	 */
	public function setToken($value)
	{
		if (is_array($value) && !array_key_exists('expires_in', $value) && array_key_exists('expires', $value))
		{
			$value['expires_in'] = $value['expires'];
			unset($value['expires']);
		}

		$this->setOption('accesstoken', $value);

		return $this;
	}

	/**
	 * Refresh the access token instance.
	 *
	 * @param   string  $token  The refresh token
	 *
	 * @return  array  The new access token
	 *
	 * @throws  Exception
	 * @throws  RuntimeException
	 */
	public function refreshToken($token = null)
	{
		if (!$this->getOption('userefresh'))
		{
			throw new RuntimeException('Refresh token is not supported for this OAuth instance.');
		}

		if (!$token)
		{
			$token = $this->getToken();

			if (!array_key_exists('refresh_token', $token))
			{
				throw new RuntimeException('No refresh token is available.');
			}

			$token = $token['refresh_token'];
		}

		$data['grant_type']    = 'refresh_token';
		$data['refresh_token'] = $token;
		$data['client_id']     = $this->getOption('clientid');
		$data['client_secret'] = $this->getOption('clientsecret');
		$response              = $this->http->post($this->getOption('tokenurl'), $data);

		if (!($response->code >= 200 || $response->code < 400))
		{
			throw new Exception('Error code ' . $response->code . ' received refreshing token: ' . $response->body . '.');
		}

		$contentType = $response->headers['Content-Type'];
		$contentType = is_array($contentType) ? array_shift($contentType) : $contentType;

		if (strpos($contentType, 'application/json') !== false)
		{
			$token = array_merge(json_decode($response->body, true), ['created' => time()]);
		}
		else
		{
			parse_str($response->body, $token);
			$token = array_merge($token, ['created' => time()]);
		}

		$this->setToken($token);

		return $token;
	}
}
