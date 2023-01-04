<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\System\SocialLogin\Library\Data;

// Protect from unauthorized access
defined('_JEXEC') || die();

/**
 * Configuration parameters of a social media integration plugin, used during login.
 *
 * @property   bool $canLoginUnlinked     Should I log in users who have not yet linked their social network account to
 *                                         their site account?
 * @property   bool $canCreateNewUsers    Can I use this integration to create new user accounts?
 * @property   bool $canCreateAlways      Allow the plugin to override Joomla's new user account registration flag?
 * @property   bool $canBypassValidation  Am I allowed to bypass user verification if the social network reports the
 *                                         user verified on their end?
 */
final class PluginConfiguration
{
	/**
	 * When creating new users, am I allowed to bypass email verification if the social network reports the user as
	 * verified on their end?
	 *
	 * @var   bool
	 */
	private $canBypassValidation = true;

	/**
	 * Allow the plugin to override Joomla's new user account registration flag. This is useful to prevent new user
	 * accounts from being created _unless_ they have a social media account and use it on your site (force new users to
	 * link their social media accounts).
	 *
	 * @var   bool
	 */
	private $canCreateAlways = false;

	/**
	 * Can I use this integration to create new user accounts? This will happen when someone tries to login through
	 * the social network but their social network account is not linked to a user account yet.
	 *
	 * @var   bool
	 */
	private $canCreateNewUsers = false;

	/**
	 * Should I log in users who have not yet linked their social network account to their site account? THIS MAY BE
	 * DANGEROUS (impersonation risk), therefore it is disabled by default.
	 *
	 * @var   bool
	 */
	private $canLoginUnlinked = false;

	/**
	 * Magic getter. Returns the stored, sanitized property values.
	 *
	 * @param   string  $name  The name of the property to read.
	 *
	 * @return  mixed
	 */
	function __get($name)
	{
		switch ($name)
		{
			case 'canLoginUnlinked':
			case 'canCreateNewUsers':
			case 'canCreateAlways':
			case 'canBypassValidation':
				return $this->{$name};
				break;

			default:
				return null;
		}
	}

	/**
	 * Magic setter. Stores a sanitized property value.
	 *
	 * @param   string  $name   The name of the property to set
	 * @param   mixed   $value  The value to set the property to
	 *
	 * @return  void
	 */
	function __set($name, $value)
	{
		switch ($name)
		{
			case 'canLoginUnlinked':
			case 'canCreateNewUsers':
			case 'canCreateAlways':
			case 'canBypassValidation':
				$this->{$name} = (bool) $value;
				break;
		}

	}


}
