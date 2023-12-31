<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\System\SocialLogin\Features;

// Protect from unauthorized access
defined('_JEXEC') || die();

use Joomla\CMS\User\User;
use Joomla\Event\Event;

/**
 * Feature: dynamically assign user groups based on whether the user had linked social media accounts
 *
 * @package Akeeba\SocialLogin\Features
 * @since   3.0.1
 */
trait DynamicUsergroups
{
	/**
	 * Dynamically assign users to user groups
	 *
	 * @param   Event  $e
	 *
	 * @return  void
	 *
	 * @since   3.0.1
	 */
	public function onAfterInitialise(Event $e): void
	{
		$user = $this->getApplication()->getIdentity();

		// Nothing to do for guest users
		if ($user->guest)
		{
			return;
		}

		// Nothing to do if neither optional parameter is set
		if (($this->linkedUserGroup === 0) && ($this->unlinkedUserGroup === 0))
		{
			return;
		}

		// Get the session flag
		$isLinked = $this->getApplication()->getSession()->get('sociallogin.islinked', null);

		// Session flag not set. Populate and store in session.
		if (is_null($isLinked))
		{
			$isLinked = $this->getSocialLoginLinkedStatus($user);

			$this->getApplication()->getSession()->set('sociallogin.islinked', $isLinked);
		}

		// Perform an action based on the sociallogin.islinked session flag.
		switch ($isLinked)
		{
			// Do not bother me: no operation
			case -1:
				break;

			// Not linked: add to unlinkedUserGroup
			case 0:
				if ($this->unlinkedUserGroup !== 0)
				{
					$this->addUserToGroup($user, $this->unlinkedUserGroup);
				}
				break;

			// Linked: add to linkedUserGroup
			case 1:
				if ($this->linkedUserGroup !== 0)
				{
					$this->addUserToGroup($user, $this->linkedUserGroup);
				}
				break;
		}
	}

	/**
	 * Internal function to add or remove the current user from a user group just for this page load.
	 *
	 * @param   User  $user     The user to add to a group just for this pageload.
	 * @param   int   $groupID  The group ID to add / remove the current user from.
	 *
	 * @return  void
	 * @throws \ReflectionException
	 */
	private function addUserToGroup(User $user, int $groupID): void
	{
		/**
		 * Make sure that Joomla has retrieved the user's groups from the database.
		 *
		 * By going through the User object's getAuthorisedGroups we force Joomla to go through Access::getGroupsByUser
		 * which retrieves the information from the database and caches it into the Access helper class.
		 */
		$user->getAuthorisedGroups();

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

		if (version_compare(PHP_VERSION, '8.3.0', 'ge'))
		{
			$rawGroupsByUser = $reflectedAccess->getStaticPropertyValue('groupsByUser');
		}
		else
		{
			$rawGroupsByUser = $groupsByUser->getValue();
		}

		/**
		 * Next up, we need to manipulate the keys of the cache which contain user to user group assignments.
		 *
		 * $rawGroupsByUser (JAccess::$groupsByUser) stored the group ownership as userID:recursive e.g. 0:1 for the
		 * default user, recursive. We need to deal with four keys: 0:1, 0:0, myID:1 and myID:0
		 */
		$keys = ['0:1', '0:0', $user->id . ':1', $user->id . ':0'];

		foreach ($keys as $key)
		{
			if (!array_key_exists($key, $rawGroupsByUser))
			{
				continue;
			}

			$groups = $rawGroupsByUser[$key];

			if (in_array($groupID, $groups))
			{
				continue;
			}

			$groups[] = $groupID;

			$rawGroupsByUser[$key] = $groups;
		}

		// We can commit our changes back to the cache property and make it publicly inaccessible again.
		if (version_compare(PHP_VERSION, '8.3.0', 'ge'))
		{
			$reflectedAccess->setStaticPropertyValue('groupsByUser', $groupsByUser);
		}
		else
		{
			$groupsByUser->setValue(null, $rawGroupsByUser);
		}

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

		if (version_compare(PHP_VERSION, '8.3.0', 'ge'))
		{
			$identities = $reflectedAccess->getStaticPropertyValue('identities');
		}
		else
		{
			$identities = $refProperty->getValue();
		}

		$keys = [$user->id, 0];

		foreach ($keys as $key)
		{
			if (!array_key_exists($key, $identities))
			{
				continue;
			}

			unset($identities[$key]);
		}

		if (version_compare(PHP_VERSION, '8.3.0', 'ge'))
		{
			$reflectedAccess->setStaticPropertyValue('identities', $identities);
		}
		else
		{
			$refProperty->setValue(null, $identities);
		}

		$refProperty->setAccessible(false);

		$reflectedUser = new \ReflectionObject($user);

		// Clear the user group cache
		$refProperty = $reflectedUser->getProperty('_authGroups');
		$refProperty->setAccessible(true);
		$refProperty->setValue($user, []);
		$refProperty->setAccessible(false);

		// Clear the view access level cache
		$refProperty = $reflectedUser->getProperty('_authLevels');
		$refProperty->setAccessible(true);
		$refProperty->setValue($user, []);
		$refProperty->setAccessible(false);

		// Clear the authenticated actions cache. I haven't seen it used anywhere but it's there, so...
		$refProperty = $reflectedUser->getProperty('_authActions');
		$refProperty->setAccessible(true);
		$refProperty->setValue($user, []);
		$refProperty->setAccessible(false);
	}

	/**
	 * Get the SocialLogin account linked status
	 *
	 * @param   User  $user  The user object
	 *
	 * @return  int  -1 Don't bother me; 0 Not linked; 1 Linked
	 *
	 * @since   3.0.1
	 */
	private function getSocialLoginLinkedStatus(User $user)
	{
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select(
				[
					$db->qn('profile_key'),
					$db->qn('profile_value'),
				]
			)
			->from($db->qn('#__user_profiles'))
			->where($db->qn('profile_key') . ' LIKE ' . $db->q('sociallogin.%'))
			->where($db->qn('user_id') . ' = ' . $db->q($user->id));

		$profileValues = $db->setQuery($query)->loadAssocList('profile_key');

		if (empty($profileValues))
		{
			return 0;
		}

		// Is "Don't remind me" flag set?
		if (isset($profileValues['sociallogin.dontremind']))
		{
			if ($profileValues['sociallogin.dontremind']['profile_value'] == 1)
			{
				return -1;
			}

			// Unset the flag; the remaining options are the SocialLogin links to social network accounts.
			unset($profileValues['sociallogin.dontremind']);
		}

		return (count($profileValues) > 0) ? 1 : 0;
	}

}
