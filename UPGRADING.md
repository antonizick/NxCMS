# Upgrading

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
