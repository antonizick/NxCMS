# Installing on shared hosting

This guide assumes you've never opened a terminal, never used SSH, and don't have
access to one on your hosting account. Everything here happens through your web
browser: your host's control panel, and the installer built into portal.lift itself.

If your host gives you SSH and you're comfortable with it, you can move faster than
this guide — but nothing here requires it.

> **Screenshot status:** this guide is a working draft. Every host's control panel
> looks a little different (cPanel, DirectAdmin, Plesk, and custom panels like
> ICDSoft's all label things slightly differently), so the placeholders below mark
> exactly what to capture from *your* actual hosting account. Once we have real
> screenshots, they replace the placeholder blocks — the surrounding instructions
> stay generic enough to still be true for readers on a different host.
>
> Drop raw screenshots in RemoteBox at `portal.lift/docs-review/shared-hosting-screenshots/`
> (see that folder's `README.md` for naming and redaction notes) — not directly
> into the repo. They get reviewed, cropped/redacted, and copied into
> `docs/screenshots/shared-hosting/` from there.

---

## What you'll need before you start

- A hosting account with: PHP 8.3 or newer, MySQL 8 (or MariaDB 10.6+), and the
  ability to create a MySQL database yourself through a control panel. Nearly all
  shared hosting qualifies — this is the same requirement WordPress has.
- Your host's control panel login (cPanel, DirectAdmin, Plesk, or similar).
- About 15 minutes.

If you're not sure what PHP version your host runs, most control panels have a
"PHP Selector" or "MultiPHP Manager" tool that shows and lets you change it. Set it
to 8.3 or higher before continuing.

---

## Step 1 — Create a database

Every shared host has a database creation tool, usually under a section called
**"Databases"** or **"MySQL Databases."** You need three things out of it:

1. A new, empty database.
2. A database user (a username + password, separate from your hosting login).
3. That user added to that database, with full privileges.

Most control panels split this into two or three separate steps — creating the
database, creating the user, and then a separate "Add User to Database" action that
attaches the two together with a privileges checklist. **Don't skip that last
step** — it's the single most common thing people miss, and it produces a
confusing error later ("this user cannot open this database") rather than an
obvious one.

Many hosts automatically prefix the database name and username with your account
name (e.g. `yourusername_portal` instead of just `portal`). That's normal — just
write down the *full* prefixed name and username exactly as the panel shows them.
You'll need:

- Database host (almost always `localhost` or `127.0.0.1`)
- Database port (almost always `3306`)
- Database name (with prefix, if your host adds one)
- Database username (with prefix, if your host adds one)
- Database password (you set this — use your password manager to generate one)

> 📸 **Screenshot needed — `02-mysql-create-database.png`**
> The "Create New Database" screen in your host's control panel, with a database
> name typed in (before you click create). Crop out anything showing your account
> username or billing details if you'd rather not show them — the *layout* of the
> tool is what matters here, not your specific account.

> 📸 **Screenshot needed — `03-mysql-create-user.png`**
> The "Add New User" (or "MySQL Users") screen, with a username typed in. **Do not
> capture the password field with a real password visible** — type a throwaway
> value or blur it before sending it to me.

> 📸 **Screenshot needed — `04-mysql-add-user-to-database.png`**
> The "Add User to Database" screen: the dropdowns for user and database, and
> ideally the privileges checklist (ALL PRIVILEGES, or the individual checkboxes)
> before you submit it. This is the step people miss — showing it clearly is the
> point.

---

## Step 2 — Download the release

Go to the [Releases page](../../../releases) and download the newest
`portal-lift-<version>.zip`. Save it somewhere you can find it — you'll upload this
exact file in the next step. Don't unzip it on your computer; upload it zipped.

> 📸 **Screenshot needed — `01-download-release.png`**
> Optional, low priority — a screenshot of the GitHub Releases page with the zip
> asset visible. This one isn't host-specific, so it's the easiest to skip if
> you're short on time.

---

## Step 3 — Upload and extract

1. In your host's control panel, open **File Manager**.
2. Navigate to `public_html` (or whichever folder serves your website — some hosts
   call it `www` or `htdocs`).
3. Upload the zip file you downloaded in Step 2.
4. Once it's uploaded, most File Managers let you right-click it and choose
   **Extract** — this unzips it in place without needing a terminal. Extract it
   directly into `public_html`.
5. After extracting, you should see folders like `app`, `config`, `public`,
   `storage`, `vendor`, and files like `.htaccess`, `README.md`, `VERSION` sitting
   directly inside `public_html` — not inside an extra subfolder. If your File
   Manager created a subfolder (some do), move everything up one level and delete
   the now-empty subfolder.
6. You can delete the zip file itself once extraction succeeds.

This "everything in `public_html`" layout is the one this guide uses throughout —
it works on every shared host, no matter what. There's a slightly cleaner
alternative in the box below, but it's optional.

> **If your host lets you set a custom document root** (look for "Document Root",
> "Addon Domains", or "Domains" in your control panel): you can instead point your
> domain's document root at the `public` folder inside what you extracted, rather
> than `public_html` itself. This hides the application's internal folders from
> the web entirely, instead of relying on the included `.htaccess` file to block
> them. It's a nice-to-have, not a requirement — skip it if you don't see an
> obvious way to do it.
>
> 📸 **Screenshot needed — `09-document-root-setting.png`** *(optional)*
> If your host exposes this option, a screenshot of that settings screen.

> 📸 **Screenshot needed — `06-file-manager-upload.png`**
> The File Manager upload dialog with `portal-lift-<version>.zip` selected,
> inside `public_html`.

> 📸 **Screenshot needed — `07-file-manager-extract.png`**
> The right-click menu (or equivalent) showing the **Extract** option, or the
> confirmation screen after extracting.

> 📸 **Screenshot needed — `08-file-manager-layout.png`**
> `public_html` after extraction, showing the flat layout described in step 5
> (`app`, `config`, `public`, `storage`, `vendor`, `.htaccess`, etc. all visible
> at the top level, no extra wrapper folder).

---

## Step 4 — Open the installer

Visit `https://yourdomain.com/install.php` in your browser.

The first screen asks for a **setup token** — a random code the installer writes
to a file the moment it first runs, as proof that whoever is completing setup is
also the person who uploaded the files (not a stranger who found your site before
you finished installing it).

To find it:

1. In File Manager, navigate to `public_html/storage/` (or, if you used the
   custom-document-root option above, the `storage` folder one level up from
   `public`).
2. Open `install-token.txt` — most File Managers have a built-in code/text viewer
   ("Edit" or "View") so you don't need to download it.
3. Copy the single line inside it, paste it into the installer's setup token
   field, and continue.

> 📸 **Screenshot needed — `10-install-token-screen.png`**
> The installer's first screen in your browser (the "Setup token" page). No
> sensitive data on this one — it's just the app's own UI.

> 📸 **Screenshot needed — `11-file-manager-open-token.png`**
> File Manager's viewer open on `storage/install-token.txt`. **Blur or crop the
> actual token value** before sending it — anyone with that token before you
> finish setup could complete the install as you.

---

## Step 5 — Server check

The installer checks that your host actually has what portal.lift needs: the
right PHP version, the `pdo_mysql`, `gd`, `mbstring`, and `openssl` extensions, and
that the config location and uploads folder are writable. Everything should show a
green checkmark.

If something shows a red ✕, it's a **required** item — the installer won't let you
continue until it's fixed. This is almost always a missing PHP extension, which
your host can enable for you (contact support, or look for an extension manager in
your control panel — cPanel calls this "Select PHP Version" with a checkbox list).
A yellow **!** is advisory (missing WebP or PNG support in the image library, for
example) — the site works either way, just with a little less than full
functionality.

> 📸 **Screenshot needed — `12-requirements-check.png`**
> The requirements screen with everything green. If you happen to hit a red ✕
> first, a screenshot of that is also useful — it helps make the troubleshooting
> guide accurate.

---

## Step 6 — Database connection

Enter the four values you wrote down in Step 1: host, port, database name, and
username, plus the password you chose. The installer tests the connection before
letting you continue, and — this matters — **it deliberately tells you different
things** depending on what's wrong:

- Wrong database name → it says the name is wrong, or that the user hasn't been
  added to that database (this is that "Add User to Database" step from Step 1
  again — the most common miss).
- Wrong username or password → it says the server rejected the credentials.
- Can't reach the host/port at all → it says it couldn't connect.

If you see the "user cannot open this database" message, go back to your control
panel's database tool and confirm the user is actually attached to the database
with privileges — that's what it means.

> 📸 **Screenshot needed — `13-database-step.png`**
> The database connection form filled in (password field can show dots/asterisks —
> that's fine, it's not the real characters).

---

## Step 7 — Site and administrator account

This step creates your site's identity and your first admin login in one form:

- **Site title** — shown in the browser tab and inside your authenticator app,
  so pick something you'll recognize on your phone later.
- **Initials** — 1 to 8 letters or numbers. These become your site's generated
  icon (favicon) and monogram, so something like your initials or a short
  abbreviation works well.
- **Your name** — shown publicly on the site. Optional; you can leave it blank
  and set it later from the admin panel.
- **Site address** — the full URL people will use to reach the site, starting
  with `http://` or `https://`.
- **Administrator username** — 3 to 64 characters (letters, numbers, dots,
  underscores, or hyphens).
- **Password** — at least 12 characters. This account controls the entire site,
  so use a password manager to generate something you wouldn't otherwise
  remember.

> 📸 **Screenshot needed — `14-site-admin-step.png`**
> This form filled in (again, password fields showing dots is fine).

---

## Step 8 — Set up two-factor authentication

portal.lift requires two-factor authentication (MFA) for every admin account —
there's no way to turn it off, by design. You'll need an authenticator app on your
phone: Google Authenticator, Authy, 1Password, and Microsoft Authenticator all
work.

1. Open your authenticator app and scan the QR code shown on screen (or type in
   the manual key underneath it, if you can't scan).
2. Enter the 6-digit code your app now shows for this account.

If the code is rejected, the most common cause on shared hosting is the *server's*
clock being wrong, not your phone's — the error message on this screen tells you
what time the server thinks it is, which is worth checking if this happens.

> 📸 **Screenshot needed — `15-mfa-qr-step.png`**
> The QR code screen. This is safe to share — the QR code and secret key are
> unique to this specific install and expire the moment you either verify a code
> or restart the installer, so a screenshot of it after your install is complete
> is not a security risk.

---

## Step 9 — Save your recovery codes

The installer shows you ten one-time recovery codes. **Save these somewhere safe
right now** — a password manager, printed and locked in a drawer, wherever you'd
keep a spare house key. They're shown exactly once. If you ever lose your phone
without them, there is no other way back into the admin account.

> 📸 **Screenshot needed — `16-recovery-codes.png`**
> Optional — the recovery codes screen with the actual codes blurred or cropped
> out (only the surrounding UI matters for documentation purposes).

---

## Step 10 — Finish

The last screen confirms setup is complete. At this point, `install.php` locks
itself — visiting it again shows a "not found," even for you. This is
intentional: it's a one-time tool, and leaving it reachable would be a standing
risk.

Log in at `https://yourdomain.com/admin/login` with the username, password, and
authenticator app you just set up.

> 📸 **Screenshot needed — `17-install-finished.png`**
> The final "All done" screen.

> 📸 **Screenshot needed — `18-admin-dashboard.png`**
> The admin dashboard right after your first login — this doubles as a "here's
> what you'll see" reference for the rest of the admin documentation.

---

## After installing

The site ships with sample content (a fictional profile, a few sample posts and
projects) so it doesn't look empty on day one. Once you've replaced it with your
own — or if you'd rather start from a blank site — there's a **"Delete demo
content"** button in Admin → Settings that removes all of it in one step. Anything
you've already edited is left alone; only untouched sample rows get deleted.

For upgrading to future releases, see [`UPGRADING.md`](../UPGRADING.md) — it's a
drop-in file replace, no database work required on your part.

---

## Screenshot checklist (for collaborating on this doc)

If you're capturing these in one pass, here's the full list in order:

| # | Filename | Required? |
|---|----------|-----------|
| 1 | `01-download-release.png` | optional |
| 2 | `02-mysql-create-database.png` | yes |
| 3 | `03-mysql-create-user.png` | yes (redact password) |
| 4 | `04-mysql-add-user-to-database.png` | yes |
| 5 | `06-file-manager-upload.png` | yes |
| 6 | `07-file-manager-extract.png` | yes |
| 7 | `08-file-manager-layout.png` | yes |
| 8 | `09-document-root-setting.png` | optional |
| 9 | `10-install-token-screen.png` | yes |
| 10 | `11-file-manager-open-token.png` | yes (redact token) |
| 11 | `12-requirements-check.png` | yes |
| 12 | `13-database-step.png` | yes |
| 13 | `14-site-admin-step.png` | yes |
| 14 | `15-mfa-qr-step.png` | yes |
| 15 | `16-recovery-codes.png` | optional (redact codes) |
| 16 | `17-install-finished.png` | yes |
| 17 | `18-admin-dashboard.png` | yes |

Drop them in RemoteBox (`portal.lift/docs-review/shared-hosting-screenshots/`)
using these exact filenames — after review they land in
`docs/screenshots/shared-hosting/` and slot straight into the placeholders above.
