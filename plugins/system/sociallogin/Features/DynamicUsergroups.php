<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Akeeba\SocialLogin\Features;

// Protect from unauthorized access
defined('_JEXEC') or die();

use Akeeba\SocialLogin\Library\Helper\DynamicGroups;
use Akeeba\SocialLogin\Library\Helper\Joomla;
use Joomla\CMS\Factory;
use Joomla\CMS\User\User;

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
	 * @return  void
	 *
	 * @since   3.0.1
	 */
	public function onAfterInitialise()
	{
		$user = Factory::getUser();

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
		$isLinked = Joomla::getSessionVar('islinked', null, 'sociallogin');

		// Session flag not set. Populate and store in session.
		if (is_null($isLinked))
		{
			$isLinked = $this->getSocialLoginLinkedStatus($user);

			Joomla::setSessionVar('islinked', $isLinked, 'sociallogin');
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
					DynamicGroups::addGroup($this->unlinkedUserGroup);
				}
				break;

			// Linked: add to linkedUserGroup
			case 1:
				if ($this->linkedUserGroup !== 0)
				{
					DynamicGroups::addGroup($this->linkedUserGroup);
				}
				break;
		}
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
		$db = Factory::getDbo();
		$query = $db->getQuery(true)
			->select([
				$db->qn('profile_key'),
				$db->qn('profile_value'),
			])
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