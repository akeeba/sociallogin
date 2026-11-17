# Bundled third-party dependencies

## They are namespace-scoped with PHP-Scoper, not Mozart

lcobucci/jwt, lcobucci/clock, codercat/jwk-to-pem, phpseclib/phpseclib 3 and
paragonie/constant_time_encoding are bundled under `Akeeba\Plugin\System\SocialLogin\Dependencies` in
`plugins/system/sociallogin/src/Dependencies/`. `composer scope-dependencies` regenerates it with
`humbug/php-scoper` (`scoper.inc.php`, package list in `build/scoped-packages.php`). The output is
committed, and nothing regenerates it at build time. php-scoper and the unscoped packages are
`require-dev` only.

**Why:** Mozart 1.x rewrites code on an AST but ignores string literals. It left phpseclib 3's
string-built class names unprefixed and mangled the `class_exists('…')` ones (doubled backslash), so
BigInteger found no engine and every Apple login failed. Mozart 0.7 handled strings, but Composer now
refuses to install it because its league/flysystem 1.x dependency has open advisories. Mozart 1.1.6 also
loops forever on the flysystem ↔ flysystem-local require cycle unless its `packages` list is set.

**How to apply:**

1. After `composer update` changes a bundled package, run `composer scope-dependencies`, then
   `composer test`, and commit the regenerated folder.
2. **Dynamic class names:** php-scoper prefixes strings that are whole class names, but not fragments
   that are concatenated at runtime (`'phpseclib3\Math\BigInteger\Engines\\' . $engine`). The
   phpseclib patcher in `scoper.inc.php` prefixes every remaining `phpseclib3\` reference.
   `ScopedDependenciesTest` fails on any unprefixed reference to a bundled namespace outside comments.
   If a new library is added, add it to that test's list and give it a patcher if the test finds any.
3. **Over-prefixing:** php-scoper 0.18.7 is the newest release that installs on our PHP 8.1 platform.
   It tells PHP's own symbols apart using 2022 stubs, so it prefixes newer built-ins: `#[\Override]`,
   and PHP 8.4+ `OPENSSL_*` constants probed with `defined('…')`, which then always returns false.
   Those go in the `exclude-*` lists of `scoper.inc.php`.
   `ScopedDependenciesTest::testNothingElseIsPrefixed` fails on any new one.
4. **Pruning** is done by the phpseclib `exclude` list in `build/scoped-packages.php`, not at build
   time. `PublicKeyLoader::load()` tries EC, then RSA, then DSA, then X.509. A missing class there is a
   fatal `Error`, not the caught `NoKeyLoadedException`, so EC, DSA, X509 and ASN1 must stay, as must
   every BigInteger engine (servers differ: GMP, BCMath, PHP64, PHP32). `ScopedDependenciesTest` loads
   every shipped class to catch a dangling parent, interface or trait.
5. **Golden tests:** `JWKConverterTest` compares JWK → PEM output against
   `tests/unit/Fixtures/jwk-golden.json`, recorded with phpseclib 2.0.55 before the move to phpseclib 3.
   Never re-record it just to make a test pass. A PEM text change after a library update needs review.
   The key-equivalence and RS256 sign/verify tests must never fail.
6. **Removed or renamed folders:** Joomla does not delete files that a plugin update no longer ships.
   When a folder under `Dependencies/` goes away, add it to `$removeFiles['folders']` in
   `plugins/system/sociallogin/script.plg_system_sociallogin.php`, as was done for `phpseclib` and
   `bcmath_compat` in 4.11.1.
7. No `defined('_JEXEC')` guard in the scoped files. Unguarded `src/Dependencies` is an accepted audit
   ruling; see [security-audit-decisions.md](security-audit-decisions.md).
