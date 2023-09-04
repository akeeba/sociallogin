<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\Database\DatabaseInterface;

defined('_JEXEC') or die;

class Pkg_SocialloginInstallerScript extends InstallerScript
{
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

	protected $minimumPhp = '7.4.0';

	protected $minimumJoomla = '4.3.0';

	protected $allowDowngrades = true;

	protected $obsoletePlugins = [
		// Remove Twitter integration, 4.4.0
		['sociallogin', 'twitter']
	];

	protected $packageName = 'pkg_sociallogin';

	/**
	 * =================================================================================================================
	 * DO NOT EDIT BELOW THIS LINE
	 * =================================================================================================================
	 */

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
	 * Runs after install, update or discover_update. In other words, it executes after Joomla! has finished installing
	 * or updating your component. This is the last chance you've got to perform any additional installations, clean-up,
	 * database updates and similar housekeeping functions.
	 *
	 * @param   string                       $type    install, update or discover_update
	 * @param   \JInstallerAdapterComponent  $parent  Parent object
	 */
	public function postflight($type, $parent)
	{
		// Do not run on uninstall.
		if ($type === 'uninstall')
		{
			return true;
		}

		// Forcibly create the autoload_psr4.php file afresh.
		if (class_exists(JNamespacePsr4Map::class))
		{
			try
			{
				$nsMap = new JNamespacePsr4Map();

				@clearstatcache(JPATH_CACHE . '/autoload_psr4.php');

				if (function_exists('opcache_invalidate'))
				{
					@opcache_invalidate(JPATH_CACHE . '/autoload_psr4.php');
				}

				@clearstatcache(JPATH_CACHE . '/autoload_psr4.php');
				$nsMap->create();

				if (function_exists('opcache_invalidate'))
				{
					@opcache_invalidate(JPATH_CACHE . '/autoload_psr4.php');
				}

				$nsMap->load();
			}
			catch (\Throwable $e)
			{
				// In case of failure, just try to delete the old autoload_psr4.php file
				if (function_exists('opcache_invalidate'))
				{
					@opcache_invalidate(JPATH_CACHE . '/autoload_psr4.php');
				}

				@unlink(JPATH_CACHE . '/autoload_psr4.php');
				@clearstatcache(JPATH_CACHE . '/autoload_psr4.php');
			}
		}

		$this->invalidateFiles();

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
		$app          = Factory::getApplication();
		$clearGroups  = ['_system', 'com_modules', 'mod_menu', 'com_plugins', 'com_modules'];
		$cacheClients = [0, 1];

		foreach ($clearGroups as $group)
		{
			foreach ($cacheClients as $client_id)
			{
				try
				{
					$options = [
						'defaultgroup' => $group,
						'cachebase'    => ($client_id) ? JPATH_ADMINISTRATOR . '/cache' : $app->get('cache_path', JPATH_SITE . '/cache'),
					];

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
					$app->triggerEvent('onContentCleanCache', $options);
				}
				catch (Exception $e)
				{
					// Suck it up
				}
			}
		}

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
			$db    = Factory::getContainer()->get(DatabaseInterface::class);
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
	 * Gets the ID of an extension
	 *
	 * @param   string  $element  Package extension element, e.g. pkg_foo
	 *
	 * @return  int  Extension ID or 0 on failure
	 */
	private function findPackageExtensionID($element)
	{
		/** @var \Joomla\Database\DatabaseDriver $db */
		$db    = Factory::getContainer()->get(DatabaseInterface::class);
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
		/** @var \Joomla\Database\DatabaseDriver $db */
		$db    = Factory::getContainer()->get(DatabaseInterface::class);
		$query = $db->getQuery(true)
			->select($db->qn('s.update_site_id'))
			->from($db->qn('#__update_sites', 's'))
			->innerJoin(
				$db->qn('#__update_sites_extensions', 'e') . 'ON(' . $db->qn('e.update_site_id') .
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

		/** @var \Joomla\Database\DatabaseDriver $db */
		$db = Factory::getContainer()->get(DatabaseInterface::class);

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

	private function uninstallObsoletePlugins()
	{
		/** @var \Joomla\Database\DatabaseDriver $db */
		$db = Factory::getContainer()->get(DatabaseInterface::class);

		/** @var \Joomla\CMS\MVC\Factory\MVCFactory $mvcFactory */
		$mvcFactory = Factory::getApplication()
			->bootComponent('com_installer')
			->getMVCFactory();
		/** @var \Joomla\Component\Installer\Administrator\Model\ManageModel $model */
		$model = $mvcFactory->createModel('Manage', 'administrator');

		foreach ($this->obsoletePlugins as $pluginDef)
		{
			[$folder, $element] = $pluginDef;

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

	private function invalidateFiles()
	{
		function getManifestXML($class): ?SimpleXMLElement
		{
			// Get the package element name
			$myPackage = strtolower(str_replace('InstallerScript', '', $class));

			// Get the package's manifest file
			$filePath = JPATH_MANIFESTS . '/packages/' . $myPackage . '.xml';

			if (!@file_exists($filePath) || !@is_readable($filePath))
			{
				return null;
			}

			$xmlContent = @file_get_contents($filePath);

			if (empty($xmlContent))
			{
				return null;
			}

			return new SimpleXMLElement($xmlContent);
		}

		function xmlNodeToExtensionName(SimpleXMLElement $fileField): ?string
		{
			$type = (string) $fileField->attributes()->type;
			$id   = (string) $fileField->attributes()->id;

			switch ($type)
			{
				case 'component':
				case 'file':
				case 'library':
					$extension = $id;
					break;

				case 'plugin':
					$group     = (string) $fileField->attributes()->group ?? 'system';
					$extension = 'plg_' . $group . '_' . $id;
					break;

				case 'module':
					$client    = (string) $fileField->attributes()->client ?? 'site';
					$extension = (($client != 'site') ? 'a' : '') . $id;
					break;

				default:
					$extension = null;
					break;
			}

			return $extension;
		}

		function getExtensionsFromManifest(?SimpleXMLElement $xml): array
		{
			if (empty($xml))
			{
				return [];
			}

			$extensions = [];

			foreach ($xml->xpath('//files/file') as $fileField)
			{
				$extensions[] = xmlNodeToExtensionName($fileField);
			}

			return array_filter($extensions);
		}

		function clearFileInOPCache(string $file): bool
		{
			static $hasOpCache = null;

			if (is_null($hasOpCache))
			{
				$hasOpCache = ini_get('opcache.enable')
				              && function_exists('opcache_invalidate')
				              && (!ini_get('opcache.restrict_api')
				                  || stripos(
					                     realpath($_SERVER['SCRIPT_FILENAME']), ini_get('opcache.restrict_api')
				                     ) === 0);
			}

			if ($hasOpCache && (strtolower(substr($file, -4)) === '.php'))
			{
				$ret = opcache_invalidate($file, true);

				@clearstatcache($file);

				return $ret;
			}

			return false;
		}

		function recursiveClearCache(string $path): void
		{
			if (!@is_dir($path))
			{
				return;
			}

			/** @var DirectoryIterator $file */
			foreach (new DirectoryIterator($path) as $file)
			{
				if ($file->isDot() || $file->isLink())
				{
					continue;
				}

				if ($file->isDir())
				{
					recursiveClearCache($file->getPathname());

					continue;
				}

				if (!$file->isFile())
				{
					continue;
				}

				clearFileInOPCache($file->getPathname());
			}
		}

		$extensionsFromPackage = getExtensionsFromManifest(getManifestXML(__CLASS__));

		foreach ($extensionsFromPackage as $element)
		{
			$paths = [];

			if (strpos($element, 'plg_') === 0)
			{
				[$dummy, $folder, $plugin] = explode('_', $element);

				$paths = [
					sprintf('%s/%s/%s/services', JPATH_PLUGINS, $folder, $plugin),
					sprintf('%s/%s/%s/src', JPATH_PLUGINS, $folder, $plugin),
				];
			}
			elseif (strpos($element, 'com_') === 0)
			{
				$paths = [
					sprintf('%s/components/%s/services', JPATH_ADMINISTRATOR, $element),
					sprintf('%s/components/%s/src', JPATH_ADMINISTRATOR, $element),
					sprintf('%s/components/%s/src', JPATH_SITE, $element),
					sprintf('%s/components/%s/src', JPATH_API, $element),
				];
			}
			elseif (strpos($element, 'mod_') === 0)
			{
				$paths = [
					sprintf('%s/modules/%s/services', JPATH_ADMINISTRATOR, $element),
					sprintf('%s/modules/%s/src', JPATH_ADMINISTRATOR, $element),
					sprintf('%s/modules/%s/services', JPATH_SITE, $element),
					sprintf('%s/modules/%s/src', JPATH_SITE, $element),
				];
			}
			else
			{
				continue;
			}

			foreach ($paths as $path)
			{
				recursiveClearCache($path);
			}
		}

		clearFileInOPCache(JPATH_CACHE . '/autoload_psr4.php');
	}
}
