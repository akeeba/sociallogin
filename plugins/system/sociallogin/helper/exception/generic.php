<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

/**
 * Exception thrown when a generic error occurs. The application must redirect to the error page WITHOUT going through
 * the login failure handlers of the user plugins.
 */
class SocialLoginGenericMessageException extends RuntimeException {}