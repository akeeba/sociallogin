<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

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
		$user_id = $this->form->getData()->get('id', null);

		if (is_null($user_id))
		{
			return JText::_('PLG_SYSTEM_SOCIALLOGIN_ERR_NOUSER');
		}

		$user = JFactory::getUser($user_id);

		// Render and return buttons
		return SocialLoginHelperIntegrations::getSocialLinkButtons($user);
	}
}