<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\SocialLogin\Google;

use Joomla\Registry\Registry;
use UnexpectedValueException;
use SimpleXMLElement;
use Exception;

// Protect from unauthorized access
defined('_JEXEC') or die();

/**
 * Google+ People class. Adapted from the Joomla! Framework.
 */
class People
{
	/**
	 * @var    array  Options for the Google data object.
	 */
	protected $options;

	/**
	 * @var    OAuth2  Authentication client for the Google data object.
	 */
	protected $auth;

	/**
	 * Constructor.
	 *
	 * @param   Registry|\JRegistry  $options  Google options object.
	 * @param   OAuth2               $auth     Google data http client object.
	 */
	public function __construct($options, OAuth2 $auth)
	{
		// Setup the default API url if not already set.
		$options->def('api.url', 'https://www.googleapis.com/plus/v1/');

		$this->options = $options;
		$this->auth    = $auth;

		if (!$this->auth->getOption('scope'))
		{
			$this->auth->setOption('scope', 'https://www.googleapis.com/auth/plus.me');
		}
	}

	/**
	 * Method to authenticate to Google
	 *
	 * @return  boolean  True on success.
	 */
	public function authenticate()
	{
		return $this->auth->authenticate();
	}

	/**
	 * Check authentication
	 *
	 * @return  boolean  True if authenticated.
	 */
	public function isAuthenticated()
	{
		return $this->auth->isAuthenticated();
	}

	/**
	 * Method to validate XML
	 *
	 * @param   string  $data  XML data to be parsed
	 *
	 * @return  SimpleXMLElement  XMLElement of parsed data
	 *
	 * @throws  UnexpectedValueException
	 */
	protected static function safeXml($data)
	{
		try
		{
			return new SimpleXMLElement($data, LIBXML_NOWARNING | LIBXML_NOERROR);
		}
		catch (Exception $e)
		{
			throw new UnexpectedValueException("Unexpected data received from Google: `$data`.");
		}
	}

	/**
	 * Method to retrieve a list of data
	 *
	 * @param   string   $url       URL to GET
	 * @param   integer  $maxpages  Maximum number of pages to return
	 * @param   string   $token     Next page token
	 *
	 * @return  mixed  Data from Google
	 *
	 * @throws  UnexpectedValueException
	 * @throws  Exception
	 */
	protected function listGetData($url, $maxpages = 1, $token = null)
	{
		$qurl = $url;

		if (strpos($url, '&') && isset($token))
		{
			$qurl .= '&pageToken=' . $token;
		}
		elseif (isset($token))
		{
			$qurl .= 'pageToken=' . $token;
		}

		$jdata = $this->query($qurl);
		$data = json_decode($jdata->body, true);

		if ($data && array_key_exists('items', $data))
		{
			if ($maxpages != 1 && array_key_exists('nextPageToken', $data))
			{
				$data['items'] = array_merge($data['items'], $this->listGetData($url, $maxpages - 1, $data['nextPageToken']));
			}

			return $data['items'];
		}
		elseif ($data)
		{
			return array();
		}
		else
		{
			throw new UnexpectedValueException("Unexpected data received from Google: `{$jdata->body}`.");
		}
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
	protected function query($url, $data = null, $headers = null, $method = 'get')
	{
		return $this->auth->query($url, $data, $headers, $method);
	}

	/**
	 * Get an option from the Data instance.
	 *
	 * @param   string  $key  The name of the option to get.
	 *
	 * @return  mixed  The option value.
	 */
	public function getOption($key)
	{
		return isset($this->options[$key]) ? $this->options[$key] : null;
	}

	/**
	 * Set an option for the Data instance.
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
	 * Get a person's profile.
	 *
	 * @param   string  $id      The ID of the person to get the profile for. The special value "me" can be used to
	 *                           indicate the authenticated user.
	 * @param   string  $fields  Used to specify the fields you want returned.
	 *
	 * @return  mixed  Data from Google
	 *
	 * @throws  Exception
	 */
	public function getPeople($id = 'me', $fields = 'emails,id,name,image')
	{
		if (!$this->isAuthenticated())
		{
			return false;
		}

		$url = $this->getOption('api.url') . 'people/' . $id;

		// Check if fields is specified.
		if ($fields)
		{
			$url .= '?fields=' . $fields;
		}

		$jdata = $this->auth->query($url);

		return json_decode($jdata->body, true);
	}
}
