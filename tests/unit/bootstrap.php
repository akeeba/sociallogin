<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Bootstrap for the unit test layer.
 *
 * Deliberately thin: no Joomla application, no database. It only makes the classes under test autoloadable.
 *
 * The third-party libraries are loaded from the namespace-scoped copy committed under
 * plugins/system/sociallogin/src/Dependencies — the code we actually ship — never from vendor/. Scoping rewrites the
 * code (namespaces, string class names), so testing the unprefixed vendor/ copy would not catch a scoping bug.
 */

declare(strict_types=1);

define('_JEXEC', 1);

$repoRoot = dirname(__DIR__, 2);

if (!is_dir($repoRoot . '/plugins/system/sociallogin/src/Dependencies'))
{
	fwrite(
		STDERR,
		"plugins/system/sociallogin/src/Dependencies is missing. It is committed; restore it, or regenerate it with "
		. "`composer scope-dependencies`.\n"
	);

	exit(1);
}

spl_autoload_register(
	static function (string $class) use ($repoRoot): void
	{
		static $prefixes = [
			'Akeeba\\Plugin\\System\\SocialLogin\\Dependencies\\' => 'plugins/system/sociallogin/src/Dependencies',
			'Akeeba\\SocialLogin\\Tests\\Unit\\'                  => 'tests/unit',
		];

		foreach ($prefixes as $prefix => $directory)
		{
			if (strncmp($class, $prefix, strlen($prefix)) !== 0)
			{
				continue;
			}

			$file = $repoRoot . '/' . $directory . '/'
			        . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

			if (is_file($file))
			{
				require_once $file;
			}

			return;
		}
	}
);
