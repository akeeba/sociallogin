<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Sociallogin\Firebase\Integration;

defined('_JEXEC') || die;

use Akeeba\Plugin\System\SocialLogin\Library\OAuth\OAuth2Client;
use Joomla\CMS\Application\CMSApplication;
use Joomla\Http\Http;
use Joomla\Input\Input;

class OAuth extends OAuth2Client
{
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

		parent::__construct($options, $client, $input, $application);
	}
}
