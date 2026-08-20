<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * The sample content a fresh install ships with (see
 * database/migrations/002_seed_public_content.sql), and the one-click removal
 * of it.
 *
 * Everything seeded carries is_demo = 1. Every admin-facing update clears that
 * flag on the row it touches, so anything a human has edited stops counting as
 * demo content and survives this. That is the whole point of the flag: the
 * button has to be safe to press on day 300, not just day 1.
 */
final class DemoContent
{
    /** Row tables where demo entries are deleted outright. */
    private const ROW_TABLES = ['content_posts', 'projects', 'skills', 'social_links'];

    /** @return array<string, int> table => remaining demo rows, plus the two single-row flags */
    public static function counts(): array
    {
        $db = Database::connection();
        $out = [];

        foreach (self::ROW_TABLES as $table) {
            $out[$table] = (int) $db->query("SELECT COUNT(*) FROM {$table} WHERE is_demo = 1")->fetchColumn();
        }
        $out['profile'] = (int) $db->query('SELECT COUNT(*) FROM profile WHERE id = 1 AND is_demo = 1')->fetchColumn();
        $out['site_settings'] = (int) $db->query('SELECT COUNT(*) FROM site_settings WHERE id = 1 AND is_demo = 1')->fetchColumn();

        return $out;
    }

    public static function exists(): bool
    {
        return array_sum(self::counts()) > 0;
    }

    /**
     * Deletes seeded rows and resets the seeded single-row values.
     *
     * profile and site_settings are updated in place rather than deleted (the
     * app requires exactly one row of each), and only when their own is_demo
     * flag is still set — so an admin who wrote a real bio and then pressed
     * this keeps it.
     *
     * @return int rows affected in total
     */
    public static function purge(): int
    {
        $db = Database::connection();
        $affected = 0;

        $db->beginTransaction();
        try {
            foreach (self::ROW_TABLES as $table) {
                $affected += $db->exec("DELETE FROM {$table} WHERE is_demo = 1");
            }

            // Blank rather than default-y placeholder text: an empty bio and no
            // avatar render as absent by design, which is a truer "nothing here
            // yet" than leftover sample wording would be.
            $affected += $db->exec(
                "UPDATE profile SET headshot_url = NULL, logo_url = NULL, photo3_url = NULL,
                        photo4_url = NULL, bio = NULL, status_phrase = '', is_demo = 0
                  WHERE id = 1 AND is_demo = 1"
            );

            $affected += $db->exec(
                "UPDATE site_settings SET
                        footer_text = '', copy_link_url = '', copy_link_text = 'Copy link',
                        recon_location_label = NULL, recon_lat = NULL, recon_lng = NULL,
                        recon_timezone = NULL, contact_sub_title = '', is_demo = 0
                  WHERE id = 1 AND is_demo = 1"
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return $affected;
    }
}
