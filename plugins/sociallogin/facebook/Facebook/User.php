<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\SocialLogin\Facebook;

// Protect from unauthorized access
use Exception;

defined('_JEXEC') or die();

/**
 * Very brief Facebook API User class. Adapted from the Joomla Framework.
 *
 * @link   https://developers.facebook.com/docs/reference/api/user/
 */
class User extends AbstractFacebookObject
{
	/**
	 * Method to get the specified user's details. Authentication is required only for some fields.
	 *
	 * @param   mixed  $user  Either an integer containing the user ID or a string containing the username.
	 *
	 * @return  mixed   The decoded JSON response or false if the client is not authenticated.
	 *
	 * @throws  Exception
	 */
	public function getUser($user)
	{
		return $this->get($user);
	}

	/**
	 * Method to get the user's profile picture. Requires authentication.
	 *
	 * @param   mixed    $user      Either an integer containing the user ID or a string containing the username.
	 * @param   boolean  $redirect  If false this will return the URL of the profile picture without a 302 redirect.
	 * @param   string   $type      To request a different photo use square | small | normal | large.
	 *
	 * @return  string   The URL to the user's profile picture.
	 *
	 * @throws  Exception
	 */
	public function getPicture($user, $redirect = true, $type = null)
	{
		$extra_fields = '';

		if ($redirect == false)
		{
			$extra_fields = '?redirect=false';
		}

		if ($type != null)
		{
			$extra_fields .= (strpos($extra_fields, '?') === false) ? '?type=' . $type : '&type=' . $type;
		}

		return $this->getConnection($user, 'picture', $extra_fields);
	}
}
