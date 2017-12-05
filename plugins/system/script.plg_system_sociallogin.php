<?php

class plgSystemSocialloginInstallerScript
{
	/**
	 * Obsolete files and folders to remove. Use path names relative to the site's root.
	 *
	 * @var   array
	 */
	protected $removeFiles = array(
		'files'   => array(
		),
		'folders' => array(
			// Version 1.x helpers, now migrated into Library
			'plugins/system/sociallogin/helper',
		),
	);

	/**
	 * Runs after install, update or discover_update. In other words, it executes after Joomla! has finished installing
	 * or updating your component. This is the last chance you've got to perform any additional installations, clean-up,
	 * database updates and similar housekeeping functions.
	 *
	 * @param   string                      $type   install, update or discover_update
	 * @param   \JInstallerAdapterComponent $parent Parent object
	 *
	 * @throws Exception
	 *
	 * @return  void
	 */
	public function postflight($type, $parent)
	{
		// Remove obsolete files and folders
		$this->removeFilesAndFolders($this->removeFiles);
	}

	/**
	 * Removes obsolete files and folders
	 *
	 * @param   array $removeList The files and directories to remove
	 */
	protected function removeFilesAndFolders($removeList)
	{
		// Remove files
		if (isset($removeList['files']) && !empty($removeList['files']))
		{
			foreach ($removeList['files'] as $file)
			{
				$f = JPATH_ROOT . '/' . $file;

				if (!is_file($f))
				{
					continue;
				}

				JFile::delete($f);
			}
		}

		// Remove folders
		if (isset($removeList['folders']) && !empty($removeList['folders']))
		{
			foreach ($removeList['folders'] as $folder)
			{
				$f = JPATH_ROOT . '/' . $folder;

				if (!is_dir($f))
				{
					continue;
				}

				JFolder::delete($f);
			}
		}
	}

}
