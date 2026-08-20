<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Project
{
    /** @return list<array<string, mixed>> */
    public static function published(int $limit = 8): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM projects WHERE published = 1 ORDER BY sort_order, id LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return Database::connection()
            ->query('SELECT * FROM projects ORDER BY sort_order, id')
            ->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM projects WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** @param array<string, mixed> $data */
    public static function create(array $data): int
    {
        Database::connection()->prepare(
            'INSERT INTO projects (title, description, github_url, external_url, sort_order, published)
             VALUES (:title, :description, :github_url, :external_url, :sort_order, :published)'
        )->execute([
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':github_url' => $data['github_url'],
            ':external_url' => $data['external_url'],
            ':sort_order' => $data['sort_order'],
            ':published' => $data['published'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public static function update(int $id, array $data): void
    {
        Database::connection()->prepare(
            'UPDATE projects SET title = :title, description = :description, github_url = :github_url,
                external_url = :external_url, sort_order = :sort_order, published = :published,
                is_demo = 0
              WHERE id = :id'
        )->execute([
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':github_url' => $data['github_url'],
            ':external_url' => $data['external_url'],
            ':sort_order' => $data['sort_order'],
            ':published' => $data['published'],
            ':id' => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        Database::connection()->prepare('DELETE FROM projects WHERE id = :id')->execute([':id' => $id]);
    }
}
