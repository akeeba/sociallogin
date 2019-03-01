<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
use Akeeba\SocialLogin\Library\Helper\Integrations;
use Akeeba\SocialLogin\Library\Helper\Joomla;
use Joomla\CMS\Form\FormField;

defined('_JEXEC') or die;

class JFormFieldSociallogin extends FormField
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
			return Joomla::_('PLG_SYSTEM_SOCIALLOGIN_ERR_NOUSER');
		}

		$user = Joomla::getUser($user_id);

		// Render and return buttons
		return Integrations::getSocialLinkButtons($user);
	}
}
