<?php

declare(strict_types=1);

use App\Support\Icons;

if (!function_exists('e')) {
    /** Escape for HTML text/attribute context. */
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('icon')) {
    function icon(string $name, string $class = 'icon', ?string $label = null): string
    {
        return Icons::render($name, $class, $label);
    }
}

if (!function_exists('asset')) {
    /**
     * Cache-busted URL for a file under public/. Uses the deployed file's
     * mtime, so a deploy invalidates the browser cache without a manual
     * version bump (and without the far-future cache header being a trap).
     */
    function asset(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $file = (defined('PUBLIC_ROOT') ? PUBLIC_ROOT : __DIR__ . '/../public') . $path;
        $stamp = is_file($file) ? filemtime($file) : false;

        return $stamp === false ? $path : $path . '?v=' . $stamp;
    }
}

if (!function_exists('post_url')) {
    /** @param array<string, mixed> $post */
    function post_url(array $post): string
    {
        return '/article/' . (int) $post['id'];
    }
}

if (!function_exists('fmt_date')) {
    function fmt_date(?string $datetime, string $format = 'j M Y'): string
    {
        if (!$datetime) {
            return '';
        }

        return (new DateTimeImmutable($datetime))->format($format);
    }
}

if (!function_exists('relative_date')) {
    /** "3h ago" / "Published today" style stamps, as in the concept screens. */
    function relative_date(?string $datetime): string
    {
        if (!$datetime) {
            return '';
        }

        $then = new DateTimeImmutable($datetime);
        $seconds = time() - $then->getTimestamp();

        if ($seconds < 0) {
            return 'Scheduled';
        }
        if ($seconds < 3600) {
            return max(1, intdiv($seconds, 60)) . 'm ago';
        }
        if ($seconds < 86400) {
            return intdiv($seconds, 3600) . 'h ago';
        }
        if ($seconds < 172800) {
            return 'Yesterday';
        }
        if ($seconds < 2592000) {
            return intdiv($seconds, 86400) . 'd ago';
        }

        return $then->format('j M Y');
    }
}

if (!function_exists('theme_color_overrides')) {
    /**
     * Inline <style> block of CSS custom-property overrides for the curated
     * admin-editable theme colors (bg/card/text/accent/accent-2/orange, plus
     * dedicated News1/Contact tile backgrounds, per theme). Placed after
     * app.css's <link> in <head> so it wins on source order — no !important,
     * no specificity games. Falls back to app.css's own hardcoded defaults
     * for anything that isn't valid stored data.
     *
     * --card-2 (a slightly darker dark-theme shade app.css defines separately
     * from --card) intentionally collapses onto the single "Card" color here
     * — the curated set trades that subtlety for a picker with ~6 fields
     * instead of ~30. Borders and glows (--border, --glow, --t4-border,
     * --t8-border, per-tile box-shadows, etc.) are NOT overridden directly —
     * app.css derives them from --accent/--accent-2/--orange itself via
     * color-mix(), so they follow whatever this function sets automatically.
     *
     * --t4-bg (News1) and --t9-bg (Contact) get their own pickers rather
     * than following --card like every other tile — light theme originally
     * hand-picked distinct looks for these two (a gray gradient hero and a
     * near-black CTA) that a shared Card color can't reproduce. A flat hex
     * picker can't represent that gradient either, so --t4-bg is now always
     * a flat color once set here.
     *
     * @param array<string, mixed> $site
     */
    function theme_color_overrides(array $site): string
    {
        $defaults = [
            'theme_dark_bg' => '#04090b', 'theme_dark_card' => '#07161a', 'theme_dark_text' => '#e6f3f5',
            'theme_dark_accent' => '#22d3ee', 'theme_dark_accent_2' => '#2dd4bf', 'theme_dark_orange' => '#fb923c',
            'theme_dark_news_bg' => '#07161a', 'theme_dark_contact_bg' => '#07161a',
            'theme_light_bg' => '#f4f4f2', 'theme_light_card' => '#ffffff', 'theme_light_text' => '#14181a',
            'theme_light_accent' => '#0d9488', 'theme_light_accent_2' => '#14b8a6', 'theme_light_orange' => '#f97316',
            'theme_light_news_bg' => '#cfcfcd', 'theme_light_contact_bg' => '#0c0a09',
        ];

        $hex = static function (string $key) use ($site, $defaults): string {
            $value = (string) ($site[$key] ?? '');
            return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? $value : $defaults[$key];
        };

        return '<style>'
            . ':root{--bg:' . $hex('theme_dark_bg') . ';--card:' . $hex('theme_dark_card') . ';--card-2:' . $hex('theme_dark_card') . ';'
            . '--text:' . $hex('theme_dark_text') . ';--accent:' . $hex('theme_dark_accent') . ';--accent-2:' . $hex('theme_dark_accent_2') . ';--orange:' . $hex('theme_dark_orange') . ';'
            . '--t4-bg:' . $hex('theme_dark_news_bg') . ';--t9-bg:' . $hex('theme_dark_contact_bg') . ';}'
            . '[data-theme="light"]{--bg:' . $hex('theme_light_bg') . ';--card:' . $hex('theme_light_card') . ';--card-2:' . $hex('theme_light_card') . ';'
            . '--text:' . $hex('theme_light_text') . ';--accent:' . $hex('theme_light_accent') . ';--accent-2:' . $hex('theme_light_accent_2') . ';--orange:' . $hex('theme_light_orange') . ';'
            . '--t4-bg:' . $hex('theme_light_news_bg') . ';--t9-bg:' . $hex('theme_light_contact_bg') . ';}'
            . '</style>';
    }
}

if (!function_exists('youtube_id')) {
    /**
     * Extracts an 11-char YouTube video ID from watch/share/embed URL forms
     * (youtube.com/watch?v=, youtu.be/, youtube.com/embed/, with or without
     * www./m. and trailing query params like ?si=). Null for anything else,
     * so callers can fall back to the plain "watch on YouTube" link.
     */
    function youtube_id(string $url): ?string
    {
        if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $url, $m) === 1) {
            return $m[1];
        }

        return null;
    }
}

if (!function_exists('excerpt')) {
    function excerpt(?string $text, int $length = 160): string
    {
        return \App\Controllers\HomeController::excerpt((string) $text, $length);
    }
}
