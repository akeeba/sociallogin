<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Akeeba\SocialLogin\Features;

// Prevent direct access
defined('_JEXEC') or die;

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
			$id = isset($data['id']) ? $data['id'] : null;
		}
		elseif (is_object($data) && is_null($data) && ($data instanceof JRegistry))
		{
			$id = $data->get('id');
		}
		elseif (is_object($data) && !is_null($data))
		{
			$id = isset($data->id) ? $data->id : null;
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