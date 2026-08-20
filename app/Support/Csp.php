<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Content-Security-Policy, sent from the PHP bootstrap rather than .htaccess:
 * the JSON-LD block in layouts/public.php needs a per-request nonce so
 * script-src can stay free of 'unsafe-inline'.
 */
final class Csp
{
    private static ?string $nonce = null;

    public static function nonce(): string
    {
        if (self::$nonce === null) {
            self::$nonce = base64_encode(random_bytes(16));
        }

        return self::$nonce;
    }

    public static function sendHeader(): void
    {
        $nonce = self::nonce();

        // lucent: style-src keeps 'unsafe-inline' — the dashboard/map/captcha
        // views set computed values (bar heights, tile offsets) via inline
        // style="--pct:..%" attributes rather than a stylesheet of every
        // possible percentage. Upgrade path: move those to CSS custom
        // properties set from JS if a stricter style-src is ever needed.
        $policy = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            // https: (any host) rather than an allowlist: article bodies are
            // Markdown authored only by authenticated admins (see
            // Markdown.php's html_input=allow docblock — same trust
            // boundary), and Nick embeds images from wherever he's hosting
            // them that week (S3, GitHub, ...) rather than one fixed origin.
            "img-src 'self' data: https:",
            "font-src 'self'",
            "connect-src 'self'",
            // YouTube-post embeds (article.php) — nocookie.com avoids setting
            // tracking cookies until the visitor actually presses play.
            "frame-src https://www.youtube-nocookie.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);

        header("Content-Security-Policy: {$policy}");
    }
}
