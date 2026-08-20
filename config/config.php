<?php

declare(strict_types=1);

// Wrapped in an IIFE deliberately: `require` shares the including scope's
// variables (it is not scope-isolated), so a bare top-level $key/$value here
// would silently clobber same-named locals in whatever function/method calls
// `require 'config.php'` (e.g. Config::get(string $key, ...)). Discovered
// 2026-08-18 the hard way — Config::get('db') returned the default because
// this file's loop variable overwrote the caller's $key parameter mid-call.
return (static function (): array {
    // The .env lives at the app root by default — <approot>/.env, one level up
    // from this file. It is never inside public/, and the app root ships an
    // .htaccess denying all access, so it cannot be served as a static file
    // even if the host points the docroot at the app root.
    //
    // PORTAL_ENV_FILE overrides this. Use it when the host lets you put secrets
    // somewhere better still — outside the app tree entirely (e.g. ~/private/).
    $envPath = getenv('PORTAL_ENV_FILE') ?: dirname(__DIR__) . '/.env';

    // is_readable(), not just is_file(): a .env that exists but is owned by
    // another user (PHP running as www-data against a file written by the
    // account user) would otherwise pass the check, then emit a warning naming
    // the absolute server path — this runs before index.php can turn
    // display_errors off, so that warning reaches the browser.
    if (!is_file($envPath) || !is_readable($envPath)) {
        http_response_code(500);
        exit('Configuration missing.');
    }

    $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        http_response_code(500);
        exit('Configuration missing.');
    }

    $env = [];
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value, " \t\"'");
    }

    return [
        'app' => [
            'env' => $env['APP_ENV'] ?? 'production',
            'debug' => ($env['APP_DEBUG'] ?? 'false') === 'true',
            // Falls back to the requesting host so a fresh install still builds
            // correct absolute URLs (sitemap, canonical links) before APP_URL is set.
            'url' => $env['APP_URL'] ?? ('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')),
        ],
        'db' => [
            'host' => $env['DB_HOST'] ?? '127.0.0.1',
            'port' => (int) ($env['DB_PORT'] ?? 3308),
            'database' => $env['DB_DATABASE'] ?? 'portal',
            'username' => $env['DB_USERNAME'] ?? '',
            'password' => $env['DB_PASSWORD'] ?? '',
        ],
        'storage' => [
            // Uploaded files live outside the docroot; served via a controlled route.
            // __DIR__ here is <approot>/config, so one level up is <approot> —
            // NOT two (that lands outside the app root entirely, e.g. ~/apps
            // instead of ~/apps/portal on the server).
            'uploads_path' => $env['UPLOADS_PATH'] ?? (dirname(__DIR__) . '/storage/uploads'),
        ],
        'session' => [
            'name' => 'portal_admin_sess',
            'lifetime' => 60 * 60 * 4, // 4 hours
        ],
        'security' => [
            // Encrypts admins.mfa_secret at rest. Never falls back to a default —
            // an empty/missing key must hard-fail, not silently encrypt with ''.
            'app_key' => $env['APP_KEY'] ?? '',
        ],
    ];
})();
