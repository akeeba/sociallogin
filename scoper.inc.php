<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

/*
 * PHP-Scoper configuration for the third-party libraries bundled with the system plugin.
 *
 * Do not run php-scoper directly; run `composer scope-dependencies`, which also moves the scoped files into
 * plugins/system/sociallogin/src/Dependencies. The scoped output is committed; nothing regenerates it at build time.
 * The package list lives in build/scoped-packages.php.
 */

const AKSOCIALLOGIN_SCOPER_PREFIX = 'Akeeba\\Plugin\\System\\SocialLogin\\Dependencies';

$finders = [];

foreach (require __DIR__ . '/build/scoped-packages.php' as $package => $info)
{
	$finder = Finder::create()
		->files()
		->ignoreVCS(true)
		->name('*.php')
		->in(__DIR__ . '/vendor/' . $package . '/' . $info['source']);

	foreach ($info['exclude'] ?? [] as $exclude)
	{
		$finder->notPath($exclude);
	}

	$finders[] = $finder;
}

return [
	'prefix' => AKSOCIALLOGIN_SCOPER_PREFIX,

	'finders' => $finders,

	'patchers' => [
		/*
		 * phpseclib 3 builds some of its own class names by string concatenation at runtime: the BigInteger engines
		 * (Math/BigInteger.php, Math/BigInteger/Engines/Engine.php), the key format plugins
		 * (Crypt/Common/AsymmetricKey.php), the elliptic curves, and the inline code of the symmetric ciphers.
		 * PHP-Scoper prefixes string literals that are whole class names, but cannot see that a fragment such as
		 * 'phpseclib3\Math\BigInteger\Engines\\' is the start of one. Left alone, BigInteger finds no engine and
		 * every JWK → PEM conversion throws, i.e. Sign in with Apple stops working.
		 *
		 * Prefix every remaining reference to the phpseclib3 namespace which is not already prefixed: a
		 * "phpseclib3\" not preceded by a word character or a backslash, keeping any leading backslashes.
		 */
		static function (string $filePath, string $prefix, string $contents): string {
			if (!str_contains($filePath, '/vendor/phpseclib/phpseclib/'))
			{
				return $contents;
			}

			return preg_replace(
				'/(?<![\w\\\\])(\\\\{0,2})phpseclib3\\\\/',
				'$1' . addcslashes($prefix, '\\') . '\\\\phpseclib3\\\\',
				$contents
			);
		},
	],

	'tag-declarations-as-internal' => false,

	/*
	 * Global symbols PHP-Scoper would wrongly prefix. php-scoper 0.18.7 (the newest release installable on our PHP 8.1
	 * platform) tells PHP's own symbols apart using 2022 PhpStorm stubs, so it does not know about newer ones:
	 * - Override: PHP 8.3's #[\Override] attribute, used by paragonie/constant_time_encoding.
	 * - OPENSSL_*: PHP 8.4+ constants phpseclib probes with defined() to pick OpenSSL fast paths.
	 * - FILE_X509_IGNORE_TYPE, PHPSECLIB_ALLOW_JIT: global switches a site may define to configure phpseclib.
	 * ScopedDependenciesTest::testNothingElseIsPrefixed catches any new one.
	 */
	'exclude-namespaces' => [],
	'exclude-classes'    => ['Override'],
	'exclude-functions'  => [],
	'exclude-constants'  => [
		'OPENSSL_KEYTYPE_ED25519',
		'OPENSSL_KEYTYPE_ED448',
		'OPENSSL_KEYTYPE_X25519',
		'OPENSSL_KEYTYPE_X448',
		'OPENSSL_PKCS1_PSS_PADDING',
		'FILE_X509_IGNORE_TYPE',
		'PHPSECLIB_ALLOW_JIT',
	],

	// Nothing needs to stay unprefixed: the bundled libraries are only used by our own code.
	'expose-global-constants' => false,
	'expose-global-classes'   => false,
	'expose-global-functions' => false,
	'expose-namespaces'       => [],
	'expose-classes'          => [],
	'expose-functions'        => [],
	'expose-constants'        => [],
];
