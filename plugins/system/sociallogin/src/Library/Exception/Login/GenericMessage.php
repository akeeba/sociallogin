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
 * Exception thrown when a generic error occurs. The application must redirect to the error page WITHOUT going through
 * the login failure handlers of the user plugins.
 */
class GenericMessage extends SocialLoginRuntimeException
{
}
