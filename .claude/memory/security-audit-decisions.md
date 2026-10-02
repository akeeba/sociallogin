# Security audit decisions

Standing rulings from the 2026-10 full security audit (12 `audit-*` skills). The findings themselves and
their fixes are in Git history and the CHANGELOG; this file keeps only the rulings that stop future audits
from re-raising the same things.

## Threat model

- **Values set by trusted administrators are not attacker input.** Plugin parameters (Synology well-known
  URL, Auth0 `domain`, colour params, Microsoft tenant aside), field XML attributes and anything else only
  a back-end administrator can edit are out of scope: a malicious admin can change the code anyway.
  Intranet deployments may legitimately run over plain HTTP, so do not force `https` on admin-supplied URLs.
- **Tampering with the server-side session mid-login** (stored return URLs, `Host` header poisoning of
  stored URLs) presumes the server is already compromised. Not a finding.
- **Redacting core's own messages is Joomla's job.** Messages we forward unchanged from com_users
  (e.g. registration exceptions) are not ours to scrub.
- **Site Debug on = the owner wants the detail.** Verbose errors, stack traces and the debug echo of
  provider errors under `JDEBUG` are intentional. Outside debug mode, user-facing errors must be generic.
- **Tokens at rest stay plaintext** in `#__user_profiles`. They are limited-scope (name and email) and
  the same data already sits unencrypted in the database. They are also needed in full to verify an
  earlier account link.

**Why:** the operator triaged 20 of 45 findings as invalid on these grounds; re-reporting them wastes
a triage round.

**How to apply:** when running or triaging an audit, drop findings that only an administrator or an
already-compromised server can trigger, and findings about debug-only output.

## Deliberate behaviour (do not "fix")

- Providers hard-code `verified = true` only where the provider's API offers no public way to check
  email verification, or needs extra entitlements. GitHub emails are verified by the provider.
- `Features/Ajax.php` exposes the guest-reachable back-end com_ajax bridge on purpose. If an admin-only
  AJAX method is ever added to a provider plugin, gate the bridge on an explicit allow-list first.
- The `authenticate` action stays reachable by GET: core-rendered login buttons navigate by URL, and the
  callback is protected by the OAuth `state` parameter. `unlink` and `dontremind` are POST + token.
- `adminkey` is forwarded in the redirect URL, not validated here. Admin Tools enforces it against the
  request URI, so moving it into the session would silently break that integration. Only a coordinated
  change in Admin Tools can fix the URL transport.
- Package installer `allowDowngrades = true`, the empty installer delete lists, source maps in the
  system plugin and unguarded `src/Dependencies` (Mozart output) are all intentional.
- Social-link dedupe (moving an existing link to the current user) and the distinct login-error messages
  are intentional.
- The "remember me" option in the system plugin defaults to **Yes** to preserve old behaviour. No is the
  safer default; revisit it for a major release.

## Build constraint

`coenjacobs/mozart` must stay in `require` in `composer.json`, not `require-dev`. The unconditional
`post-install-cmd` runs `vendor/bin/mozart compose`, which exits 255 on `composer install --no-dev`.
Moving it needs the composer scripts reworked first.
