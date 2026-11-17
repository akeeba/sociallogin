<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Regenerates plugins/system/sociallogin/src/Dependencies: the namespace-scoped copies of the bundled third-party
 * libraries listed in build/scoped-packages.php.
 *
 * Run it through Composer, after `composer install` / `composer update` changed one of those packages:
 *   composer scope-dependencies
 *
 * Then run `composer test` and commit the regenerated folder. See .claude/memory/bundled-dependencies.md.
 */

declare(strict_types=1);

$root      = dirname(__DIR__);
$packages  = require __DIR__ . '/scoped-packages.php';
$target    = $root . '/plugins/system/sociallogin/src/Dependencies';
$tmp       = $root . '/build/tmp-scoped';
$phpScoper = $root . '/vendor/bin/php-scoper';

$rmrf = static function (string $dir) use (&$rmrf): void {
	if (!is_dir($dir))
	{
		return;
	}

	foreach (new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS) as $item)
	{
		$item->isDir() && !$item->isLink() ? $rmrf($item->getPathname()) : unlink($item->getPathname());
	}

	rmdir($dir);
};

if (!is_file($phpScoper))
{
	fwrite(STDERR, "vendor/bin/php-scoper is missing. Run `composer install` first.\n");
	exit(1);
}

$rmrf($tmp);

// php-scoper 0.18.7 (the newest release installable on our PHP 8.1 platform) emits deprecation notices on newer PHP.
$command = sprintf(
	'%s -d error_reporting=%s %s add-prefix --config=%s --output-dir=%s --force --no-interaction',
	escapeshellarg(PHP_BINARY),
	escapeshellarg((string) (E_ALL & ~E_DEPRECATED)),
	escapeshellarg($phpScoper),
	escapeshellarg($root . '/scoper.inc.php'),
	escapeshellarg($tmp)
);

passthru($command, $exitCode);

if ($exitCode !== 0)
{
	$rmrf($tmp);
	fwrite(STDERR, "php-scoper failed with exit code $exitCode.\n");
	exit($exitCode);
}

// php-scoper writes each file at its path relative to the common ancestor of all scoped files (vendor/). Move each
// package's scoped source directory to its PSR-4 location under Dependencies/.
$rmrf($target);
mkdir($target, 0755, true);

foreach ($packages as $package => $info)
{
	$from = $tmp . '/' . $package . '/' . $info['source'];
	$to   = $target . '/' . $info['target'];

	if (!is_dir($from))
	{
		$rmrf($tmp);
		fwrite(STDERR, "Scoped output for $package not found at $from.\n");
		exit(1);
	}

	if (!is_dir(dirname($to)))
	{
		mkdir(dirname($to), 0755, true);
	}

	rename($from, $to);
}

$rmrf($tmp);

echo "Scoped dependencies written to plugins/system/sociallogin/src/Dependencies\n";
