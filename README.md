# portal-lift

A self-hostable personal portal and portfolio CMS — a nine-tile public homepage with an
MFA-protected admin panel behind it.

> **Status: pre-release, under active development.** There is no installable release yet.
> Watch the Releases page.

> **Screenshots below are placeholders.** This README shows the real app before explaining
> it, but there are no reviewed screenshots yet. Raw captures go in RemoteBox at
> `portal.lift/docs-review/readme-hero-screenshots/` (see that folder's `README.md` for
> exact filenames and what each shot should show) — not directly into the repo. They get
> reviewed and copied into `docs/screenshots/readme/` from there, and this section gets
> updated to show them for real.

**Public homepage — dark theme, desktop width.**
`docs/screenshots/readme/hero-public-homepage.png`
*(placeholder — see note above)*

**Admin dashboard, logged in.**
`docs/screenshots/readme/hero-admin-dashboard.png`
*(placeholder — see note above)*

## What it is

A small, deliberately boring PHP application. No framework, no Node build step, no
JavaScript toolchain. If your web host can run WordPress, it can run this.

- **Public portal** — nine configurable tiles: profile, projects, skills, posts, a
  location map, and a contact form
- **Admin CMS** — write posts, manage projects and skills, upload images, edit every
  piece of copy and both colour themes without touching a file
- **Security** — mandatory TOTP two-factor for admins, CSRF protection, a nonce-based
  Content-Security-Policy, login throttling, and a self-hosted proof-of-work CAPTCHA on
  the contact form (no third-party CAPTCHA service, no tracking). Full picture:
  [`docs/SECURITY.md`](docs/SECURITY.md).
- **Themes** — dark and light, resolved server-side so there is no flash of the wrong
  theme on load

## Requirements

- PHP 8.3 or newer with `pdo_mysql`, `gd`, `mbstring`, `openssl`
- MySQL 8 (or MariaDB 10.6+)
- Apache with `mod_rewrite`, or nginx with an equivalent rule

## Installing

Two paths:

1. **Shared hosting** — download the release zip, upload it through your host's file
   manager, create a database in your control panel, and open the installer in a browser.
   No terminal, no SSH, no Composer. Full walkthrough:
   [`docs/INSTALL-SHARED-HOSTING.md`](docs/INSTALL-SHARED-HOSTING.md).
2. **Docker** — clone the repo, then `make up`. Full walkthrough:
   [`docs/INSTALL-DOCKER.md`](docs/INSTALL-DOCKER.md).

Already running an instance? See [`UPGRADING.md`](UPGRADING.md) for drop-in upgrades —
migrations apply themselves on your next admin login.

## More docs

- [`docs/CONFIGURATION.md`](docs/CONFIGURATION.md) — every `.env` setting and both
  supported docroot layouts
- [`docs/TROUBLESHOOTING.md`](docs/TROUBLESHOOTING.md) — 500s, blank pages, rewrite
  rules not firing, MFA lockouts
- [`docs/SECURITY.md`](docs/SECURITY.md) — what the app protects against, and what
  stays your responsibility as the operator

## Licence

MIT — see [LICENSE](LICENSE).

Built by Nick Antonizick. The original instance it grew out of lives at antonizick.com.
