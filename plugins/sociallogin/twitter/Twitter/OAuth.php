<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\SocialLogin\Twitter;

use Akeeba\SocialLogin\Library\OAuth\OAuth1Client;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Http\Http;
use Joomla\CMS\Http\Response;
use Joomla\CMS\Input\Input;

// Protect from unauthorized access
defined('_JEXEC') or die();

/**
 * Twitter OAuth authentication class. Adapted from the Joomla! Framework.
 */
class OAuth extends OAuth1Client
{
	/**
	 * @var    array  Options for the Twitter OAuth object.
	 */
	protected $options;

	/**
	 * Constructor.
	 *
	 * @param   array           $options      OAuth options array.
	 * @param   Http            $client       The HTTP client object.
	 * @param   Input           $input        The input object.
	 * @param   CMSApplication  $application  The application object.
	 *
	 * @since 1.0
	 */
	public function __construct($options, $client, $input, $application)
	{
		$this->options = $options;

		if (!isset($this->options['accessTokenURL']))
		{
			$this->options['accessTokenURL'] = 'https://api.twitter.com/oauth/access_token';
		}

		if (!isset($this->options['authenticateURL']))
		{
			$this->options['authenticateURL'] = 'https://api.twitter.com/oauth/authenticate';
		}

		if (!isset($this->options['authoriseURL']))
		{
			$this->options['authoriseURL'] = 'https://api.twitter.com/oauth/authorize';
		}

		if (!isset($this->options['requestTokenURL']))
		{
			$this->options['requestTokenURL'] = 'https://api.twitter.com/oauth/request_token';
		}

		// Call the OAuth1 Client constructor to setup the object.
		parent::__construct($this->options, $client, $input, $application);
	}

	/**
	 * Method to verify if the access token is valid by making a request.
	 *
	 * @return  boolean  Returns true if the access token is valid and false otherwise.
	 */
	public function verifyCredentials()
	{
		$token      = $this->getToken();
		$parameters = array('oauth_token' => $token['key']);
		$path       = 'https://api.twitter.com/1.1/account/verify_credentials.json';
		$response   = $this->oauthRequest($path, 'GET', $parameters);

		return $response->code == 200;
	}
	/**
	 * Ends the session of the authenticating user, returning a null cookie.
	 *
	 * @return  array  The decoded JSON response
	 */
	public function endSession()
	{
		$token      = $this->getToken();
		$parameters = array('oauth_token' => $token['key']);
		$path       = 'https://api.twitter.com/1.1/account/end_session.json';
		$response   = $this->oauthRequest($path, 'POST', $parameters);

		return json_decode($response->body);
	}

	/**
	 * Method to validate a response.
	 *
	 * @param   string    $url       The request URL.
	 * @param   Response  $response  The response to validate.
	 *
	 * @return  void
	 *
	 * @throws \DomainException
	 */
	public function validateResponse($url, $response)
	{
		if (strpos($url, 'verify_credentials') === false && $response->code != 200)
		{
			$error = json_decode($response->body);

			if (property_exists($error, 'error'))
			{
				throw new \DomainException($error->error);
			}

			$error = $error->errors;

			throw new \DomainException($error[0]->message, $error[0]->code);
		}
	}
}
