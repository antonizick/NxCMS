<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ContactSubmission
{
    public const SORTS = [
        'newest' => 'created_at DESC, id DESC',
        'oldest' => 'created_at ASC, id ASC',
    ];

    public static function create(string $name, string $email, string $message, string $ip): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO contact_submissions (name, email, message, ip_address)
             VALUES (:name, :email, :message, :ip)'
        );
        $stmt->execute([':name' => $name, ':email' => $email, ':message' => $message, ':ip' => $ip]);
    }

    public static function countToday(): int
    {
        return (int) Database::connection()
            ->query('SELECT COUNT(*) FROM contact_submissions WHERE created_at >= CURDATE()')
            ->fetchColumn();
    }

    /** Per-IP submission count in the trailing window, for the abuse-response rate limit. */
    public static function recentByIp(string $ip, int $minutes): int
    {
        $minutes = max(1, $minutes);
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM contact_submissions
              WHERE ip_address = :ip AND created_at > (NOW() - INTERVAL {$minutes} MINUTE)"
        );
        $stmt->execute([':ip' => $ip]);

        return (int) $stmt->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM contact_submissions WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function markRead(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE contact_submissions SET is_read = 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM contact_submissions WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function unreadCount(): int
    {
        return (int) Database::connection()
            ->query('SELECT COUNT(*) FROM contact_submissions WHERE is_read = 0')
            ->fetchColumn();
    }

    /**
     * @param 'all'|'unread'|'read' $filter
     * @return list<array<string, mixed>>
     */
    public static function searchAdmin(string $filter, string $q, string $sort, int $limit, int $offset): array
    {
        $order = self::SORTS[$sort] ?? self::SORTS['newest'];
        [$where, $params] = self::searchWhere($filter, $q);

        $stmt = Database::connection()->prepare(
            "SELECT * FROM contact_submissions WHERE {$where}
              ORDER BY {$order}
              LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function searchAdminCount(string $filter, string $q): int
    {
        [$where, $params] = self::searchWhere($filter, $q);

        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM contact_submissions WHERE {$where}");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, string>} */
    private static function searchWhere(string $filter, string $q): array
    {
        $where = '1 = 1';
        $params = [];

        if ($filter === 'unread') {
            $where .= ' AND is_read = 0';
        } elseif ($filter === 'read') {
            $where .= ' AND is_read = 1';
        }

        if ($q !== '') {
            $where .= ' AND (name LIKE :like_name OR email LIKE :like_email OR message LIKE :like_message)';
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $params[':like_name'] = $like;
            $params[':like_email'] = $like;
            $params[':like_message'] = $like;
        }

        return [$where, $params];
    }
}
