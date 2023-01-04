<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') || die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\System\SocialLogin\Extension\SocialLogin;

return new class implements ServiceProviderInterface {
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 *
	 * @since   4.0.0
	 */
	public function register(Container $container)
	{
		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$subject = $container->get(DispatcherInterface::class);
				$config  = (array) PluginHelper::getPlugin('system', 'sociallogin');

				$plugin = new SocialLogin($subject, $config);

				$plugin->setApplication(\Joomla\CMS\Factory::getApplication());
				$plugin->setDatabase($container->get('DatabaseDriver'));

				$plugin->init();

				return $plugin;
			}
		);
	}
};
