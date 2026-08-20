# Security

What this application does to protect itself, and — just as important —
what it deliberately leaves to you as the operator. Written so you can make
an informed decision about whether it's enough for what you're putting on
it, not to claim it's bulletproof.

## What the app handles

- **Mandatory TOTP two-factor for every admin.** There is no way to create
  or use an admin account without enrolling an authenticator app — it isn't
  optional, and it isn't something you can turn off from the admin panel.
  The first ("protected") admin created by the installer additionally can
  never be deleted, disabled, or stripped of MFA by another admin, so a site
  can't be locked into having zero working administrators.
- **TOTP secrets encrypted at rest.** Each admin's MFA secret is encrypted
  in the database using `APP_KEY`, generated fresh per install
  (`random_bytes(32)`, base64-encoded) — a database backup or export alone
  doesn't hand over working MFA secrets.
- **Recovery codes, hashed, shown once.** Single-use backup codes are
  generated at enrollment, stored as hashes (not the codes themselves), and
  displayed to the admin exactly one time. There is intentionally no
  self-service way to view or regenerate them afterward — see
  [`TROUBLESHOOTING.md`](TROUBLESHOOTING.md) for what to do if you lose
  them.
- **Login throttling.** Failed logins are rate-limited per-username (5 in 15
  minutes) and per-IP (10 in 15 minutes), independent of each other, so
  neither a distributed guessing attempt nor repeated guesses against one
  known username can brute-force past it unnoticed.
- **CSRF protection** on every state-changing admin request, and a
  **nonce-based Content-Security-Policy** sent on every response — script
  execution is restricted to same-origin plus the per-request nonce, so a
  reflected or stored injection can't just add an inline `<script>` tag and
  run. (`style-src` still allows inline styles, used for a handful of
  computed values like bar-chart heights — a narrower gap than an open
  `script-src`, and documented in `app/Support/Csp.php` if you want to
  tighten it further.)
- **Self-hosted proof-of-work CAPTCHA on the public contact form** (Altcha-
  style challenge/response, signed with `APP_KEY`), active only once the
  site has taken 5+ submissions in a day. No third-party script, no CDN, no
  tracking — the tradeoff is that it's a speed bump against casual scripted
  spam, not a hard wall against a targeted or well-resourced bot.
- **Security headers** sent on every response (via `.htaccess`, so they
  apply regardless of what's rendering the page):
  `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`,
  `Referrer-Policy: strict-origin-when-cross-origin`,
  `Strict-Transport-Security` (1 year, includes subdomains), and a
  restrictive `Permissions-Policy` (camera/mic/geolocation/USB/payment all
  denied, `interest-cohort=()` to opt out of FLoC-style tracking).
- **Application internals never reachable over HTTP.** `app/`, `config/`,
  `database/`, `resources/`, `storage/`, `tools/`, `vendor/` are denied at
  both the `.htaccess` level and, in the bundled Docker image, again at the
  Apache virtual-host level — belt and suspenders, so a misconfigured
  `AllowOverride` alone doesn't expose them.
- **Uploaded files are never served directly.** Everything under
  `storage/uploads/` is only reachable through the `/media/{filename}`
  route, not as static files off the docroot.
- **The installer locks itself.** `public/install.php` refuses to run again
  once an admin account exists — checked against the database itself, not
  just a lock file, so deleting `storage/installed.lock` doesn't reopen it.
  Before that point, it's gated by a token you have to read off the
  filesystem (or the container logs, under Docker) — proof you're the
  person who actually put the files there, the same model Jenkins uses for
  its initial admin password.

## What you own as the operator

- **TLS/HTTPS.** The app assumes a certificate is already in place and
  redirects HTTP to HTTPS (`.htaccess`, honoring both a direct HTTPS
  connection and an `X-Forwarded-Proto` header from a reverse proxy). It
  does not obtain, renew, or manage certificates itself — that's your host's
  job (nearly all shared hosts issue free Let's Encrypt certificates from
  the control panel) or, on Docker, a reverse proxy/load balancer's you put
  in front of it. The bundled Docker image has no TLS of its own by design —
  it's meant to sit behind something that does.
- **Database credentials and access.** The app connects with whatever
  username/password you gave the installer. Scope that database user to
  only the one database it needs, the same as you would for any application
  — the installer doesn't do this for you because most shared-hosting
  panels create the user pre-scoped to a single database by default.
- **Server-level patching.** PHP version updates, MySQL updates, OS
  packages — all outside the app's control. On shared hosting, this is
  mostly your host's job; on the Docker path, it's `apt`/image updates you
  pull yourself.
- **Backups.** Neither install path backs up your database automatically.
  See [`UPGRADING.md`](../UPGRADING.md) for the manual export step to run
  before every upgrade — the same export is your disaster-recovery backup
  if you run it on a schedule, not just before upgrades.
- **`APP_KEY` custody.** It's written once, into `.env`, and never displayed
  again. Losing it doesn't lose data, but it does mean re-enrolling every
  admin's MFA from scratch (the encrypted secrets it protected become
  unreadable). Back up `.env` with the same care as the database — see
  [`CONFIGURATION.md`](CONFIGURATION.md).
- **Who else has server access.** MFA and CSRF protect the app from remote
  attackers; they do nothing against someone who already has filesystem or
  database access on the box itself (a compromised host account, a
  malicious co-tenant on badly-isolated shared hosting, etc.). That threat
  model is the hosting provider's responsibility, not this application's.

## Reporting a vulnerability

This is a small, unfunded open-source project with no dedicated security
contact yet — open a GitHub issue, or (for anything you'd rather not post
publicly first) reach the maintainer directly before disclosing details
widely. There is no bug bounty.
