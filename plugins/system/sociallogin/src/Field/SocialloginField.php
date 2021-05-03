<?php
/*
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\System\SocialLogin\Field;

defined('_JEXEC') || die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\Plugin\System\SocialLogin\Library\Helper\Integrations;
use Joomla\Plugin\System\SocialLogin\Library\Helper\Joomla;

class SocialloginField extends FormField
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
			return Text::_('PLG_SYSTEM_SOCIALLOGIN_ERR_NOUSER');
		}

		$user = Joomla::getUser($user_id);

		// Render and return buttons
		return Integrations::getSocialLinkButtons($user);
	}
}