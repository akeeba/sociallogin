<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * The third-party packages bundled, namespace-scoped, in plugins/system/sociallogin/src/Dependencies.
 *
 * Shared by scoper.inc.php (what to scope) and build/scope-dependencies.php (where the scoped files go).
 *
 * Each entry maps a Composer package to:
 * - source:    its PSR-4 source directory, relative to the package root;
 * - target:    the directory under Dependencies/ matching its PSR-4 namespace root (so that the system plugin's own
 *              PSR-4 namespace, Akeeba\Plugin\System\SocialLogin → src/, autoloads it with no extra autoloader);
 * - exclude:   paths, relative to `source`, which are never scoped nor shipped. Only used to drop the parts of
 *              phpseclib we do not use. See .claude/memory/bundled-dependencies.md before changing it.
 */

return [
	'lcobucci/jwt'                     => [
		'source' => 'src',
		'target' => 'Lcobucci/JWT',
	],
	'lcobucci/clock'                   => [
		'source' => 'src',
		'target' => 'Lcobucci/Clock',
	],
	'codercat/jwk-to-pem'              => [
		'source' => 'src',
		'target' => 'CoderCat/JWKToPEM',
	],
	'phpseclib/phpseclib'              => [
		'source'  => 'phpseclib',
		'target'  => 'phpseclib3',
		// Only RSA public key loading and PEM export is used, through PublicKeyLoader. That tries EC, then RSA, then
		// DSA, then X.509, so those, ASN.1 and every BigInteger engine must stay. The SSH/SFTP clients, the SSH agent,
		// the terminal emulator, Diffie-Hellman and the symmetric ciphers (used only to decrypt password-protected
		// private keys) are never reached.
		'exclude' => [
			'#^Net/#',
			'#^System/#',
			'#^bootstrap\.php$#',
			'#^File/ANSI\.php$#',
			'#^Crypt/DH(\.php$|/)#',
			'#^Crypt/(AES|Blowfish|ChaCha20|DES|RC2|RC4|Rijndael|Salsa20|TripleDES|Twofish)\.php$#',
			'#^Crypt/Common/(BlockCipher|StreamCipher|SymmetricKey)\.php$#',
		],
	],
	'paragonie/constant_time_encoding' => [
		'source' => 'src',
		'target' => 'ParagonIE/ConstantTime',
	],
];
