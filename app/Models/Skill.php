<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Skill
{
    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        // Admin-defined sort_order is authoritative — the portal must list
        // skills in exactly the order set in the admin panel.
        return Database::connection()
            ->query('SELECT * FROM skills ORDER BY sort_order, id')
            ->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM skills WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public static function create(string $name, string $iconKey, int $sortOrder): int
    {
        Database::connection()->prepare(
            'INSERT INTO skills (name, icon_key, sort_order) VALUES (:name, :icon, :sort)'
        )->execute([':name' => $name, ':icon' => $iconKey, ':sort' => $sortOrder]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, string $name, string $iconKey, int $sortOrder): void
    {
        Database::connection()->prepare(
            'UPDATE skills SET name = :name, icon_key = :icon, sort_order = :sort, is_demo = 0 WHERE id = :id'
        )->execute([':name' => $name, ':icon' => $iconKey, ':sort' => $sortOrder, ':id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::connection()->prepare('DELETE FROM skills WHERE id = :id')->execute([':id' => $id]);
    }
}
