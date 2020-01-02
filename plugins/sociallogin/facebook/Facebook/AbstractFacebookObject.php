<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Akeeba\SocialLogin\Facebook;

use Joomla\CMS\Http\Http;
use Joomla\Uri\Uri;
use JUri;

// Protect from unauthorized access
defined('_JEXEC') or die();

/**
 * Facebook API object class. Adapted from the Joomla Framework.
 */
abstract class AbstractFacebookObject
{
	/**
	 * Options for the Facebook object.
	 *
	 * @var    array
	 */
	protected $options;

	/**
	 * The HTTP client object to use in sending HTTP requests.
	 *
	 * @var    Http
	 */
	protected $client;

	/**
	 * The OAuth client.
	 *
	 * @var    OAuth
	 */
	protected $oauth;

	/**
	 * Constructor.
	 *
	 * @param   array  $options  Facebook options array.
	 * @param   Http   $client   The HTTP client object.
	 * @param   OAuth  $oauth    The OAuth client.
	 */
	public function __construct($options = array(), $client = null, $oauth = null)
	{
		$this->options = $options;
		$this->client = $client;
		$this->oauth = $oauth;
	}

	/**
	 * Method to build and return a full request URL for the request.  This method will
	 * add appropriate pagination details if necessary and also prepend the API url
	 * to have a complete URL for the request.
	 *
	 * @param   string   $path    URL to inflect.
	 * @param   integer  $limit   The number of objects per page.
	 * @param   integer  $offset  The object's number on the page.
	 * @param   string   $until   A unix timestamp or any date accepted by strtotime.
	 * @param   string   $since   A unix timestamp or any date accepted by strtotime.
	 *
	 * @return  string  The request URL.
	 */
	protected function fetchUrl($path, $limit = 0, $offset = 0, $until = null, $since = null)
	{
		// Get a new Uri object fousing the api url and given path.
		$apiUrl = isset($this->options['api.url']) ? $this->options['api.url'] : null;

		if (class_exists('Joomla\\Uri\\Uri'))
		{
			$uri = new Uri($apiUrl . $path);
		}
		else
		{
			$uri = new JUri($apiUrl . $path);
		}

		if ($limit > 0)
		{
			$uri->setVar('limit', (int) $limit);
		}

		if ($offset > 0)
		{
			$uri->setVar('offset', (int) $offset);
		}

		if ($until != null)
		{
			$uri->setVar('until', $until);
		}

		if ($since != null)
		{
			$uri->setVar('since', $since);
		}

		return (string) $uri;
	}

	/**
	 * Method to send the request.
	 *
	 * @param   string   $path     The path of the request to make.
	 * @param   mixed    $data     Either an associative array or a string to be sent with the post request.
	 * @param   array    $headers  An array of name-value pairs to include in the header of the request
	 * @param   integer  $limit    The number of objects per page.
	 * @param   integer  $offset   The object's number on the page.
	 * @param   string   $until    A unix timestamp or any date accepted by strtotime.
	 * @param   string   $since    A unix timestamp or any date accepted by strtotime.
	 *
	 * @return   mixed  The request response.
	 *
	 * @throws   \RuntimeException
	 */
	public function sendRequest($path, $data = '', array $headers = array(), $limit = 0, $offset = 0, $until = null, $since = null)
	{
		// Send the request.
		$response = $this->client->get($this->fetchUrl($path, $limit, $offset, $until, $since), $headers);

		$response = json_decode($response->body);

		// Validate the response.
		if (property_exists($response, 'error'))
		{
			throw new \RuntimeException($response->error->message);
		}

		return $response;
	}

	/**
	 * Method to get an object.
	 *
	 * @param   string  $object  The object id.
	 *
	 * @return  mixed   The decoded JSON response or false if the client is not authenticated.
	 *
	 * @throws  \Exception
	 */
	public function get($object)
	{
		if ($this->oauth != null)
		{
			if ($this->oauth->isAuthenticated())
			{
				$response = $this->oauth->query($this->fetchUrl($object));

				return json_decode($response->body);
			}
			else
			{
				return false;
			}
		}

		// Send the request.
		return $this->sendRequest($object);
	}

	/**
	 * Method to get object's connection.
	 *
	 * @param   string  $object       The object id.
	 * @param   string  $connection   The object's connection name.
	 * @param   string  $extra_fields URL fields.
	 * @param   integer $limit        The number of objects per page.
	 * @param   integer $offset       The object's number on the page.
	 * @param   string  $until        A unix timestamp or any date accepted by strtotime.
	 * @param   string  $since        A unix timestamp or any date accepted by strtotime.
	 *
	 * @return  mixed   The decoded JSON response or false if the client is not authenticated.
	 *
	 * @throws \Exception
	 */
	public function getConnection($object, $connection = null, $extra_fields = '', $limit = 0, $offset = 0, $until = null, $since = null)
	{
		$path = $object . '/' . $connection . $extra_fields;

		if ($this->oauth != null)
		{
			if ($this->oauth->isAuthenticated())
			{
				$response = $this->oauth->query($this->fetchUrl($path, $limit, $offset, $until, $since));

				if (strcmp($response->body, ''))
				{
					return json_decode($response->body);
				}
				else
				{
					return $response->headers['Location'];
				}
			}
			else
			{
				return false;
			}
		}

		// Send the request.
		return $this->sendRequest($path, '', array(), $limit, $offset, $until, $since);
	}

	/**
	 * Method to create a connection.
	 *
	 * @param   string $object     The object id.
	 * @param   string $connection The object's connection name.
	 * @param   array  $parameters The POST request parameters.
	 * @param   array  $headers    An array of name-value pairs to include in the header of the request
	 *
	 * @return  mixed   The decoded JSON response or false if the client is not authenticated.
	 *
	 * @throws \Exception
	 */
	public function createConnection($object, $connection = null, $parameters = null, array $headers = array())
	{
		if ($this->oauth->isAuthenticated())
		{
			// Build the request path.
			if ($connection != null)
			{
				$path = $object . '/' . $connection;
			}
			else
			{
				$path = $object;
			}

			// Send the post request.
			$response = $this->oauth->query($this->fetchUrl($path), $parameters, $headers, 'post');

			return json_decode($response->body);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Method to delete a connection.
	 *
	 * @param   string $object       The object id.
	 * @param   string $connection   The object's connection name.
	 * @param   string $extra_fields URL fields.
	 *
	 * @return  mixed   The decoded JSON response or false if the client is not authenticated.
	 *
	 * @throws \Exception
	 */
	public function deleteConnection($object, $connection = null, $extra_fields = '')
	{
		if ($this->oauth->isAuthenticated())
		{
			// Build the request path.
			if ($connection != null)
			{
				$path = $object . '/' . $connection . $extra_fields;
			}
			else
			{
				$path = $object . $extra_fields;
			}

			// Send the delete request.
			$response = $this->oauth->query($this->fetchUrl($path), null, array(), 'delete');

			return json_decode($response->body);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Method used to set the OAuth client.
	 *
	 * @param   OAuth  $oauth  The OAuth client object.
	 *
	 * @return  AbstractFacebookObject  This object for method chaining.
	 */
	public function setOauth($oauth)
	{
		$this->oauth = $oauth;

		return $this;
	}

	/**
	 * Method used to get the OAuth client.
	 *
	 * @return  OAuth  The OAuth client
	 */
	public function getOauth()
	{
		return $this->oauth;
	}
}
