<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Sociallogin\SynologyOIDC\Integration;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Cache\CacheControllerFactoryAwareTrait;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Http\Http;
use Joomla\Input\Input;
use Akeeba\Plugin\System\SocialLogin\Library\OAuth\OAuth2Client;
use Akeeba\Plugin\System\SocialLogin\Library\OAuth\OpenIDConnectTrait;

class OAuth extends OAuth2Client
{
	use CacheControllerFactoryAwareTrait;
	use OpenIDConnectTrait;

	/**
	 * Constructor.
	 *
	 * @param   array           $options      OAuth options array.
	 * @param   Http            $client       The HTTP client object.
	 * @param   Input           $input        The input object.
	 * @param   CMSApplication  $application  The application object.
	 */
	public function __construct($options, $client, $input, $application)
	{
		$this->application = $application;

		if (empty($options['wellknown']))
		{
			throw new \RuntimeException('Not configured', 500);
		}

		$endpoints = $this->getOIDCEndpoints($options['wellknown']);

		if (empty($endpoints))
		{
			throw new \RuntimeException('Invalid Well-known URL, or cannot retrieve information.');
		}

		// Set up the authentication and token urls if not already set.
		$options['authurl']  ??= $endpoints->authurl;
		$options['tokenurl'] ??= $endpoints->tokenurl;
		$options['scope']    ??= 'email openid';

		// Call the \Joomla\OAuth2\Client constructor to setup the object.
		parent::__construct($options, $client, $input, $application);
	}

	/**
	 * Method to get the current scope
	 *
	 * @return  string Comma separated list of permissions.
	 */
	public function getScope()
	{
		return $this->getOption('scope');
	}

	/**
	 * Method used to set permissions.
	 *
	 * @param   string  $scope  Comma separated list of permissions.
	 *
	 * @return  self  This object for method chaining
	 */
	public function setScope($scope)
	{
		$this->setOption('scope', $scope);

		return $this;
	}
}