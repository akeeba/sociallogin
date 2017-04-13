<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

JLoader::register('LoginGuardViewTODO', JPATH_SITE . '/components/com_loginguard/views/TODO/view.html.php');
JLoader::register('LoginGuardModelTODO', JPATH_SITE . '/components/com_loginguard/models/TODO.php');

class JFormFieldSociallogin extends JFormField
{
	/**
	 * Element name
	 *
	 * @var   string
	 */
	protected $_name = 'Sociallogin';

	function getInput()
	{
		// Make sure we can load the classes we need
		if (!class_exists('LoginGuardViewTODO', true) || !class_exists('LoginGuardModelTODO', true))
		{
			return JText::_('PLG_USER_SOCIALLOGIN_ERR_NOCOMPONENT');
		}

		// Load the language files
		JFactory::getLanguage()->load('com_sociallogin', JPATH_SITE, null, true, true);

		$user_id = $this->form->getData()->get('id', null);

		if (is_null($user_id))
		{
			return JText::_('PLG_USER_SOCIALLOGIN_ERR_NOUSER');
		}

		$user = JFactory::getUser($user_id);

		// Get a model
		/** @var LoginGuardModelTODO $model */
		$model = new LoginGuardModelTODO();

		// Get a view object
		$view = new LoginGuardViewTODO(array(
			'base_path' => JPATH_SITE . '/components/com_sociallogin'
		));
		$view->setModel($model, true);
		$view->returnURL = base64_encode(JUri::getInstance()->toString());
		$view->user      = $user;

		return $view->display();
	}
}