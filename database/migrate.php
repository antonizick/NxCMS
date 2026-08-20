<?php

declare(strict_types=1);

// Run from the application root: php database/migrate.php
// (The web installer runs the same logic in-process on first setup.)
// Applies any migrations/*.sql not yet recorded in the `migrations` table.

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Config;

// Dedicated connection with multi-statement execution enabled — migration
// files contain several CREATE TABLE statements per file. The app-wide
// Database::connection() intentionally leaves this off (defense in depth
// against multi-statement injection); only this offline script needs it.
$db = Config::get('db');
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']);
$pdo = new PDO($dsn, $db['username'], $db['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
]);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$applied = $pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

$files = glob(__DIR__ . '/migrations/*.sql');
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        echo "skip:  $name\n";
        continue;
    }

    $sql = file_get_contents($file);
    $pdo->exec($sql);
    $stmt = $pdo->prepare('INSERT INTO migrations (filename) VALUES (?)');
    $stmt->execute([$name]);
    echo "apply: $name\n";
}

echo "done.\n";
