<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Settings
{
    /** @var array<string, mixed>|null */
    private static ?array $site = null;
    /** @var array<string, mixed>|null */
    private static ?array $profile = null;

    /** @return array<string, mixed> */
    public static function site(): array
    {
        if (self::$site === null) {
            $row = Database::connection()
                ->query('SELECT * FROM site_settings WHERE id = 1')
                ->fetch();
            self::$site = $row ?: [];
        }

        return self::$site;
    }

    /** @return array<string, mixed> */
    public static function profile(): array
    {
        if (self::$profile === null) {
            $row = Database::connection()
                ->query('SELECT * FROM profile WHERE id = 1')
                ->fetch();
            self::$profile = $row ?: [];
        }

        return self::$profile;
    }

    /** @return list<array<string, mixed>> */
    public static function socialLinks(): array
    {
        // A blank url means "don't render the icon" (spec: icon only appears
        // when the admin has filled the field in).
        return Database::connection()->query(
            "SELECT * FROM social_links WHERE url <> '' ORDER BY sort_order, id"
        )->fetchAll();
    }

    /** @param array<string, mixed> $fields */
    public static function updateSite(array $fields): void
    {
        Database::connection()->prepare(
            'UPDATE site_settings SET
                page_title = :page_title, initials = :initials, display_name = :display_name,
                copyright_year = :copyright_year, copyright_text = :copyright_text, footer_text = :footer_text,
                copy_link_text = :copy_link_text, copy_link_url = :copy_link_url, theme_default = :theme_default,
                recon_location_label = :recon_location_label, recon_lat = :recon_lat, recon_lng = :recon_lng,
                recon_timezone = :recon_timezone,
                contact_main_title = :contact_main_title, contact_sub_title = :contact_sub_title,
                contact_button_text = :contact_button_text, is_demo = 0
              WHERE id = 1'
        )->execute([
            ':page_title' => $fields['page_title'],
            ':initials' => $fields['initials'],
            ':display_name' => $fields['display_name'],
            ':copyright_year' => $fields['copyright_year'],
            ':copyright_text' => $fields['copyright_text'],
            ':footer_text' => $fields['footer_text'],
            ':copy_link_text' => $fields['copy_link_text'],
            ':copy_link_url' => $fields['copy_link_url'],
            ':theme_default' => $fields['theme_default'],
            ':recon_location_label' => $fields['recon_location_label'] !== '' ? $fields['recon_location_label'] : null,
            ':recon_lat' => $fields['recon_lat'] !== '' ? $fields['recon_lat'] : null,
            ':recon_lng' => $fields['recon_lng'] !== '' ? $fields['recon_lng'] : null,
            ':recon_timezone' => $fields['recon_timezone'] !== '' ? $fields['recon_timezone'] : null,
            ':contact_main_title' => $fields['contact_main_title'],
            ':contact_sub_title' => $fields['contact_sub_title'],
            ':contact_button_text' => $fields['contact_button_text'],
        ]);

        self::$site = null;
    }

    public const THEME_COLOR_KEYS = [
        'theme_dark_bg', 'theme_dark_card', 'theme_dark_text',
        'theme_dark_accent', 'theme_dark_accent_2', 'theme_dark_orange',
        'theme_dark_news_bg', 'theme_dark_contact_bg',
        'theme_light_bg', 'theme_light_card', 'theme_light_text',
        'theme_light_accent', 'theme_light_accent_2', 'theme_light_orange',
        'theme_light_news_bg', 'theme_light_contact_bg',
    ];

    /** @param array<string, string> $fields keyed by THEME_COLOR_KEYS, each a "#rrggbb" hex color */
    public static function updateThemeColors(array $fields): void
    {
        $set = implode(', ', array_map(static fn(string $k) => "$k = :$k", self::THEME_COLOR_KEYS));
        $params = [];
        foreach (self::THEME_COLOR_KEYS as $k) {
            $params[":$k"] = $fields[$k];
        }

        Database::connection()
            ->prepare("UPDATE site_settings SET $set WHERE id = 1")
            ->execute($params);

        self::$site = null;
    }

    /** @param array<string, mixed> $fields */
    public static function updateProfile(array $fields): void
    {
        Database::connection()->prepare(
            'UPDATE profile SET headshot_url = :headshot_url, logo_url = :logo_url,
                photo3_url = :photo3_url, photo4_url = :photo4_url,
                status_phrase = :status_phrase, status_dot_color = :status_dot_color, bio = :bio,
                is_demo = 0
              WHERE id = 1'
        )->execute([
            ':headshot_url' => $fields['headshot_url'],
            ':logo_url' => $fields['logo_url'],
            ':photo3_url' => $fields['photo3_url'],
            ':photo4_url' => $fields['photo4_url'],
            ':status_phrase' => $fields['status_phrase'],
            ':status_dot_color' => $fields['status_dot_color'],
            ':bio' => $fields['bio'],
        ]);

        self::$profile = null;
    }

    /**
     * All rows including blank-url ones — the admin screen edits those, unlike the public read.
     * @return list<array<string, mixed>>
     */
    public static function allSocialLinks(): array
    {
        return Database::connection()
            ->query('SELECT * FROM social_links ORDER BY sort_order, id')
            ->fetchAll();
    }
}
