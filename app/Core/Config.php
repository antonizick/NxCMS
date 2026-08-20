<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static ?array $data = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$data === null) {
            self::$data = require dirname(__DIR__, 2) . '/config/config.php';
        }

        return self::$data[$key] ?? $default;
    }
}
