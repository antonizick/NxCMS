# Security Policy

## Reporting a vulnerability

Please report security issues privately through GitHub's **Report a vulnerability**
button on the Security tab, rather than opening a public issue.

This is a personal project maintained on a best-effort basis. There is no SLA, but
genuine vulnerability reports will be taken seriously and credited unless you ask
otherwise.

## What this software does for you

- Admin passwords are hashed with PHP's `password_hash()` defaults
- Two-factor authentication (TOTP) is **mandatory** for every admin account
- TOTP secrets are encrypted at rest with a per-install `APP_KEY`
- Login attempts are throttled per IP and per username
- All state-changing forms are CSRF-protected
- A nonce-based Content-Security-Policy is sent on every response
- Uploaded files are stored outside the web root and served through a controlled route
- The contact form uses a self-hosted proof-of-work CAPTCHA — no third-party service,
  no visitor tracking

## What it does not do — your responsibility as the operator

- **TLS.** This software does not obtain or renew certificates. Serve it over HTTPS.
- **Backups.** Nothing here backs up your database or uploads.
- **Host security.** Keep PHP and MySQL patched.
- **Your `APP_KEY`.** It is generated once at install and encrypts your TOTP secrets.
  If you lose it, admins must re-enrol their second factor. Do not commit it anywhere.
- **Your recovery codes.** The installer shows them exactly once. Without them, and
  without your authenticator, the only way back into an account is editing the database
  by hand.

## Supported versions

Only the latest tagged release receives fixes.
