<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\System\SocialLogin\Features;

// Prevent direct access
defined('_JEXEC') || die;

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form as JForm;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Table\Menu;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Event\Event;
use Joomla\Registry\Registry as JRegistry;
use Joomla\Utilities\ArrayHelper;

trait UserFields
{
	/**
	 * Is the current user allowed to edit the social login configuration of $user? To do so I must either be editing my
	 * own account OR I have to be a Super User.
	 *
	 * @param   User  $user  The user you want to know if we're allowed to edit
	 *
	 * @return  bool
	 * @since   4.1.0
	 */
	public function canEditUser($user = null)
	{
		// I can edit myself
		if (empty($user))
		{
			return true;
		}

		// Guests can't have social logins associated
		if ($user->guest)
		{
			return false;
		}

		// Get the currently logged in used
		$myUser = $this->app->getIdentity();

		// Same user? I can edit myself
		if ($myUser->id == $user->id)
		{
			return true;
		}

		// To edit a different user I must be a Super User myself. If I'm not, I can't edit another user!
		if (!$myUser->authorise('core.admin'))
		{
			return false;
		}

		// I am a Super User editing another user. That's allowed.
		return true;
	}

	/**
	 * Add the SocialLogin custom user profile field data to the core Joomla user profile data. This is required to
	 * populate the "sociallogin.dontremind" field with its current value.
	 *
	 * @param   Event  $event
	 *
	 * @return    void
	 */
	public function onContentPrepareData(Event $event): void
	{
		/**
		 * @param   string  $context  The context for the data (form name)
		 * @param   object  $data     The user profile data
		 */
		[$context, $data] = $event->getArguments();
		$result   = $event->getArgument('result') ?: [];
		$result   = is_array($result) ? $result : [$result];
		$result[] = true;

		$event->setArgument('result', $result);

		// Check we are manipulating a valid form.
		if (!in_array($context, ['com_admin.profile', 'com_users.user', 'com_users.profile', 'com_users.registration']))
		{
			return;
		}

		if (!is_object($data))
		{
			return;

		}

		$userId = $data->id ?? 0;

		if (isset($data->profile) || ($userId <= 0))
		{
			return;
		}

		// Load the profile data from the database.
		$db = $this->db;

		$query = $db->getQuery(true)
		            ->select([$db->qn('profile_key'), $db->qn('profile_value')])
		            ->from($db->qn('#__user_profiles'))
		            ->where($db->qn('user_id') . ' = ' . $db->q($userId))
		            ->where($db->qn('profile_key') . ' LIKE ' . $db->q('sociallogin.%', false))
		            ->order($db->qn('ordering'));

		try
		{
			$results = $db->setQuery($query)->loadRowList();
		}
		catch (Exception $e)
		{
			return;
		}

		// Merge the profile data.
		$data->sociallogin = [];

		foreach ($results as $v)
		{
			$k                     = str_replace('sociallogin.', '', $v[0]);
			$data->sociallogin[$k] = $v[1];
		}

		$result[] = true;
		$event->setArgument('result', $result);
	}

	/**
	 * Adds additional fields to the user editing form
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 *
	 */
	public function onContentPrepareForm(Event $event): void
	{
		/**
		 * @param   JForm  $form  The form to be altered.
		 * @param   mixed  $data  The associated data for the form.
		 */
		[$form, $data] = $event->getArguments();
		$result   = $event->getArgument('result') ?: [];
		$result   = is_array($result) ? $result : [$result];
		$result[] = true;

		$event->setArgument('result', $result);

		if (!$this->addLinkUnlinkButtons)
		{
			return;
		}

		// Check we are manipulating a valid form.
		if (!($form instanceof JForm))
		{
			return;
		}

		$name = $form->getName();

		if (!in_array($name, ['com_admin.profile', 'com_users.user', 'com_users.profile', 'com_users.registration']))
		{
			return;
		}

		$layout = $this->app->input->getCmd('layout', 'default');

		/**
		 * Joomla is kinda brain-dead. When we have a menu item to the Edit Profile page it does not push the layout
		 * into the Input (as opposed with option and view) so I have to go in and dig it out myself. Yikes!
		 */
		$itemId = $this->app->input->getInt('Itemid');

		if ($itemId)
		{
			try
			{
				/** @var Menu $menuItem */
				$menuItem = Table::getInstance('Menu');
				$menuItem->load($itemId);
				/** @noinspection PhpUndefinedFieldInspection */
				$uri    = new Uri($menuItem->link);
				$layout = $uri->getVar('layout', $layout);
			}
			catch (Exception $e)
			{
			}
		}

		if (!$this->app->isClient('administrator') && ($layout != 'edit'))
		{
			return;
		}

		// Get the user ID
		$id = null;

		if (is_array($data))
		{
			$id = $data['id'] ?? null;
		}
		elseif ($data instanceof JRegistry)
		{
			$id = $data->get('id');
		}
		elseif (is_object($data))
		{
			$id = $data->id ?? null;
		}

        $user = ($id === null)
            ? $this->app->getIdentity()
            : Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($id);

		// Make sure the loaded user is the correct one
		if ($user->id != $id)
		{
			return;
		}

		// Make sure I am either editing myself OR I am a Super User
		if (!$this->canEditUser($user))
		{
			return;
		}

		// Add the fields to the form. The custom Sociallogin field uses the Integrations to render the buttons.
		Log::add(
			'Injecting Social Login fields in user profile edit page',
			Log::DEBUG,
			'sociallogin.system'
		);

		$this->loadLanguage();
		JForm::addFormPath(__DIR__ . '/../../forms');
		$form->loadFile('sociallogin', false);

		// Should I show the Don't Remind field?
		if (!$this->params->get('show_dontremind', 0))
		{
			$form->removeField('dontremind', 'sociallogin');
		}
	}

	/**
	 * Remove all user profile information for the given user ID
	 *
	 * Method is called after user data is deleted from the database
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 *
	 */
	public function onUserAfterDelete(Event $event): void
	{
		/**
		 * @var   array  $user    Holds the user data
		 * @var   bool   $success True if user was successfully stored in the database
		 * @var   string $msg     Message
		 */
		[$user, $success, $msg] = $event->getArguments();
		$result = $event->getArgument('result') ?: [];
		$result = is_array($result) ? $result : [$result];

		if (!$success)
		{
			$result[] = false;

			$event->setArgument('result', $result);

			return;
		}

		$userId = ArrayHelper::getValue($user, 'id', 0, 'int');

		if ($userId)
		{
			Log::add(
				sprintf(
					'Removing Social Login information for deleted user #%s',
					$userId
				),
				Log::DEBUG,
				'sociallogin.system'
			);
			$db = $this->db;

			/** @noinspection SqlResolve */
			$query = $db->getQuery(true)
			            ->delete($db->qn('#__user_profiles'))
			            ->where($db->qn('user_id') . ' = ' . $db->q($userId))
			            ->where($db->qn('profile_key') . ' LIKE ' . $db->q('sociallogin.%', false));

			$db->setQuery($query)->execute();
		}

		$result[] = true;

		$event->setArgument('result', $result);
	}

	/**
	 * Save the custom SocialLogin user profile fields. It's called after Joomla saves a user to the database.
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 */
	public function onUserAfterSave(Event $event)
	{
		/**
		 * @var   array $data   The user profile data which was saved.
		 * @var   bool  $isNew  Is this a new user? (ignored)
		 * @var   bool  $result Was the user saved successfully?
		 * @var   mixed $error  (ignored)
		 */
		[$data, $isNew, $result, $error] = $event->getArguments();
		$result   = $event->getArgument('result') ?: [];
		$result   = is_array($result) ? $result : [$result];
		$result[] = true;

		$event->setArgument('result', $result);

		$userId = ArrayHelper::getValue($data, 'id', 0, 'int');

		if (!$userId || !$result || !isset($data['sociallogin']) || !is_array($data['sociallogin']) || !count($data['sociallogin']))
		{
			return;
		}


		$db         = $this->db;
		$fieldNames = array_map(function ($key) use ($db) {
			return $db->q('sociallogin.' . $key);
		}, array_keys($data['sociallogin']));

		$query = $db->getQuery(true)
		            ->delete($db->qn('#__user_profiles'))
		            ->where($db->qn('user_id') . ' = ' . $db->q($userId))
		            ->where($db->qn('profile_key') . ' IN (' . implode(',', $fieldNames) . ')');

		$db->setQuery($query)->execute();

		$order = 1;

		$query = $db->getQuery(true)
		            ->insert($db->qn('#__user_profiles'))
		            ->columns([
			            $db->qn('user_id'), $db->qn('profile_key'), $db->qn('profile_value'), $db->qn('ordering'),
		            ]);

		foreach ($data['sociallogin'] as $k => $v)
		{
			$query->values($userId . ', ' . $db->quote('sociallogin.' . $k) . ', ' . $db->quote($v) . ', ' . $order++);
		}

		$db->setQuery($query)->execute();

		// Reset the session flag; the user save operation may have changed the dontremind flag.
		$this->app->getSession()->set('sociallogin.islinked', null);
	}

}
