<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Akeeba\SocialLogin\Library\Helper;

// Protect from unauthorized access
defined('_JEXEC') or die();

use Joomla\CMS\Factory;

/**
 * Dynamic user to user group assignment.
 *
 * This class allows you to add / remove the currently logged in user to a user group without writing the information to
 * the database. This is useful when you want to allow core and third party code to allow or prohibit display of
 * information and / or taking actions based on a condition controlled in your code.
 *
 * This class has been forked off Akeeba FOF 3.
 */
class DynamicGroups
{
	/**
	 * Add the current user to a user group just for this page load.
	 *
	 * @param   int  $groupID  The group ID to add the current user into.
	 *
	 * @return  void
	 */
	public static function addGroup($groupID)
	{
		self::addRemoveGroup($groupID, true);
		self::cleanUpUserObjectCache();
	}

	/**
	 * Remove the current user from a user group just for this page load.
	 *
	 * @param   int  $groupID  The group ID to remove the current user from.
	 *
	 * @return  void
	 */
	public static function removeGroup($groupID)
	{
		self::addRemoveGroup($groupID, false);
		self::cleanUpUserObjectCache();
	}

	/**
	 * Internal function to add or remove the current user from a user group just for this page load.
	 *
	 * @param   int   $groupID  The group ID to add / remove the current user from.
	 * @param   bool  $add      Add (true) or remove (false) the user?
	 *
	 * @return  void
	 */
	protected static function addRemoveGroup($groupID, $add)
	{
		/**
		 * Make sure that Joomla has retrieved the user's groups from the database.
		 *
		 * By going through the User object's getAuthorisedGroups we force Joomla to go through Access::getGroupsByUser
		 * which retrieves the information from the database and caches it into the Access helper class.
		 */
		Factory::getUser()->getAuthorisedGroups();

		/**
		 * Now we can get a Reflection object into Joomla's Access helper class and manipulate its groupsByUser cache.
		 */
		$className = class_exists('Joomla\\CMS\\Access\\Access') ? 'Joomla\\CMS\\Access\\Access' : 'JAccess';

		try
		{
			$reflectedAccess = new \ReflectionClass($className);
		}
		catch (\ReflectionException $e)
		{
			// This should never happen!
			return;
		}

		$groupsByUser = $reflectedAccess->getProperty('groupsByUser');
		$groupsByUser->setAccessible(true);
		$rawGroupsByUser = $groupsByUser->getValue();

		/**
		 * Next up, we need to manipulate the keys of the cache which contain user to user group assignments.
		 *
		 * $rawGroupsByUser (JAccess::$groupsByUser) stored the group ownership as userID:recursive e.g. 0:1 for the
		 * default user, recursive. We need to deal with four keys: 0:1, 0:0, myID:1 and myID:0
		 */
		$user = Factory::getUser();
		$keys = ['0:1', '0:0', $user->id . ':1', $user->id . ':0'];

		foreach ($keys as $key)
		{
			if (!array_key_exists($key, $rawGroupsByUser))
			{
				continue;
			}

			$groups = $rawGroupsByUser[$key];

			if ($add)
			{
				if (in_array($groupID, $groups))
				{
					continue;
				}

				$groups[] = $groupID;
			}
			else
			{
				if (!in_array($groupID, $groups))
				{
					continue;
				}

				$removeKey = array_search($groupID, $groups);
				unset($groups[$removeKey]);
			}

			$rawGroupsByUser[$key] = $groups;
		}

		// We can commit our changes back to the cache property and make it publicly inaccessible again.
		$groupsByUser->setValue(null, $rawGroupsByUser);
		$groupsByUser->setAccessible(false);

		/**
		 * We are not done. Caching user groups is only one aspect of Joomla access management. Joomla also caches the
		 * identities, i.e. the user group assignment per user, in a different cache. We need to reset it to for our
		 * user.
		 *
		 * Do note that we CAN NOT use clearStatics since that also clears the user group assignment which we assigned
		 * dynamically. Therefore calling it would destroy our work so far.
		 */
		$refProperty = $reflectedAccess->getProperty('identities');
		$refProperty->setAccessible(true);
		$identities = $refProperty->getValue();

		$keys = array($user->id, 0);

		foreach ($keys as $key)
		{
			if (!array_key_exists($key, $identities))
			{
				continue;
			}

			unset($identities[$key]);
		}

		$refProperty->setValue(null, $identities);
		$refProperty->setAccessible(false);
	}

	/**
	 * Clean up the current user's authenticated groups cache.
	 *
	 * @return  void
	 */
	protected static function cleanUpUserObjectCache()
	{
		$user          = Factory::getUser();
		$reflectedUser = new \ReflectionObject($user);

		// Clear the user group cache
		$refProperty   = $reflectedUser->getProperty('_authGroups');
		$refProperty->setAccessible(true);
		$refProperty->setValue($user, array());
		$refProperty->setAccessible(false);

		// Clear the view access level cache
		$refProperty   = $reflectedUser->getProperty('_authLevels');
		$refProperty->setAccessible(true);
		$refProperty->setValue($user, array());
		$refProperty->setAccessible(false);

		// Clear the authenticated actions cache. I haven't seen it used anywhere but it's there, so...
		$refProperty   = $reflectedUser->getProperty('_authActions');
		$refProperty->setAccessible(true);
		$refProperty->setValue($user, array());
		$refProperty->setAccessible(false);
	}
}