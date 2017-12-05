<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\SocialLogin\Library\OAuth;

use Joomla\Application\AbstractWebApplication;
use Joomla\CMS\Http\Http;
use Joomla\CMS\Http\Response;
use Joomla\Input\Input;

// Protect from unauthorized access
defined('_JEXEC') or die();

/**
 * OAuth 1 client.
 *
 * This class is copied from the Joomla! Framework. We removed the typehints to allow it to work with older versions of
 * Joomla! 3 which included legacy CMS-specific classes instead of the equivalent Joomla! Framework classes. Moreover,
 * forking the code allows us to use it in Joomla! 4 where this class is simply not present. Forking, instead of using
 * a private Composer vendor folder, is the best way to avoid hard to solve conflicts with third party extensions also
 * using the same code.
 */
abstract class OAuth1Client
{
	/**
	 * @var    array  Options for the Client object.
	 * @since  1.0
	 */
	protected $options;

	/**
	 * @var    array  Contains access token key, secret and verifier.
	 * @since  1.0
	 */
	protected $token = array();

	/**
	 * @var    Http  The HTTP client object to use in sending HTTP requests.
	 * @since  1.0
	 */
	protected $client;

	/**
	 * @var    Input The input object to use in retrieving GET/POST data.
	 * @since  1.0
	 */
	protected $input;

	/**
	 * @var    AbstractWebApplication  The application object to send HTTP headers for redirects.
	 * @since  1.0
	 */
	protected $application;

	/**
	 * @var    string  Selects which version of OAuth to use: 1.0 or 1.0a.
	 * @since  1.0
	 */
	protected $version;

	/**
	 * Constructor.
	 *
	 * @param   array                   $options      OAuth1 Client options array.
	 * @param   Http                    $client       The HTTP client object.
	 * @param   Input                   $input        The input object
	 * @param   AbstractWebApplication  $application  The application object
	 * @param   string                  $version      Specify the OAuth version. By default we are using 1.0a.
	 *
	 * @since   1.0
	 */
	public function __construct($options = array(), $client, $input, $application, $version = '1.0a')
	{
		$this->options = $options;
		$this->client = $client;

		if (class_exists('Joomla\\Input\\Input'))
		{
			$this->input = $input ?: ($application ? $application->input : new Input());
		}
		else
		{
			$this->input = $input ?: ($application ? $application->input : new JInput());
		}

		$this->application = $application;
		$this->version = $version;
	}

	/**
	 * Method to form the oauth flow.
	 *
	 * @return  array  The access token.
	 *
	 * @since   1.0
	 * @throws  \DomainException
	 */
	public function authenticate()
	{
		// Already got some credentials stored?
		if ($this->token)
		{
			$response = $this->verifyCredentials();

			if ($response)
			{
				return $this->token;
			}
			else
			{
				$this->token = null;
			}
		}

		// Check for callback.
		if (strcmp($this->version, '1.0a') === 0)
		{
			$verifier = $this->input->get('oauth_verifier');
		}
		else
		{
			$verifier = $this->input->get('oauth_token');
		}
		if (empty($verifier))
		{
			// Generate a request token.
			$this->generateRequestToken();

			// Authenticate the user and authorise the app.
			$this->authorise();

			return array();
		}

		// Callback
		$session = $this->application->getSession();

		// Get token form session.
		$this->token = array('key' => $session->get('oauth_token.key', null), 'secret' => $session->get('oauth_token.secret', null));

		// Verify the returned request token.
		if (strcmp($this->token['key'], $this->input->get('oauth_token')) !== 0)
		{
			throw new \DomainException('Bad session!');
		}

		// Set token verifier for 1.0a.
		if (strcmp($this->version, '1.0a') === 0)
		{
			$this->token['verifier'] = $this->input->get('oauth_verifier');
		}

		// Generate access token.
		$this->generateAccessToken();

		// Return the access token.
		return $this->token;
	}

	/**
	 * Method used to get a request token.
	 *
	 * @return  void
	 *
	 * @throws  \DomainException
	 */
	private function generateRequestToken()
	{
		// Set the callback URL.
		if ($this->getOption('callback'))
		{
			$parameters = array(
				'oauth_callback' => $this->getOption('callback')
			);
		}
		else
		{
			$parameters = array();
		}

		// Make an OAuth request for the Request Token.
		$response = $this->oauthRequest($this->getOption('requestTokenURL'), 'POST', $parameters);

		parse_str($response->body, $params);

		if (strcmp($this->version, '1.0a') === 0 && strcmp($params['oauth_callback_confirmed'], 'true') !== 0)
		{
			throw new \DomainException('Bad request token!');
		}

		// Save the request token.
		$this->token = array('key' => $params['oauth_token'], 'secret' => $params['oauth_token_secret']);

		// Save the request token in session
		$session = $this->application->getSession();
		$session->set('oauth_token.key', $this->token['key']);
		$session->set('oauth_token.secret', $this->token['secret']);
	}

	/**
	 * Method used to authorise the application.
	 *
	 * @return  void
	 */
	private function authorise()
	{
		$url = $this->getOption('authoriseURL') . '?oauth_token=' . $this->token['key'];

		if ($this->getOption('scope'))
		{
			$scope = is_array($this->getOption('scope')) ? implode(' ', $this->getOption('scope')) : $this->getOption('scope');
			$url .= '&scope=' . urlencode($scope);
		}

		if ($this->getOption('sendheaders'))
		{
			$this->application->redirect($url);
		}
	}

	/**
	 * Method used to get an access token.
	 *
	 * @return  void
	 */
	private function generateAccessToken()
	{
		// Set the parameters.
		$parameters = array(
			'oauth_token' => $this->token['key']
		);

		if (strcmp($this->version, '1.0a') === 0)
		{
			$parameters = array_merge($parameters, array('oauth_verifier' => $this->token['verifier']));
		}

		// Make an OAuth request for the Access Token.
		$response = $this->oauthRequest($this->getOption('accessTokenURL'), 'POST', $parameters);

		parse_str($response->body, $params);

		// Save the access token.
		$this->token = array('key' => $params['oauth_token'], 'secret' => $params['oauth_token_secret']);
	}

	/**
	 * Method used to make an OAuth request.
	 *
	 * @param   string  $url         The request URL.
	 * @param   string  $method      The request method.
	 * @param   array   $parameters  Array containing request parameters.
	 * @param   mixed   $data        The POST request data.
	 * @param   array   $headers     An array of name-value pairs to include in the header of the request
	 *
	 * @return  Response  The Response object.
	 *
	 * @since   1.0
	 * @throws  \DomainException
	 */
	public function oauthRequest($url, $method, $parameters, $data = array(), $headers = array())
	{
		// Set the parameters.
		$defaults = array(
			'oauth_consumer_key' => $this->getOption('consumer_key'),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_version' => '1.0',
			'oauth_nonce' => $this->generateNonce(),
			'oauth_timestamp' => time()
		);

		$parameters = array_merge($parameters, $defaults);

		// Do not encode multipart parameters. Do not include $data in the signature if $data is not array.
		if (isset($headers['Content-Type']) && strpos($headers['Content-Type'], 'multipart/form-data') !== false || !is_array($data))
		{
			$oauthHeaders = $parameters;
		}
		else
		{
			// Use all parameters for the signature.
			$oauthHeaders = array_merge($parameters, $data);
		}

		// Sign the request.
		$oauthHeaders = $this->signRequest($url, $method, $oauthHeaders);

		// Get parameters for the Authorisation header.
		if (is_array($data))
		{
			$oauthHeaders = array_diff_key($oauthHeaders, $data);
		}

		// Send the request.
		switch ($method)
		{
			case 'GET':
				$url = $this->toUrl($url, $data);
				$response = $this->client->get($url, array('Authorization' => $this->createHeader($oauthHeaders)));
				break;
			case 'POST':
				$headers = array_merge($headers, array('Authorization' => $this->createHeader($oauthHeaders)));
				$response = $this->client->post($url, $data, $headers);
				break;
			case 'PUT':
				$headers = array_merge($headers, array('Authorization' => $this->createHeader($oauthHeaders)));
				$response = $this->client->put($url, $data, $headers);
				break;
			case 'DELETE':
				$headers = array_merge($headers, array('Authorization' => $this->createHeader($oauthHeaders)));
				$response = $this->client->delete($url, $headers);
				break;
		}

		// Validate the response code.
		$this->validateResponse($url, $response);

		return $response;
	}

	/**
	 * Method to validate a response.
	 *
	 * @param   string    $url       The request URL.
	 * @param   Response  $response  The response to validate.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 * @throws  \DomainException
	 */
	abstract public function validateResponse($url, $response);

	/**
	 * Method used to create the header for the POST request.
	 *
	 * @param   array  $parameters  Array containing request parameters.
	 *
	 * @return  string  The header.
	 */
	private function createHeader($parameters)
	{
		$header = 'OAuth ';

		foreach ($parameters as $key => $value)
		{
			if (!strcmp($header, 'OAuth '))
			{
				$header .= $key . '="' . $this->safeEncode($value) . '"';
			}
			else
			{
				$header .= ', ' . $key . '="' . $value . '"';
			}
		}

		return $header;
	}

	/**
	 * Method to create the URL formed string with the parameters.
	 *
	 * @param   string  $url         The request URL.
	 * @param   array   $parameters  Array containing request parameters.
	 *
	 * @return  string  The formed URL.
	 *
	 * @since   1.0
	 */
	public function toUrl($url, $parameters)
	{
		foreach ($parameters as $key => $value)
		{
			if (is_array($value))
			{
				foreach ($value as $k => $v)
				{
					if (strpos($url, '?') === false)
					{
						$url .= '?' . $key . '=' . $v;
					}
					else
					{
						$url .= '&' . $key . '=' . $v;
					}
				}
			}
			else
			{
				if (strpos($value, ' ') !== false)
				{
					$value = $this->safeEncode($value);
				}

				if (strpos($url, '?') === false)
				{
					$url .= '?' . $key . '=' . $value;
				}
				else
				{
					$url .= '&' . $key . '=' . $value;
				}
			}
		}

		return $url;
	}

	/**
	 * Method used to sign requests.
	 *
	 * @param   string  $url         The URL to sign.
	 * @param   string  $method      The request method.
	 * @param   array   $parameters  Array containing request parameters.
	 *
	 * @return  array  The array containing the request parameters, including signature.
	 */
	private function signRequest($url, $method, $parameters)
	{
		// Create the signature base string.
		$base = $this->baseString($url, $method, $parameters);

		$parameters['oauth_signature'] = $this->safeEncode(
			base64_encode(
				hash_hmac('sha1', $base, $this->prepareSigningKey(), true)
			)
		);

		return $parameters;
	}

	/**
	 * Prepare the signature base string.
	 *
	 * @param   string  $url         The URL to sign.
	 * @param   string  $method      The request method.
	 * @param   array   $parameters  Array containing request parameters.
	 *
	 * @return  string  The base string.
	 */
	private function baseString($url, $method, $parameters)
	{
		// Sort the parameters alphabetically
		uksort($parameters, 'strcmp');

		// Encode parameters.
		foreach ($parameters as $key => $value)
		{
			$key = $this->safeEncode($key);

			if (is_array($value))
			{
				foreach ($value as $k => $v)
				{
					$v = $this->safeEncode($v);
					$kv[] = "{$key}={$v}";
				}
			}
			else
			{
				$value = $this->safeEncode($value);
				$kv[] = "{$key}={$value}";
			}
		}

		// Form the parameter string.
		$params = implode('&', $kv);

		// Signature base string elements.
		$base = array(
			$method,
			$url,
			$params
		);

		// Return the base string.
		return implode('&', $this->safeEncode($base));
	}

	/**
	 * Encodes the string or array passed in a way compatible with OAuth.
	 * If an array is passed each array value will will be encoded.
	 *
	 * @param   mixed  $data  The scalar or array to encode.
	 *
	 * @return  string  $data encoded in a way compatible with OAuth.
	 *
	 * @since   1.0
	 */
	public function safeEncode($data)
	{
		if (is_array($data))
		{
			return array_map(array($this, 'safeEncode'), $data);
		}

		if (is_scalar($data))
		{
			return str_ireplace(
				array('+', '%7E'),
				array(' ', '~'),
				rawurlencode($data)
			);
		}

		return '';
	}

	/**
	 * Method used to generate the current nonce.
	 *
	 * @return  string  The current nonce.
	 *
	 * @since   1.0
	 */
	public static function generateNonce()
	{
		$mt = microtime();
		$rand = random_bytes(16);

		// The md5s look nicer than numbers.
		return md5($mt . $rand);
	}

	/**
	 * Prepares the OAuth signing key.
	 *
	 * @return  string  The prepared signing key.
	 */
	private function prepareSigningKey()
	{
		return $this->safeEncode($this->getOption('consumer_secret')) . '&' . $this->safeEncode(($this->token) ? $this->token['secret'] : '');
	}

	/**
	 * Returns an HTTP 200 OK response code and a representation of the requesting user if authentication was successful;
	 * returns a 401 status code and an error message if not.
	 *
	 * @return  array  The decoded JSON response
	 *
	 * @since   1.0
	 */
	abstract public function verifyCredentials();

	/**
	 * Get an option from the OAuth1 Client instance.
	 *
	 * @param   string  $key  The name of the option to get
	 *
	 * @return  mixed  The option value
	 *
	 * @since   1.0
	 */
	public function getOption($key)
	{
		return isset($this->options[$key]) ? $this->options[$key] : null;
	}

	/**
	 * Set an option for the OAuth1 Client instance.
	 *
	 * @param   string  $key    The name of the option to set
	 * @param   mixed   $value  The option value to set
	 *
	 * @return  self  This object for method chaining
	 *
	 * @since   1.0
	 */
	public function setOption($key, $value)
	{
		$this->options[$key] = $value;

		return $this;
	}

	/**
	 * Get the oauth token key or secret.
	 *
	 * @return  array  The oauth token key and secret.
	 *
	 * @since   1.0
	 */
	public function getToken()
	{
		return $this->token;
	}

	/**
	 * Set the oauth token.
	 *
	 * @param   array  $token  The access token key and secret.
	 *
	 * @return  self  This object for method chaining.
	 *
	 * @since   1.0
	 */
	public function setToken($token)
	{
		$this->token = $token;

		return $this;
	}

}
