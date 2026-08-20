# Upgrading

## 0.2.1 → 0.2.2

Code only — no migration. Adds an optional `ADMIN_URL` env var (alongside
the existing `APP_URL`) for installs that reverse-proxy the admin panel on
a separate origin from the public site — e.g. `portal.example.com` while
the public site is `example.com`. Leave it unset and nothing changes: the
Toolbox double-click shortcut stays a same-origin `/admin/login` link, same
as before.

Also swaps the raw `/article/{id}` paths in the dashboard's Top pages panel
for the post's title, linked out to the live article.

## 0.2.0 → 0.2.1

Documentation only — adds `docs/CAROUSELS.md` and a troubleshooting entry for
featured posts that do not appear. No migration, no code change, nothing to do
beyond replacing the files if you want the docs locally.

## 0.1.0 → 0.2.0

Adds the Misc category and the profile/map tile carousels. One migration
(`010_misc_category_and_tile_rotations.sql`) applies itself on the next admin
login, as below.

Nothing on your home page changes on upgrade. The new rotations start empty,
and both tiles render exactly as they did until you flag a post into one —
from the post editor, or the Carousel column on the post list. The sample
content the migration seeds is skipped entirely on any install where "Delete
demo content" has already been used, so an established site is untouched.

Every release is a drop-in file replace. There is no upgrade wizard and no CLI
step required — schema changes apply themselves the next time an admin logs in.

## Shared hosting (no SSH / no CLI)

1. **Back up first.** Export the database from your host's control panel
   (phpMyAdmin → Export), and download a copy of `storage/uploads/` if you
   want a safety net for user-uploaded images. This isn't optional — step 2
   overwrites application files, and there's no undo.
2. Download the new release zip and extract it locally.
3. Upload every file it contains **except** these — overwriting them destroys
   your live site's configuration and data:
   - `.env` (or wherever `storage/app.env` lives, if you moved it)
   - `storage/` (uploads, and the SQLite/file state the app keeps there)
4. Visit the site and log in as an admin. Pending `database/migrations/*.sql`
   files apply automatically at that point — see `App\Support\Migrator`,
   called from `AuthController` on every successful login. No visible change
   if there's nothing pending.

If a migration fails, login still succeeds — the app logs the error
(`error_log`, and an `activity_log` row of `migrations_applied` on success)
rather than locking you out. Check your host's PHP error log if the admin
dashboard looks like something's missing after an upgrade.

## Docker (Model B)

```
git pull                # or re-download and diff against your local changes
make down
make up                 # rebuilds the image; the composer service reinstalls
                         # vendor/ automatically
```

Migrations still apply on next admin login, same as above — `make up` does
not run them itself.

## Notes

- Migrations are forward-only. There is no down-migration or rollback
  mechanism; restore from your database backup if an upgrade goes wrong.
- Never delete or hand-edit the `migrations` table — it's how the app knows
  what's already applied. If a migration was already run by hand, insert a
  matching row instead of re-running the `.sql` file.
- Check `VERSION` after upgrading to confirm the files actually replaced.
