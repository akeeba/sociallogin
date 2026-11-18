# Server firewalls on hosted test sites

## A `.profile` request parameter gets a hidden 403

On the hosted test site (here called `www.example.com`; cPanel, Apache, server-level mod_security), any request with
`.profile` in a parameter value is rejected with a 403 before PHP runs. Google's OAuth callback always carries
`scope=… https://www.googleapis.com/auth/userinfo.profile …`, so every Google callback trips it. The rule is most
likely the OWASP CRS OS-file-access check (its list of OS files includes `.profile`); the server config is not
readable from the hosting account, so this is unconfirmed.

The 403 is invisible: `/403.shtml` does not exist, Joomla's `.htaccess` rewrites it to `index.php`, and PHP runs as the
error document with an **empty** `QUERY_STRING`, `$_GET` and `$_REQUEST`, but the original `REQUEST_URI`
(`$_SERVER['REDIRECT_STATUS']` is `403`, `REDIRECT_URL` is `/403.shtml`). Joomla's site router rebuilds the input
from the request URI, so frontend flows look fine; anything running before routing (`onAfterInitialise`) sees no
request variables. The backend application never re-parses the URI, so there the request variables stay empty.

**Why:** backend linking with Google failed with the generic "social network login failed" message on the frontend.
The frontend-to-backend callback forward ran in `onAfterInitialise`, saw no `state`, and did not forward. A
`?dummy=.profile` test looked unblocked only because the error document rendered the home page.

**How to apply:**

- When a social login fails only on a hosted site, check the access log status and `REDIRECT_STATUS` before
  suspecting the code. Logging `$_SERVER` from a line at the top of `index.php` (back it up, restore it, compare md5)
  shows it; a standalone `.php` file is blocked by the Admin Tools `.htaccess`.
- Never rely on `$input` alone in code that runs before routing; fall back to `Uri::getInstance()->getQuery(true)`.
  Don't forward `scope` to the backend. See `plugins/system/sociallogin/src/Library/Helper/BackendCallback.php`.

## IP blocks: cause unconfirmed, so be careful

Our IP has been blocked after a series of server-level 403s, but the mechanism is unconfirmed. In one instance SSH
(port 22) timed out while HTTPS from the same IP kept working, which doesn't fit a simple web-firewall ban. Lifting a
block takes a support request to the host, which is slow. Admin Tools' own blocks are logged in its Blocked Requests
Log and are separate.

**Why:** SSH access was lost while debugging and only came back after the router got a new IP.

**How to apply:**

- Prefer read-only SSH (logs, file checksums) over HTTP probes.
- Probe over HTTP only with request shapes already known to pass, spaced out, and only a handful at a time.
- A 303 or 200 response does not prove the server accepted the request; see above.
- If a probe returns a 403 or 500, disable Admin Tools and retry once. If it still happens, it's the server's
  mod_security; tell the operator rather than probing further.
