<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Akeeba\SocialLogin\Tests\Unit\Guards;

use PHPUnit\Framework\TestCase;

/**
 * Static guards for defects which only show on a live Joomla site (the unit layer has no Joomla or database), and
 * which slipped through before.
 */
class SourceGuardsTest extends TestCase
{
	private static function root(): string
	{
		return dirname(__DIR__, 3) . '/plugins';
	}

	/**
	 * @return string[]
	 */
	private static function phpFiles(): array
	{
		$ret      = [];
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root(), \FilesystemIterator::SKIP_DOTS));

		foreach ($iterator as $file)
		{
			/** @var \SplFileInfo $file */
			if ($file->getExtension() === 'php' && !str_contains($file->getPathname(), '/Dependencies/'))
			{
				$ret[] = $file->getPathname();
			}
		}

		sort($ret);

		return $ret;
	}

	/**
	 * Joomla's QueryInterface::bindArray() defaults its data type to INTEGER. Binding a string without saying so turns
	 * it into 0, e.g. user profile keys were saved as "0" and account linking silently broke.
	 */
	public function testBindArrayAlwaysStatesItsDataType(): void
	{
		$offenders = [];

		foreach (self::phpFiles() as $file)
		{
			$code = file_get_contents($file);

			if (!preg_match_all('/->bindArray\(/', $code, $matches, PREG_OFFSET_CAPTURE))
			{
				continue;
			}

			foreach ($matches[0] as [$match, $offset])
			{
				$start = $offset + strlen($match);
				$depth = 1;
				$commas = 0;

				for ($i = $start, $n = strlen($code); $i < $n && $depth > 0; $i++)
				{
					$c = $code[$i];

					if ($c === '(' || $c === '[')
					{
						$depth++;
					}
					elseif ($c === ')' || $c === ']')
					{
						$depth--;
					}
					elseif ($c === ',' && $depth === 1)
					{
						$commas++;
					}
				}

				if ($commas < 1)
				{
					$offenders[] = substr($file, strlen(self::root()) + 1) . ':' . (substr_count(substr($code, 0, $offset), "\n") + 1);
				}
			}
		}

		$this->assertSame([], $offenders, 'bindArray() calls without an explicit data type: ' . implode(', ', $offenders));
	}

	/**
	 * Link / unlink buttons are rendered inside the user edit form. HTML forbids nested forms: the browser drops the
	 * inner <form>, and the button submits the outer form instead, so nothing happens.
	 */
	public function testUnlinkButtonDoesNotRenderANestedForm(): void
	{
		$layout = file_get_contents(self::root() . '/system/sociallogin/layout/akeeba/sociallogin/linkbutton.php');

		$this->assertStringNotContainsString('<form', $layout);
		$this->assertStringContainsString('data-sociallogin-unlink', $layout);
		$this->assertFileExists(self::root() . '/system/sociallogin/media/js/unlink.js');
	}

	/**
	 * The callback arrives at the frontend, which has a separate session from the backend, so the frontend must not
	 * decide where to route a callback by reading the (frontend) session.
	 */
	public function testBackendCallbackRoutingDoesNotDependOnTheSession(): void
	{
		$code = file_get_contents(self::root() . '/system/sociallogin/src/Extension/SocialLogin.php');
		$a    = strpos($code, 'function redirectBackendCallbackFromFrontend');

		$this->assertNotFalse($a);

		$body = substr($code, $a, 2500);

		$this->assertStringNotContainsString('getSession', $body);
		$this->assertStringContainsString("'admin-'", $body);
	}
}
