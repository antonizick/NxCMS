<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $config = Config::get('session');
        $https = ($_SERVER['HTTPS'] ?? '') !== '' || ($_SERVER['SERVER_PORT'] ?? '') === '443';

        session_name($config['name']);
        session_set_cookie_params([
            'lifetime' => 0,           // session cookie; server-side lifetime is enforced separately
            'path' => '/',
            'httponly' => true,
            'secure' => $https,
            'samesite' => 'Lax',       // Lax, not Strict: admin links from email must still work
        ]);
        ini_set('session.gc_maxlifetime', (string) $config['lifetime']);
        ini_set('session.use_strict_mode', '1');

        session_start();
    }
}
