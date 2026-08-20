<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Config;
use PDO;

/**
 * Applies pending database/migrations/*.sql files. A no-CLI shared-hosting
 * upgrade is "replace the files, reload the site" — this is what makes that
 * true: called on every successful admin login (see AuthController), so a
 * drop-in file replace picks up new migrations without SSH or a terminal.
 *
 * Dedicated multi-statement connection, separate from Database::connection()
 * (which disables multi-statement execution as defense-in-depth against
 * injection) — migration files legitimately hold several DDL statements each.
 */
final class Migrator
{
    /** @return string[] filenames applied this call; empty if already current */
    public static function runPending(): array
    {
        $pdo = self::connection();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL UNIQUE,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $applied = $pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

        $files = glob(dirname(__DIR__, 2) . '/database/migrations/*.sql') ?: [];
        sort($files);

        $ran = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }

            $pdo->exec((string) file_get_contents($file));
            $pdo->prepare('INSERT INTO migrations (filename) VALUES (?)')->execute([$name]);
            $ran[] = $name;
        }

        return $ran;
    }

    private static function connection(): PDO
    {
        $db = Config::get('db');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']);

        return new PDO($dsn, $db['username'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]);
    }
}
