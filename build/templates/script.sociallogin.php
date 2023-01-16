<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

// Prevent direct access
use Joomla\CMS\Factory;

defined('_JEXEC') or die;

class Pkg_SocialloginInstallerScript
{
	protected $packageName = 'pkg_sociallogin';

	/**
	 * The minimum PHP version required to install this extension
	 *
	 * @var   string
	 */
	protected $minimumPHPVersion = '7.1.0';

	/**
	 * The minimum Joomla! version required to install this extension
	 *
	 * @var   string
	 */
	protected $minimumJoomlaVersion = '3.9.0';

	/**
	 * The maximum Joomla! version this extension can be installed on
	 *
	 * @var   string
	 */
	protected $maximumJoomlaVersion = '4.0.999';

	/**
	 * A list of extensions (modules, plugins) to enable after installation. Each item has four values, in this order:
	 * type (plugin, module, ...), name (of the extension), client (0=site, 1=admin), group (for plugins).
	 *
	 * @var array
	 */
	protected $extensionsToEnable = [
		// System plugins
		['plugin', 'sociallogin', 1, 'system'],
		// Social Login plugins
		['plugin', 'facebook', 1, 'sociallogin'],
	];

	protected $obsoletePlugins = [
		['sociallogin', 'twitter']
	];

	/**
	 * =================================================================================================================
	 * DO NOT EDIT BELOW THIS LINE
	 * =================================================================================================================
	 */

	/**
	 * Joomla! pre-flight event. This runs before Joomla! installs or updates the package. This is our last chance to
	 * tell Joomla! if it should abort the installation.
	 *
	 * In here we'll try to install FOF. We have to do that before installing the component since it's using an
	 * installation script extending FOF's InstallScript class. We can't use a <file> tag in the manifest to install FOF
	 * since the FOF installation is expected to fail if a newer version of FOF is already installed on the site.
	 *
	 * @param   string                     $type    Installation type (install, update, discover_install)
	 * @param   \JInstallerAdapterPackage  $parent  Parent object
	 *
	 * @return  boolean  True to let the installation proceed, false to halt the installation
	 */
	public function preflight($type, $parent)
	{
		// Do not run on uninstall.
		if ($type === 'uninstall')
		{
			return true;
		}

		// Check the minimum PHP version
		if (!version_compare(PHP_VERSION, $this->minimumPHPVersion, 'ge'))
		{
			$msg = "<p>You need PHP $this->minimumPHPVersion or later to install this package</p>";
			JLog::add($msg, JLog::WARNING, 'jerror');

			return false;
		}

		// Check the minimum Joomla! version
		if (!version_compare(JVERSION, $this->minimumJoomlaVersion, 'ge'))
		{
			$msg = "<p>You need Joomla! $this->minimumJoomlaVersion or later to install this component</p>";
			JLog::add($msg, JLog::WARNING, 'jerror');

			return false;
		}

		// Check the maximum Joomla! version
		if (!version_compare(JVERSION, $this->maximumJoomlaVersion, 'le'))
		{
			$msg = "<p>You need Joomla! $this->maximumJoomlaVersion or earlier to install this component</p>";
			JLog::add($msg, JLog::WARNING, 'jerror');

			return false;
		}

		return true;
	}

	/**
	 * Runs after install, update or discover_update. In other words, it executes after Joomla! has finished installing
	 * or updating your component. This is the last chance you've got to perform any additional installations, clean-up,
	 * database updates and similar housekeeping functions.
	 *
	 * @param   string                       $type   install, update or discover_update
	 * @param   \JInstallerAdapterComponent  $parent Parent object
	 */
	public function postflight($type, $parent)
	{
		// Do not run on uninstall.
		if ($type === 'uninstall')
		{
			return;
		}

		// Uninstall obsolete plugins
		$this->uninstallObsoletePlugins();

		/**
		 * Clean up the obsolete package update sites.
		 *
		 * If you specify a new update site location in the XML manifest Joomla will install it in the #__update_sites
		 * table but it will NOT remove the previous update site. This method removes the old update sites which are
		 * left behind by Joomla.
		 */
		if ($type !== 'install')
		{
			$this->removeObsoleteUpdateSites();
		}

		/**
		 * Clean the cache after installing the package.
		 *
		 * See bug report https://github.com/joomla/joomla-cms/issues/16147
		 */
		$conf = \JFactory::getConfig();
		$clearGroups = array('_system', 'com_modules', 'mod_menu', 'com_plugins', 'com_modules');
		$cacheClients = array(0, 1);

		foreach ($clearGroups as $group)
		{
			foreach ($cacheClients as $client_id)
			{
				try
				{
					$options = array(
						'defaultgroup' => $group,
						'cachebase' => ($client_id) ? JPATH_ADMINISTRATOR . '/cache' : $conf->get('cache_path', JPATH_SITE . '/cache')
					);

					/** @var JCache $cache */
					$cache = \JCache::getInstance('callback', $options);
					$cache->clean();
				}
				catch (Exception $exception)
				{
					$options['result'] = false;
				}

				// Trigger the onContentCleanCache event.
				try
				{
					JFactory::getApplication()->triggerEvent('onContentCleanCache', $options);
				}
				catch (Exception $e)
				{
					// Suck it up
				}
			}
		}
	}

	/**
	 * Tuns on installation (but not on upgrade). This happens in install and discover_install installation routes.
	 *
	 * @param   \JInstallerAdapterPackage  $parent  Parent object
	 *
	 * @return  bool
	 */
	public function install($parent)
	{
		// Enable the extensions we need to install
		$this->enableExtensions();

		return true;
	}

	/**
	 * Runs on uninstallation
	 *
	 * @param   \JInstallerAdapterPackage  $parent  Parent object
	 *
	 * @return  bool
	 */
	public function uninstall($parent)
	{
		// Reserved for future use
		return true;
	}


	/**
	 * Enable modules and plugins after installing them
	 */
	private function enableExtensions()
	{
		foreach ($this->extensionsToEnable as $ext)
		{
			$this->enableExtension($ext[0], $ext[1], $ext[2], $ext[3]);
		}
	}

	/**
	 * Enable an extension
	 *
	 * @param   string   $type    The extension type.
	 * @param   string   $name    The name of the extension (the element field).
	 * @param   integer  $client  The application id (0: Joomla CMS site; 1: Joomla CMS administrator).
	 * @param   string   $group   The extension group (for plugins).
	 */
	private function enableExtension($type, $name, $client = 1, $group = null)
	{
		try
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true)
			            ->update('#__extensions')
			            ->set($db->qn('enabled') . ' = ' . $db->q(1))
			            ->where('type = ' . $db->quote($type))
			            ->where('element = ' . $db->quote($name));
		}
		catch (\Exception $e)
		{
			return;
		}


		switch ($type)
		{
			case 'plugin':
				// Plugins have a folder but not a client
				$query->where('folder = ' . $db->quote($group));
				break;

			case 'language':
			case 'module':
			case 'template':
				// Languages, modules and templates have a client but not a folder
				$client = JApplicationHelper::getClientInfo($client, true);
				$query->where('client_id = ' . (int) $client->id);
				break;

			default:
			case 'library':
			case 'package':
			case 'component':
				// Components, packages and libraries don't have a folder or client.
				// Included for completeness.
				break;
		}

		try
		{
			$db->setQuery($query);
			$db->execute();
		}
		catch (\Exception $e)
		{
		}
	}

	private function uninstallObsoletePlugins()
	{
		$db = Factory::getDbo();

		JModelLegacy::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_installer/models', 'InstallerModel');
		/** @var InstallerModelManage $model */
		$model = JModelLegacy::getInstance('Manage', 'InstallerModel');

		foreach ($this->obsoletePlugins as $pluginDef)
		{
			list($folder, $element) = $pluginDef;

			// Does the plugin exist? If not, there's nothing to do here.
			$query = $db->getQuery(true)
				->select('*')
				->from('#__extensions')
				->where($db->qn('type') . ' = ' . $db->q('plugin'))
				->where($db->qn('folder') . ' = ' . $db->q($folder))
				->where($db->qn('element') . ' = ' . $db->q($element));
			try
			{
				$result = $db->setQuery($query)->loadAssoc();

				if (empty($result))
				{
					continue;
				}

				$eid = $result['extension_id'];
			}
			catch (Exception $e)
			{
				continue;
			}

			// Uninstall the plugin
			$model->remove([$eid]);
		}
	}

	/**
	 * Removes the obsolete update sites for the component, since now we're dealing with a package.
	 *
	 * Controlled by componentName, packageName and obsoleteUpdateSiteLocations
	 *
	 * Depends on getExtensionId, getUpdateSitesFor
	 *
	 * @return  void
	 */
	private function removeObsoleteUpdateSites()
	{
		// Initialize
		$deleteIDs = [];

		// Get package ID
		$packageID = $this->findPackageExtensionID($this->packageName);

		if (!$packageID)
		{
			return;
		}

		// All update sites for the packgae
		$deleteIDs = $this->getUpdateSitesFor($packageID);

		if (empty($deleteIDs))
		{
			$deleteIDs = [];
		}

		if (count($deleteIDs) <= 1)
		{
			return;
		}

		$deleteIDs = array_unique($deleteIDs);

		// Remove the latest update site, the one we just installed
		array_pop($deleteIDs);

		$db = \Joomla\CMS\Factory::getDbo();

		if (empty($deleteIDs) || !count($deleteIDs))
		{
			return;
		}

		// Delete the remaining update sites
		$deleteIDs = array_map([$db, 'q'], $deleteIDs);

		$query = $db->getQuery(true)
			->delete($db->qn('#__update_sites'))
			->where($db->qn('update_site_id') . ' IN(' . implode(',', $deleteIDs) . ')');

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			// Do nothing.
		}

		$query = $db->getQuery(true)
			->delete($db->qn('#__update_sites_extensions'))
			->where($db->qn('update_site_id') . ' IN(' . implode(',', $deleteIDs) . ')');

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			// Do nothing.
		}
	}

	/**
	 * Gets the ID of an extension
	 *
	 * @param   string  $element  Package extension element, e.g. pkg_foo
	 *
	 * @return  int  Extension ID or 0 on failure
	 */
	private function findPackageExtensionID($element)
	{
		$db    = \Joomla\CMS\Factory::getDbo();
		$query = $db->getQuery(true)
			->select($db->qn('extension_id'))
			->from($db->qn('#__extensions'))
			->where($db->qn('element') . ' = ' . $db->q($element))
			->where($db->qn('type') . ' = ' . $db->q('package'));

		try
		{
			$id = $db->setQuery($query, 0, 1)->loadResult();
		}
		catch (Exception $e)
		{
			return 0;
		}

		return empty($id) ? 0 : (int) $id;
	}

	/**
	 * Returns the update site IDs for the specified Joomla Extension ID.
	 *
	 * @param   int  $eid  Extension ID for which to retrieve update sites
	 *
	 * @return  array  The IDs of the update sites
	 */
	private function getUpdateSitesFor($eid = null)
	{
		$db    = \Joomla\CMS\Factory::getDbo();
		$query = $db->getQuery(true)
			->select($db->qn('s.update_site_id'))
			->from($db->qn('#__update_sites', 's'))
			->innerJoin($db->qn('#__update_sites_extensions', 'e') . 'ON(' . $db->qn('e.update_site_id') .
				' = ' . $db->qn('s.update_site_id') . ')'
			)
			->where($db->qn('e.extension_id') . ' = ' . $db->q($eid));

		try
		{
			$ret = $db->setQuery($query)->loadColumn();
		}
		catch (Exception $e)
		{
			return [];
		}

		return empty($ret) ? [] : $ret;
	}
}
