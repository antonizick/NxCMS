<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The site icon, drawn from the admin's own `initials` setting rather than
 * shipped as a fixed file.
 *
 * A redistributable install can't ship one favicon: every deployment would
 * show the same letters in the browser tab. Generating it means each install
 * is identifiably its own, with no image editor and no upload step — the
 * admin types two letters into Settings and the tab follows.
 *
 * SVG rather than a GD-rendered PNG: no font file to bundle or license, it
 * stays crisp at every tab/bookmark/retina size, and it needs no writable
 * directory to cache into.
 */
final class Monogram
{
    private const FALLBACK = 'P';
    private const MAX_CHARS = 3;

    /**
     * @param string $initials the admin's `initials` setting
     * @param string $title    page title, used only to derive a fallback
     * @param string $accent   "#rrggbb" ring/text colour
     */
    public static function svg(string $initials, string $title = '', string $accent = '#22d3ee'): string
    {
        $text = self::letters($initials, $title);
        $accent = preg_match('/^#[0-9a-fA-F]{6}$/', $accent) === 1 ? $accent : '#22d3ee';

        // Shrink the type as letters are added so three characters still fit
        // inside the ring instead of overflowing it.
        $size = [1 => 15, 2 => 12, 3 => 9][strlen($text)] ?? 12;

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" role="img" aria-label="{$text}">
              <rect width="32" height="32" rx="8" fill="#0a1416"/>
              <circle cx="16" cy="16" r="14.1" fill="none" stroke="{$accent}" stroke-width="0.7" opacity=".35"/>
              <circle cx="16" cy="16" r="12.4" fill="none" stroke="{$accent}" stroke-width="1.4" opacity=".85"/>
              <text x="16" y="16" font-family="ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,sans-serif"
                    font-size="{$size}" font-weight="700" letter-spacing="0.3"
                    text-anchor="middle" dominant-baseline="central" fill="#e2f6fa">{$text}</text>
            </svg>
            SVG;
    }

    /**
     * Changes whenever the rendered icon would change, so the URL can carry it
     * and browsers pick up an edited `initials` without a hard refresh.
     */
    public static function version(string $initials, string $title, string $accent): string
    {
        return substr(hash('sha256', self::letters($initials, $title) . $accent), 0, 8);
    }

    /**
     * Deliberately no hardcoded brand default: an unset `initials` falls back
     * to the site's own title, never to the letters of whoever built this.
     */
    private static function letters(string $initials, string $title): string
    {
        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $initials));
        if ($clean !== '') {
            return substr($clean, 0, self::MAX_CHARS);
        }

        // "Ada Sinclair" -> "AS"; "Portal" -> "P".
        $words = preg_split('/\s+/', trim($title)) ?: [];
        $derived = '';
        foreach ($words as $word) {
            $first = (string) preg_replace('/[^A-Za-z0-9]/', '', $word);
            if ($first !== '') {
                $derived .= strtoupper($first[0]);
            }
        }

        return $derived !== '' ? substr($derived, 0, 2) : self::FALLBACK;
    }
}

assert(Monogram::svg('nx', '', '#22d3ee') !== '');
