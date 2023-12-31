<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\Sociallogin\Google\Integration;

// Protect from unauthorized access
defined('_JEXEC') || die();

use Exception;
use Joomla\Registry\Registry;
use SimpleXMLElement;
use UnexpectedValueException;

/**
 * Google OpenID class. Adapted from the Joomla! Framework's Google+ People class.
 */
class OpenID
{
	/**
	 * @var    OAuth2  Authentication client for the Google data object.
	 */
	protected $auth;

	/**
	 * @var    array  Options for the Google data object.
	 */
	protected $options;

	/**
	 * Constructor.
	 *
	 * @param   Registry  $options  Google options object.
	 * @param   OAuth2    $auth     Google data http client object.
	 */
	public function __construct($options, OAuth2 $auth)
	{
		/**
		 * Setup the default API url if not already set.
		 *
		 * See https://developers.google.com/identity/protocols/OpenIDConnect#obtaininguserprofileinformation
		 * See https://accounts.google.com/.well-known/openid-configuration
		 */
		$options->def('api.url', 'https://openidconnect.googleapis.com/v1/userinfo');

		$this->options = $options;
		$this->auth    = $auth;

		if (!$this->auth->getOption('scope'))
		{
			$this->auth->setOption('scope', 'openid email profile');
		}
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
	 * Method to authenticate to Google
	 *
	 * @return  boolean  True on success.
	 */
	public function authenticate()
	{
		return $this->auth->authenticate();
	}

	/**
	 * Get a person's profile.
	 *
	 * @return  mixed  Data from Google
	 *
	 * @throws  Exception
	 */
	public function getOpenIDProfile()
	{
		if (!$this->isAuthenticated())
		{
			return false;
		}

		$url = $this->getOption('api.url');

		$jdata = $this->auth->query($url);

		return json_decode($jdata->body, true);
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
		return $this->options[$key] ?? null;
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
}
