# portal-lift

A self-hostable personal portal and portfolio CMS — a nine-tile public homepage with a
MFA-protected admin panel behind it.

> **Status: pre-release, under active development.** There is no installable release yet.
> Watch the Releases page.

## What it is

A small, deliberately boring PHP application. No framework, no Node build step, no
JavaScript toolchain. If your web host can run WordPress, it can run this.

- **Public portal** — nine configurable tiles: profile, projects, skills, posts, a
  location map, and a contact form
- **Admin CMS** — write posts, manage projects and skills, upload images, edit every
  piece of copy and both colour themes without touching a file
- **Security** — mandatory TOTP two-factor for admins, CSRF protection, a nonce-based
  Content-Security-Policy, login throttling, and a self-hosted proof-of-work CAPTCHA on
  the contact form (no third-party CAPTCHA service, no tracking)
- **Themes** — dark and light, resolved server-side so there is no flash of the wrong
  theme on load

## Requirements

- PHP 8.3 or newer with `pdo_mysql`, `gd`, `mbstring`, `openssl`
- MySQL 8 (or MariaDB 10.6+)
- Apache with `mod_rewrite`, or nginx with an equivalent rule

## Installing

Two paths, both planned for the first release:

1. **Shared hosting** — download the release zip, upload it through your host's file
   manager, create a database in your control panel, and open the installer in a browser.
   No terminal, no SSH, no Composer.
2. **Docker** — `cp .env.example .env && docker compose up -d`

Detailed guides land in `docs/` before the first tagged release.

## Licence

MIT — see [LICENSE](LICENSE).

Built by Nick Antonizick. The original instance it grew out of lives at antonizick.com.
