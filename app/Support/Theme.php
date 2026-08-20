<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Settings;

final class Theme
{
    public const COOKIE = 'portal_theme';
    private const VALID = ['dark', 'light'];

    /**
     * Resolve the theme server-side so the correct palette is in the very
     * first byte of HTML. Doing this in JS instead would flash the wrong
     * theme on every page load for anyone who picked the non-default.
     */
    public static function current(): string
    {
        $cookie = $_COOKIE[self::COOKIE] ?? null;
        if (is_string($cookie) && in_array($cookie, self::VALID, true)) {
            return $cookie;
        }

        $default = Settings::site()['theme_default'] ?? 'dark';

        return in_array($default, self::VALID, true) ? $default : 'dark';
    }
}
