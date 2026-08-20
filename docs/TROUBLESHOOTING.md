# Troubleshooting

## "500 Internal Server Error" on every page

Almost always one of the requirements the installer already checked, having
drifted after the fact — a host changing PHP versions, or a permissions
change. Check, in order:

1. **PHP version and extensions.** The app needs PHP 8.3+ with `pdo_mysql`,
   `gd`, `mbstring`, `openssl`. If your host's control panel lets you switch
   PHP versions per-site, confirm it's still set to 8.3 or newer — hosts
   occasionally reset this on plan changes or PHP end-of-life migrations.
2. **`.env` readability.** If the app can't read its config file, it exits
   immediately with the exact text **"Configuration missing."** — a blank
   page with just that sentence, not a generic 500. This means either the
   file doesn't exist (was it uploaded? did an upgrade overwrite it — see
   [`UPGRADING.md`](../UPGRADING.md)'s warning about not re-uploading
   `.env`?), or it exists but isn't readable by the PHP process — usually a
   file-permissions mismatch between the account that uploaded it and the
   account PHP runs as. Your host's file manager can usually fix permissions
   directly; if not, re-download it, delete it, and re-upload.
3. **Turn on debug output, temporarily, on a site nobody else is looking at.**
   Edit `.env`, set `APP_DEBUG=true`, reload the failing page — PHP will now
   show the actual error and file/line instead of a blank page (see
   [`CONFIGURATION.md`](CONFIGURATION.md) for why this is off by default).
   **Set it back to `false` as soon as you've seen the error** — a public
   site should never run with debug output on; a stack trace can reveal
   server file paths.

## Blank white page, no error at all

This is what a genuine PHP fatal error looks like with `APP_DEBUG=false`
(the correct production setting) — PHP suppresses the error text instead of
printing it. Follow step 3 above: flip `APP_DEBUG` to `true` just long
enough to see what actually failed, then flip it back.

## Pages other than the homepage 404 ("Not Found" for `/admin`, `/articles`, etc.)

The homepage works because it's the literal `index.php` some servers will
serve by default; everything else depends on the rewrite rules in
`.htaccess` actually running. Check:

- **`mod_rewrite` is enabled** on your host (Apache-specific — this doesn't
  apply if you're on nginx with an equivalent rule already configured).
  Most shared hosts have this on by default; if yours doesn't expose a
  toggle, ask their support — it's a one-line answer for them.
- **`AllowOverride All`** is in effect for your docroot, so `.htaccess` is
  actually read. This is usually the host's default and not something you
  can change yourself on shared hosting; if it's off, that's a support
  ticket, not something fixable from the file manager.
- **The `.htaccess` file itself made it into the upload.** File managers and
  some FTP clients hide dotfiles by default — confirm it's actually present
  next to `index.php` in `public/` (or at the account root, if you're using
  the root-shim layout).

## "Requirements check" step fails in the installer

Each failed line names exactly what's missing and why it's needed (e.g.
"gd extension — image resizing on upload"). Missing PHP extensions are
usually a checkbox in your host's PHP configuration panel, not something you
install yourself. "Config location writable" or "Uploads folder writable"
failing means the PHP process doesn't have write access to the app
directory or `storage/uploads/` — check the ownership/permissions your
host's file manager assigns to freshly-extracted files.

## Database step: "Access denied" (error 1044 or 1045)

The installer's Database step surfaces MySQL's own error text, which
distinguishes two different mistakes:

- **1045 (Access denied for user ... using password)** — the username or
  password is wrong. Re-check what you typed against what your host's
  database panel shows.
- **1044 (Access denied for user ... to database ...)** — the username and
  password are *correct*, but that user was never actually granted
  permission on that specific database. Shared-hosting control panels almost
  always require a separate "Add User to Database" step after creating the
  user — the single most commonly missed step in the whole install. Go back
  to your host's database panel and confirm that link exists.

## "Too many attempts. Try again in a few minutes."

Login attempts are throttled: after 5 failed attempts against one username,
or 10 failed attempts from one IP address (whichever comes first) within a
15-minute rolling window, further attempts are blocked — regardless of
whether the next one would have been correct. This resets on its own; there
is no manual override. If you're testing the installer repeatedly and
tripping this yourself, wait out the window rather than retyping faster.

## Locked out of MFA — lost your phone/authenticator and your recovery codes

Recovery codes are shown exactly once, at enrollment, and there is no
self-service way to regenerate them from the admin panel — if another
administrator account still has access, they can reset your MFA enrollment
from **Admin → Users**, which clears your TOTP secret and forces you through
setup again on your next login. That's the intended path.

If you are the *only* administrator and have lost both your authenticator
and your recovery codes, the honest answer is that there is no in-app
recovery flow for that — you have to reach into the database directly, the
same way you'd fix any other single-admin lockout:

1. Open your database in phpMyAdmin (or an equivalent your host provides).
2. Find your row in the `admins` table.
3. Set `mfa_secret` to `NULL`, `mfa_enabled` to `0`, and `force_mfa_setup` to
   `1`.
4. Log in with your username and password as normal — you'll be walked
   through MFA enrollment again, exactly as on first install.

This is a deliberate design tradeoff, not an oversight: a self-service
"disable MFA" path reachable without database access would be a much larger
security hole than the inconvenience it removes. Note it down somewhere
before you need it.

## A post I featured isn't showing on the home page

Check these in order — the first three are far more common than a fault:

1. **Is the tile already full?** Each rotation shows the newest five posts only.
   The post list's filter bar says so directly when you are over the limit
   (*"showing 5 of 7 — the 2 oldest never appear"*). Unflag something older to
   make room.
2. **Is the post suppressed?** A suppressed post is hidden everywhere, rotations
   included. Un-suppress it and it comes straight back — you do not need to
   re-flag it.
3. **Is its publish date older than the five that are showing?** Rotations order
   by publish date, not by when you ticked the box. A post you flagged most
   recently can still be the one left out.
4. **Is the date in the future?** It will appear, but marked *Scheduled*.

If none of those apply, confirm the flag actually saved: filter the post list by
*on profile* or *on map* and look for the post. If it is not listed, the checkbox
did not save — the usual cause is JavaScript being blocked, since the checkboxes
save on click rather than with a Save button. Open the post itself and use the
**Home page** switches in the editor instead.

See [`CAROUSELS.md`](CAROUSELS.md) for the full rules.

## Contact form rejects a real submission with a CAPTCHA error

The proof-of-work CAPTCHA only activates once the site has received 5 or
more submissions in a day — a burst of real inquiries can trip the same
threshold as a bot. It self-resolves the next day; there's nothing to
configure or reset by hand.

## Checking the database connection directly

`GET /health/db` returns `{"status":"ok","db":1}` when the app can reach
MySQL, or a 500 with `{"status":"error"}` if it can't — useful for telling a
database problem apart from an application one without digging through logs.
`GET /health` (no `/db`) just confirms PHP itself is alive and reports the
running version, independent of the database entirely.
