<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\System\SocialLogin\Library\Exception\Login;

// Protect from unauthorized access
defined('_JEXEC') || die();

/**
 * Exception thrown when a login error occurs. The application must go through the failed login user plugin handlers.
 */
class LoginError extends SocialLoginRuntimeException
{
}
