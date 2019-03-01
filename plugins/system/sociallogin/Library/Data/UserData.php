<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\SocialLogin\Library\Data;

// Protect from unauthorized access
defined('_JEXEC') or die();

/**
 * Information about the user account returned by the social media API
 *
 * @property   string  $id        A unique identifier of the social media user.
 * @property   string  $name      Full (real) name of the social media user.
 * @property   string  $email     The email address of the social media user.
 * @property   bool    $verified  Does the social media report the user as verified?
 * @property   string  $timezone  Timezone of the user, as reported by the social media site.
 */
final class UserData
{
	/**
	 * A unique identifier of the social media user.
	 *
	 * @var   string
	 */
	private $id = '';

	/**
	 * Full (real) name of the social media user.
	 *
	 * @var   string
	 */
	private $name = '';

	/**
	 * The email address of the social media user.
	 *
	 * @var   string
	 */
	private $email = '';

	/**
	 * Does the social media report the user as verified?
	 *
	 * @var   bool
	 */
	private $verified = false;

	/**
	 * Timezone of the user, as reported by the social media site.
	 *
	 * @var   string
	 */
	private $timezone = 'UTC';


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
			case 'id':
			case 'name':
			case 'email':
			case 'timezone':
			case 'verified':
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
			case 'id':
			case 'name':
			case 'email':
				$this->{$name} = $value . '';
				break;

			case 'timezone':
				$this->timezone = $this->normalizeTimezone($value);
				break;

			case 'verified':
				$this->{$name} = (bool) $value;
				break;
		}
	}

	/**
	 * Normalize the timezone. The provided value can be a timezone (e.g. Europe/Paris), an abbreviated timezone (e.g.
	 * CET), a GMT offset with or without prefix, either in HH:MM, integer or float (e.g. GMT+1, GMT+1.00, GMT+1:00, +1,
	 * +1.00 or +1:00). You will get back either a normalized timezone (e.g. Europe/Paris) or "UTC".
	 *
	 * @param   string  $timezone  See above
	 *
	 * @return  string
	 */
	private function normalizeTimezone($timezone)
	{
		// If there is a forward slash in the name it's already a timezone name, e.g. Asia/Nicosia. Return it.
		if (is_string($timezone) && (strpos($timezone, '/') !== false))
		{
			return $timezone;
		}

		// If it's the literal string "UTC" or "GMT" return "UTC"
		if (($timezone === 'UTC') || ($timezone === 'GMT'))
		{
			return 'UTC';
		}

		// If there's a "GMT+" or "GMT-" prefix remove it
		$potentialPrefix = strtoupper(substr($timezone, 4));

		if (in_array($potentialPrefix, array('GMT+', 'GMT-')))
		{
			$timezone = substr($timezone, 3);
		}

		// If it's in the form +1:30 or -2:00 convert to float
		if (strpos($timezone, ':'))
		{
			list($hours, $minutes) = explode(':', $timezone, 2);

			$timezone = (float) ($hours + $minutes / 60);
		}

		/**
		 * If the timezone is a float we need to process it. The if-block makes sure that something like EST5EDT or CET
		 * is not mistakenly recognized as a float.
		 */
		if (is_numeric(substr($timezone, 0, 3)))
		{
			$seconds = (int)(3600 * (float) $timezone);
			$timezone = timezone_name_from_abbr('', $seconds, 0);

			if (empty($timezone))
			{
				$timezone = 'UTC';
			}
		}

		return $timezone;
	}
}
