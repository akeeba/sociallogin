<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Sociallogin\Paypal\Integration;

defined('_JEXEC') || die();

use Joomla\Plugin\System\SocialLogin\Library\OAuth\OAuth2Client;
use RuntimeException;

class OAuth extends OAuth2Client
{

	public function __construct($options, $client, $input, $application)
	{
		$this->options = $options;

		$this->options['authurl']  ??= 'https://www.paypal.com/signin/authorize?flowEntry=static&fullPage=true';
		$this->options['tokenurl'] ??= 'https://api-m.paypal.com/v1/oauth2/token';
		$this->options['scope']    ??= 'openid profile email';

		parent::__construct($this->options, $client, $input, $application);
	}

	public function authenticate()
	{
		if ($data['code'] = $this->input->get('code', false, 'raw'))
		{
			$data['grant_type'] = 'authorization_code';
			$response           = $this->http->post(
				$this->getOption('tokenurl'), $data, [
					'Authorization' => 'Basic ' . base64_encode(
							$this->getOption('clientid') . ':' . $this->getOption('clientsecret')
						),
				]
			);

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

		return parent::authenticate();
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