<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Akeeba\SocialLogin\Features;

// Prevent direct access
defined('_JEXEC') || die;

use Akeeba\SocialLogin\Library\Helper\Joomla;
use Exception;
use JArrayHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form as JForm;
use Joomla\CMS\Table\Menu;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry as JRegistry;
use Joomla\Utilities\ArrayHelper;

trait UserFields
{
	/**
	 * Add the SocialLogin custom user profile field data to the core Joomla user profile data. This is required to
	 * populate the "sociallogin.dontremind" field with its current value.
	 *
	 * @param	string	$context  The context for the data (form name)
	 * @param	object  $data	  The user profile data
	 *
	 * @return	bool
	 */
	public function onContentPrepareData($context, $data)
	{
		// Check we are manipulating a valid form.
		if (!in_array($context, ['com_admin.profile', 'com_users.user', 'com_users.profile', 'com_users.registration']))
		{
			return true;
		}

		if (!is_object($data))
		{
			return true;
		}

		$userId = $data->id ?? 0;

		if (isset($data->profile) || ($userId <= 0))
		{
			return true;
		}

		// Load the profile data from the database.
		$db = Factory::getDbo();

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
			return true;
		}

		// Merge the profile data.
		$data->sociallogin = [];

		foreach ($results as $v)
		{
			$k                     = str_replace('sociallogin.', '', $v[0]);
			$data->sociallogin[$k] = $v[1];
		}

		return true;
	}

	/**
	 * Adds additional fields to the user editing form
	 *
	 * @param   JForm  $form  The form to be altered.
	 * @param   mixed  $data  The associated data for the form.
	 *
	 * @return  boolean
	 *
	 * @throws  Exception
	 */
	public function onContentPrepareForm($form, $data)
	{
		if (!$this->addLinkUnlinkButtons)
		{
			return true;
		}

		// Check we are manipulating a valid form.
		if (!($form instanceof JForm))
		{
			return true;
		}

		$name = $form->getName();

		if (!in_array($name, array('com_admin.profile', 'com_users.user', 'com_users.profile', 'com_users.registration')))
		{
			return true;
		}

		$layout = Joomla::getApplication()->input->getCmd('layout', 'default');

		/**
		 * Joomla is kinda brain-dead. When we have a menu item to the Edit Profile page it does not push the layout
		 * into the Input (as opposed with option and view) so I have to go in and dig it out myself. Yikes!
		 */
		$itemId = Factory::getApplication()->input->getInt('Itemid');

		if ($itemId)
		{
			try
			{
				/** @var Menu $menuItem */
				$menuItem = Table::getInstance('Menu');
				$menuItem->load($itemId);
				$uri    = new Uri($menuItem->link);
				$layout = $uri->getVar('layout', $layout);
			}
			catch (Exception $e)
			{
			}
		}

		if (!Joomla::isAdminPage() && ($layout != 'edit'))
		{
			return true;
		}

		// Get the user ID
		$id = null;

		if (is_array($data))
		{
			$id = $data['id'] ?? null;
		}
		elseif (is_object($data) && is_null($data) && ($data instanceof JRegistry))
		{
			$id = $data->get('id');
		}
		elseif (is_object($data) && !is_null($data))
		{
			$id = $data->id ?? null;
		}

		$user = Joomla::getUser($id);

		// Make sure the loaded user is the correct one
		if ($user->id != $id)
		{
			return true;
		}

		// Make sure I am either editing myself OR I am a Super User
		if (!Joomla::canEditUser($user))
		{
			return true;
		}

		// Add the fields to the form. The custom Sociallogin field uses the Integrations to render the buttons.
		Joomla::log('system', 'Injecting Social Login fields in user profile edit page');
		$this->loadLanguage();
		JForm::addFormPath(dirname(__FILE__) . '/../fields');
		$form->loadFile('sociallogin', false);

		// Should I show the Don't Remind field?
		if (!$this->params->get('show_dontremind', 0))
		{
			$form->removeField('dontremind', 'sociallogin');
		}

		return true;
	}

	/**
	 * Save the custom SocialLogin user profile fields. It's called after Joomla saves a user to the database.
	 *
	 * @param   array   $data    The user profile data which was saved.
	 * @param   bool    $isNew   Is this a new user? (ignored)
	 * @param   bool    $result  Was the user saved successfully?
	 * @param   mixed   $error   (ignored)
	 *
	 * @return bool
	 */
	public function onUserAfterSave($data, $isNew, $result, $error)
	{
		$userId = ArrayHelper::getValue($data, 'id', 0, 'int');

		if (!$userId || !$result || !isset($data['sociallogin']) || !is_array($data['sociallogin']) || !count($data['sociallogin']))
		{
			return true;
		}


		$db         = Factory::getDbo();
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
			->columns([$db->qn('user_id'), $db->qn('profile_key'), $db->qn('profile_value'), $db->qn('ordering')]);

		foreach ($data['sociallogin'] as $k => $v)
		{
			$query->values($userId . ', ' . $db->quote('sociallogin.' . $k) . ', ' . $db->quote($v) . ', ' . $order++);
		}

		$db->setQuery($query)->execute();

		// Reset the session flag; the user save operation may have changed the dontremind flag.
		Joomla::setSessionVar('islinked', null, 'sociallogin');

		return true;
	}

	/**
	 * Remove all user profile information for the given user ID
	 *
	 * Method is called after user data is deleted from the database
	 *
	 * @param   array   $user     Holds the user data
	 * @param   bool    $success  True if user was successfully stored in the database
	 * @param   string  $msg      Message
	 *
	 * @return  bool
	 *
	 * @throws  Exception
	 */
	public function onUserAfterDelete($user, $success, $msg)
	{
		if (!$success)
		{
			return false;
		}

		if (class_exists('Joomla\\Utilities\\ArrayHelper'))
		{
			$userId = ArrayHelper::getValue($user, 'id', 0, 'int');
		}
		else
		{
			$userId	= JArrayHelper::getValue($user, 'id', 0, 'int');
		}

		if ($userId)
		{
			Joomla::log('system', "Removing Social Login information for deleted user #{$userId}");
			$db = Joomla::getDbo();

			$query = $db->getQuery(true)
				->delete($db->qn('#__user_profiles'))
				->where($db->qn('user_id').' = '.$db->q($userId))
				->where($db->qn('profile_key').' LIKE '.$db->q('sociallogin.%', false));

			$db->setQuery($query)->execute();
		}

		return true;
	}
}
