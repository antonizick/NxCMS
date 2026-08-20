<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class SocialLink
{
    public const PLATFORMS = [
        'x', 'facebook', 'instagram', 'tiktok', 'threads', 'youtube', 'linkedin', 'github',
        'discord', 'twitch', 'reddit', 'mastodon', 'bluesky', 'dribbble', 'other',
    ];

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM social_links WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public static function create(string $platform, ?string $label, string $url, int $sortOrder): int
    {
        Database::connection()->prepare(
            'INSERT INTO social_links (platform, label, url, sort_order) VALUES (:p, :l, :u, :s)'
        )->execute([':p' => $platform, ':l' => $label, ':u' => $url, ':s' => $sortOrder]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, string $platform, ?string $label, string $url, int $sortOrder): void
    {
        Database::connection()->prepare(
            'UPDATE social_links SET platform = :p, label = :l, url = :u, sort_order = :s,
                    is_demo = 0 WHERE id = :id'
        )->execute([':p' => $platform, ':l' => $label, ':u' => $url, ':s' => $sortOrder, ':id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::connection()->prepare('DELETE FROM social_links WHERE id = :id')->execute([':id' => $id]);
    }
}
