<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Sociallogin\Linkedin\Integration;

// Protect from unauthorized access
defined('_JEXEC') || die();

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Http\Http;
use Joomla\Input\Input;
use Akeeba\Plugin\System\SocialLogin\Library\OAuth\OAuth2Client;

/**
 * Facebook OAuth client.
 *
 * This class is adapted from the Joomla! Framework.
 */
class OAuth extends OAuth2Client
{
	/**
	 * @var   array  Options for the OAuth object.
	 */
	protected $options;

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
		$this->options = $options;

		// Setup the authentication and token urls if not already set.
		$this->options['authurl']  ??= 'https://www.linkedin.com/oauth/v2/authorization';
		$this->options['tokenurl'] ??= 'https://www.linkedin.com/oauth/v2/accessToken';
		$this->options['scope']    ??= 'profile email openid';

		// Call the \Joomla\OAuth2\Client constructor to setup the object.
		parent::__construct($this->options, $client, $input, $application);
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
	 * @return  OAuth  This object for method chaining
	 */
	public function setScope($scope)
	{
		$this->setOption('scope', $scope);

		return $this;
	}

}
