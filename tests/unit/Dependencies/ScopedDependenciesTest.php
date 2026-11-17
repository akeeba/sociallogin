<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Akeeba\SocialLogin\Tests\Unit\Dependencies;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Sanity checks on the namespace-scoped third-party code in plugins/system/sociallogin/src/Dependencies.
 */
class ScopedDependenciesTest extends TestCase
{
	private const PREFIX = 'Akeeba\\Plugin\\System\\SocialLogin\\Dependencies\\';

	private static function dependenciesDir(): string
	{
		return dirname(__DIR__, 3) . '/plugins/system/sociallogin/src/Dependencies';
	}

	/**
	 * @return  string[]  Every PHP file under Dependencies/, relative to it.
	 */
	private static function dependencyFiles(): array
	{
		$dir   = self::dependenciesDir();
		$files = [];

		foreach (
			new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS))
			as $file
		)
		{
			if ($file->getExtension() === 'php')
			{
				$files[] = substr($file->getPathname(), strlen($dir) + 1);
			}
		}

		sort($files);

		return $files;
	}

	/**
	 * Every shipped class, interface, trait and enum loads, so pruning never left a dangling parent class,
	 * interface or trait behind.
	 *
	 * Runs in a separate process so that loading every class cannot affect the other tests.
	 */
	#[RunInSeparateProcess]
	public function testEveryScopedSymbolLoads(): void
	{
		$count = 0;

		foreach (self::dependencyFiles() as $relativePath)
		{
			$symbol = self::PREFIX . str_replace('/', '\\', substr($relativePath, 0, -4));
			$exists = class_exists($symbol) || interface_exists($symbol) || trait_exists($symbol)
			          || enum_exists($symbol);

			$this->assertTrue($exists, "Cannot load $symbol from $relativePath");

			$count++;
		}

		$this->assertGreaterThan(100, $count);
	}

	/**
	 * Only the bundled libraries' own namespaces sit under our prefix.
	 *
	 * Catches the scoper wrongly prefixing a PHP built-in it does not know about, e.g. a newer constant probed with
	 * defined('…') (always false once prefixed), an exception class in a catch clause (never caught), or the
	 * #[\Override] attribute.
	 */
	public function testNothingElseIsPrefixed(): void
	{
		$dir   = self::dependenciesDir();
		$found = [];

		foreach (self::dependencyFiles() as $relativePath)
		{
			preg_match_all(
				'/SocialLogin\\\\{1,2}Dependencies\\\\{1,2}(\w+)/',
				file_get_contents($dir . '/' . $relativePath),
				$matches
			);

			foreach ($matches[1] as $name)
			{
				$found[$name][] = $relativePath;
			}
		}

		$unexpected = array_diff_key($found, array_flip(['phpseclib3', 'Lcobucci', 'CoderCat', 'ParagonIE']));

		$this->assertSame(
			[],
			array_map(static fn(array $files): array => array_values(array_unique($files)), $unexpected),
			'Symbols which should not have been prefixed. Add them to the exclude-* lists in scoper.inc.php.'
		);
	}

	public static function unprefixedReferenceProvider(): iterable
	{
		foreach (['phpseclib3', 'Lcobucci', 'CoderCat', 'ParagonIE'] as $namespace)
		{
			yield $namespace => [$namespace];
		}
	}

	/**
	 * No scoped file refers to a bundled library by its original, unprefixed namespace, e.g. a class name built by
	 * string concatenation (`'phpseclib3\Math\BigInteger\Engines\\' . $engine`) which the scoper did not rewrite.
	 * At runtime such a reference silently resolves to another extension's copy of the library, or to nothing.
	 */
	#[DataProvider('unprefixedReferenceProvider')]
	public function testNoUnprefixedReferences(string $namespace): void
	{
		$dir = self::dependenciesDir();

		foreach (self::dependencyFiles() as $relativePath)
		{
			// Code and string literals only; comments may legitimately name the original namespace.
			$contents = '';

			foreach (token_get_all(file_get_contents($dir . '/' . $relativePath)) as $token)
			{
				if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true))
				{
					continue;
				}

				$contents .= is_array($token) ? $token[1] : $token;
			}

			// A namespace name not preceded by a word character or a backslash is a reference outside our prefix.
			$this->assertDoesNotMatchRegularExpression(
				'/(?<![\w\\\\])\\\\{0,2}' . $namespace . '\\\\{1,2}\w/',
				$contents,
				"$relativePath refers to the unprefixed $namespace namespace"
			);
		}
	}
}
