# Configuration reference

Almost everything about a running site — site title, colors, projects,
skills, posts, profile copy — is edited from the admin panel, not from a
config file. This document covers the small set of settings that live
outside the database: the `.env` file the installer writes, and the two
supported ways of arranging the files on disk.

If you're looking for how to change the site title or theme colors, that's
**Admin → Settings** / **Admin → Theme**, not this file.

## The `.env` file

Written once by the installer (`public/install.php`, final step) and read on
every request by `config/config.php`. It is a flat `KEY=value` file, one
setting per line, `#`-prefixed lines ignored.

| Key | Set by | Meaning |
|---|---|---|
| `APP_ENV` | installer, fixed to `production` | Present for completeness; nothing in the app currently branches on it. |
| `APP_DEBUG` | installer, fixed to `false` | Governs PHP's `display_errors`/`error_reporting` (see `public/index.php`). `false` means a fatal error shows a blank page instead of leaking a stack trace with server paths to visitors — see [`TROUBLESHOOTING.md`](TROUBLESHOOTING.md) for how to debug one. Only ever set this to `true` temporarily, on a site nobody else can see, and set it back. |
| `APP_URL` | installer, from the request `Host` header | Used to build absolute URLs (sitemap, canonical links). Falls back to the requesting host if missing, so a fresh install still works before this is set. |
| `DB_HOST` | you, at the installer's Database step | `127.0.0.1` on shared hosting (almost always — check your host's database docs if unsure); `db` under Docker Compose. |
| `DB_PORT` | you, at the installer's Database step | `3306` in both the shared-hosting and Docker cases — this is MySQL's own port, unrelated to `WEB_PORT`/`DB_PORT` in Docker Compose's `.env` (see below), which are host-side port *mappings*, a different layer entirely. |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | you, at the installer's Database step | Whatever you created in your host's control panel, or Docker Compose's generated values. |
| `UPLOADS_PATH` | not set by the installer; defaults to `<approot>/storage/uploads` | Where uploaded images (headshot, project/post images) are written on disk, and read back from by the `/media/{filename}` route — never served directly by the web server, always through that route. Override only if your host requires writable storage somewhere other than the app tree. |
| `APP_KEY` | installer, `random_bytes(32)` at install time | Encrypts each admin's TOTP secret at rest, and signs the contact-form CAPTCHA challenge. **Never edit or regenerate this after installing** — every enrolled admin's MFA secret is encrypted with it; changing it locks every admin out of MFA at once (see `TROUBLESHOOTING.md`). |

## Environment variables (not in `.env`)

A few settings are read from the process environment rather than the file,
because they need to exist *before* `.env` can be located or read:

| Variable | Who sets it | Meaning |
|---|---|---|
| `PORTAL_ENV_FILE` | you, in your web server's config, if at all | Overrides where the app looks for its config file. Default: `<approot>/.env`. The Docker image sets this to `storage/app.env` — see [`INSTALL-DOCKER.md`](INSTALL-DOCKER.md) "Where things live." Set this yourself only if your host lets you store secrets somewhere outside the app tree entirely (e.g. a private directory above the docroot) and you want to use that. |
| `PORTAL_APP_ROOT` | you, only under the root-shim layout with a docroot your host won't let you point elsewhere | Tells `public/index.php` and `public/install.php` where the application root is, when it can't be inferred (see "Docroot layouts" below). |
| `PORTAL_SETUP_TOKEN` | you, optionally, before first install | Lets you set the installer's token yourself instead of reading the auto-generated one from `storage/install-token.txt` (or, under Docker, from `docker compose logs web`). Only read by the installer, and only until it locks itself. |
| `PORTAL_LOCAL_HTTP` | the bundled Docker image's Apache config only | Disables the force-HTTPS redirect for the no-TLS local container. Never set this on a real deployment — it would turn off the HTTPS redirect for real visitors. |

## Docroot layouts

The installer produces a working site under either arrangement; nothing
else needs to change based on which one you use.

**Single root (the default).** `public/` sits inside the app root, alongside
`app/`, `config/`, `vendor/`. Point your web server's document root at
`public/`. This is what the Docker image does, and what most VPS/self-managed
setups should do.

**Root-shim.** Some shared hosts fix the document root to your account's top
level (`public_html/`, or similar) and won't let you change it. Unzip the
release there anyway — the root `.htaccess` forwards every request into
`public/` and denies direct access to `app/`, `config/`, `database/`,
`resources/`, `storage/`, `tools/`, `vendor/` on the way past. If your host
*can* point the docroot at a subdirectory, prefer doing that (single root) —
the shim is a fallback for when it can't.

If neither `public/app-root.php` (created automatically by shared-hosting
installs that need it) nor a same-directory `config/config.php` (the
single-root signal) is found, and `PORTAL_APP_ROOT` isn't set, both
`index.php` and `install.php` fail loudly with a 500 rather than guessing.

## Session and storage

Not currently configurable without editing `config/config.php` directly —
listed here for reference, not as something you're expected to change:

- Admin session cookie: `portal_admin_sess`, 4-hour lifetime.
- Uploaded images live under `UPLOADS_PATH` (see above), served exclusively
  through `/media/{filename}`.
