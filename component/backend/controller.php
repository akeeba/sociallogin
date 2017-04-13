<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

class SocialLoginController extends JControllerLegacy
{
	/**
	 * Method to display a view.
	 *
	 * @param   boolean  $cachable   If true, the view output will be cached.
	 * @param   boolean  $urlparams  An array of safe url parameters and their variable types, for valid values see {@link JFilterInput::clean()}.
	 *
	 * @return  JControllerLegacy  This object to support chaining.
	 */
	public function display($cachable = false, $urlparams = false)
	{
		$this->setRedirect(JRoute::_('index.php?option=com_sociallogin&task=TODO.USER', false));

		// If you're a super user you get to see the another page instead
		if (JFactory::getUser()->authorise('core.admin'))
		{
			$this->setRedirect(JRoute::_('index.php?option=com_sociallogin&task=TODO.SUPERUSER', false));
		}

		return $this;
	}

}