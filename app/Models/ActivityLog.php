<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ActivityLog
{
    public static function record(?int $adminId, string $action, ?string $detail, bool $success = true): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO activity_log (admin_id, action, detail, ip_address, user_agent, success)
             VALUES (:admin_id, :action, :detail, :ip, :ua, :success)'
        );
        $stmt->execute([
            ':admin_id' => $adminId,
            ':action' => $action,
            ':detail' => $detail,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ':ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
            ':success' => $success ? 1 : 0,
        ]);
    }

    /**
     * Failed-attempt counts for LoginThrottle. $minutes is always an internal
     * constant (never request input), so it's interpolated rather than bound —
     * MySQL doesn't reliably accept a placeholder inside INTERVAL.
     */
    public static function recentFailuresByIp(string $ip, int $minutes): int
    {
        $minutes = max(1, $minutes);
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM activity_log
              WHERE action IN ('login_failed', 'mfa_failed') AND ip_address = :ip
                AND created_at > (NOW() - INTERVAL {$minutes} MINUTE)"
        );
        $stmt->execute([':ip' => $ip]);

        return (int) $stmt->fetchColumn();
    }

    public static function recentFailuresByUsername(string $username, int $minutes): int
    {
        $minutes = max(1, $minutes);
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM activity_log
              WHERE action IN ('login_failed', 'mfa_failed') AND detail = :username
                AND created_at > (NOW() - INTERVAL {$minutes} MINUTE)"
        );
        $stmt->execute([':username' => $username]);

        return (int) $stmt->fetchColumn();
    }

    // ── Admin viewer (Phase 6) — searchable/filterable/sortable activity log ──

    public const SORTS = [
        'newest' => 'a.created_at DESC, a.id DESC',
        'oldest' => 'a.created_at ASC, a.id ASC',
    ];

    /** @return list<string> distinct action values seen so far, for the filter dropdown */
    public static function distinctActions(): array
    {
        return Database::connection()
            ->query('SELECT DISTINCT action FROM activity_log ORDER BY action')
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @param 'all'|'success'|'failed' $outcome
     * @return list<array<string, mixed>>
     */
    public static function searchAdmin(string $action, string $outcome, string $q, string $sort, int $limit, int $offset): array
    {
        $order = self::SORTS[$sort] ?? self::SORTS['newest'];
        [$where, $params] = self::searchWhere($action, $outcome, $q);

        $stmt = Database::connection()->prepare(
            "SELECT a.*, ad.username AS admin_username FROM activity_log a
              LEFT JOIN admins ad ON ad.id = a.admin_id
              WHERE {$where}
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

    public static function searchAdminCount(string $action, string $outcome, string $q): int
    {
        [$where, $params] = self::searchWhere($action, $outcome, $q);

        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM activity_log a WHERE {$where}");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, string>} */
    private static function searchWhere(string $action, string $outcome, string $q): array
    {
        $where = '1 = 1';
        $params = [];

        if ($action !== 'all') {
            $where .= ' AND a.action = :action';
            $params[':action'] = $action;
        }
        if ($outcome === 'success') {
            $where .= ' AND a.success = 1';
        } elseif ($outcome === 'failed') {
            $where .= ' AND a.success = 0';
        }
        if ($q !== '') {
            $where .= ' AND (a.detail LIKE :like_detail OR a.ip_address LIKE :like_ip)';
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $params[':like_detail'] = $like;
            $params[':like_ip'] = $like;
        }

        return [$where, $params];
    }
}
