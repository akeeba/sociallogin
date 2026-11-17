<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Records the golden JWK → PEM fixtures used by JWKConverterTest.
 *
 * DO NOT re-run this to make a failing test pass. The fixtures are a snapshot of what the shipped converter produced
 * when they were recorded (see the `recordedWith` key); their whole point is to catch a change in that output when
 * the underlying libraries change. Re-record only after a reviewed, intentional change of output.
 *
 * Inputs:
 * - apple-jwks.json: a snapshot of https://appleid.apple.com/auth/keys (public keys).
 * - Fresh RSA keys made with the `openssl` CLI, covering several modulus sizes, an odd modulus size (whose DER
 *   encoding needs no leading zero byte) and public exponent 3. Only the public parts are kept.
 *
 * Usage, from the repository root, after `composer install`:
 *   php tests/unit/Fixtures/generate-jwk-golden.php
 */

declare(strict_types=1);

use Akeeba\Plugin\System\SocialLogin\Dependencies\CoderCat\JWKToPEM\JWKConverter;

require __DIR__ . '/../bootstrap.php';

$fixturesDir = __DIR__;
$repoRoot    = dirname(__DIR__, 3);

$b64url = static fn(string $bin): string => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

$cases = [];

foreach (json_decode(file_get_contents($fixturesDir . '/apple-jwks.json'), true, 512, JSON_THROW_ON_ERROR)['keys'] as $jwk)
{
	$cases[] = ['name' => 'apple-' . $jwk['kid'], 'jwk' => $jwk];
}

$generated = [
	['bits' => 2048, 'e3' => false],
	['bits' => 3072, 'e3' => false],
	['bits' => 4096, 'e3' => false],
	['bits' => 2047, 'e3' => false],
	['bits' => 2048, 'e3' => true],
];

foreach ($generated as $spec)
{
	$command = sprintf('openssl genrsa %s %d 2>/dev/null', $spec['e3'] ? '-3' : '', $spec['bits']);
	$private = shell_exec($command);
	$key     = openssl_pkey_get_private((string) $private);

	if ($key === false)
	{
		fwrite(STDERR, "Could not generate a key with: $command\n");
		exit(1);
	}

	$rsa  = openssl_pkey_get_details($key)['rsa'];
	$name = sprintf('generated-%d-e%s', $spec['bits'], $spec['e3'] ? '3' : '65537');

	$cases[] = [
		'name' => $name,
		'jwk'  => [
			'kty' => 'RSA',
			'kid' => $name,
			'use' => 'sig',
			'alg' => 'RS256',
			'n'   => $b64url($rsa['n']),
			'e'   => $b64url($rsa['e']),
		],
	];
}

$converter = new JWKConverter();

foreach ($cases as &$case)
{
	$case['pem'] = $converter->toPEM($case['jwk']);
}

unset($case);

// Record which library versions produced the golden output.
$installed = json_decode(file_get_contents($repoRoot . '/vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);
$versions  = [];

foreach ($installed['packages'] ?? $installed as $package)
{
	if (in_array($package['name'], ['codercat/jwk-to-pem', 'phpseclib/phpseclib', 'humbug/php-scoper'], true))
	{
		$versions[$package['name']] = $package['version'];
	}
}

ksort($versions);

file_put_contents(
	$fixturesDir . '/jwk-golden.json',
	json_encode(
		['recordedWith' => $versions, 'cases' => $cases],
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
	) . "\n"
);

echo sprintf("Recorded %d cases with %s\n", count($cases), json_encode($versions, JSON_UNESCAPED_SLASHES));
