<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Sociallogin\Spotify\Integration;

defined('_JEXEC') || die();

use Akeeba\Plugin\System\SocialLogin\Library\OAuth\OAuth2Client;

class OAuth extends OAuth2Client
{

	public function __construct($options, $client, $input, $application)
	{
		$this->options = $options;
		if (!isset($this->options['authurl']))
		{
			$this->options['authurl'] = 'https://accounts.spotify.com/authorize';
		}
		if (!isset($this->options['tokenurl']))
		{
			$this->options['tokenurl'] = 'https://accounts.spotify.com/api/token';
		}
		if (!isset($this->options['scope']))
		{
			$this->options['scope'] = 'user-read-email';
		}
		parent::__construct($this->options, $client, $input, $application);
	}

	public function getScope()
	{
		return $this->getOption('scope');
	}

	public function setScope($scope)
	{
		$this->setOption('scope', $scope);

		return $this;
	}

}