<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
use Akeeba\SocialLogin\Library\Helper\Integrations;
use Akeeba\SocialLogin\Library\Helper\Joomla;

defined('_JEXEC') or die;

if (!defined('_AkeebaSocialLoginJFormFieldParent_'))
{
	require_once __DIR__ . '/abstraction.php';
}

class JFormFieldSociallogin extends AkeebaSocialLoginJFormFieldParent
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

		$user = Joomla::getUser($user_id);

		// Render and return buttons
		return Integrations::getSocialLinkButtons($user);
	}
}
