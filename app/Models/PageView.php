<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

/**
 * Public-page view tracking for the admin engagement dashboard (spec:
 * "page click and load tracking... to populate an admin dashboard so that
 * user engagement can be tracked"). One row per request to a tracked public
 * route — see the recordCurrent() call sites in the public controllers.
 *
 * Keeps both ip_hash (cheap "was this the same visitor" grouping for the
 * aggregate dashboard) and the raw ip_address (admin visitor-log table,
 * /admin/visitors — same posture as activity_log/contact_submissions).
 */
final class PageView
{
    public static function recordCurrent(): void
    {
        $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

        $stmt = Database::connection()->prepare(
            'INSERT INTO page_views (path, ip_hash, ip_address, user_agent, referrer, device_type)
             VALUES (:path, :ip_hash, :ip_address, :ua, :referrer, :device)'
        );
        $stmt->execute([
            ':path' => mb_substr($path, 0, 255),
            ':ip_hash' => hash('sha256', $ip),
            ':ip_address' => mb_substr($ip, 0, 45) ?: null,
            ':ua' => mb_substr($ua, 0, 512),
            ':referrer' => mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 512) ?: null,
            ':device' => self::deviceType($ua),
        ]);
    }

    private static function deviceType(string $ua): string
    {
        if ($ua === '') {
            return 'desktop';
        }
        if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview/i', $ua)) {
            return 'bot';
        }
        if (preg_match('/ipad|tablet(?!.*mobile)|kindle|playbook/i', $ua)) {
            return 'tablet';
        }
        if (preg_match('/mobi|iphone|ipod|android.*mobile/i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    public static function totalViews(int $days): int
    {
        $stmt = self::since($days, 'SELECT COUNT(*) FROM page_views');

        return (int) $stmt->fetchColumn();
    }

    public static function uniqueVisitors(int $days): int
    {
        $stmt = self::since($days, 'SELECT COUNT(DISTINCT ip_hash) FROM page_views');

        return (int) $stmt->fetchColumn();
    }

    /** @return array<string, int> date (Y-m-d) => view count, always $days entries including zero days */
    public static function dailyCounts(int $days): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DATE(created_at) AS d, COUNT(*) AS c FROM page_views
              WHERE created_at > (NOW() - INTERVAL :days DAY)
              GROUP BY DATE(created_at)'
        );
        $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
        $stmt->execute();
        $byDate = array_column($stmt->fetchAll(), 'c', 'd');

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $out[$day] = (int) ($byDate[$day] ?? 0);
        }

        return $out;
    }

    /** @return list<array{path: string, views: int}> */
    public static function topPaths(int $days, int $limit = 8): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT path, COUNT(*) AS views FROM page_views
              WHERE created_at > (NOW() - INTERVAL :days DAY)
              GROUP BY path ORDER BY views DESC LIMIT :limit'
        );
        $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return list<array{referrer: string, views: int}> excludes blank/self referrers */
    public static function topReferrers(int $days, int $limit = 8): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT referrer, COUNT(*) AS views FROM page_views
              WHERE created_at > (NOW() - INTERVAL :days DAY)
                AND referrer IS NOT NULL AND referrer != ''
                AND referrer NOT LIKE :self
              GROUP BY referrer ORDER BY views DESC LIMIT :limit"
        );
        $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
        // Falling back to '' would make the LIKE pattern '%%' and silently
        // exclude every referrer, so derive the host from APP_URL instead.
        $selfHost = $_SERVER['HTTP_HOST']
            ?? (parse_url((string) Config::get('app')['url'], PHP_URL_HOST) ?: 'localhost');
        $stmt->bindValue(':self', '%' . $selfHost . '%');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<string, int> device_type => count, always includes all four keys */
    public static function deviceBreakdown(int $days): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT device_type, COUNT(*) AS c FROM page_views
              WHERE created_at > (NOW() - INTERVAL :days DAY)
              GROUP BY device_type'
        );
        $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
        $stmt->execute();
        $byType = array_column($stmt->fetchAll(), 'c', 'device_type');

        return [
            'desktop' => (int) ($byType['desktop'] ?? 0),
            'mobile' => (int) ($byType['mobile'] ?? 0),
            'tablet' => (int) ($byType['tablet'] ?? 0),
            'bot' => (int) ($byType['bot'] ?? 0),
        ];
    }

    private static function since(int $days, string $sql): \PDOStatement
    {
        $stmt = Database::connection()->prepare($sql . ' WHERE created_at > (NOW() - INTERVAL :days DAY)');
        $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    // ── Admin visitor log (searchable/filterable/sortable per-visit table) ──

    public const SORTS = [
        'newest' => 'pv.created_at DESC, pv.id DESC',
        'oldest' => 'pv.created_at ASC, pv.id ASC',
    ];

    public const DEVICES = ['desktop', 'mobile', 'tablet', 'bot'];

    /**
     * $device is 'all' or one of self::DEVICES.
     * Resolves article titles for /article/{id} paths via a join, so the
     * admin sees "News: Q3 launch" rather than a bare path.
     *
     * @return list<array<string, mixed>>
     */
    public static function searchAdmin(string $device, string $q, string $sort, int $limit, int $offset): array
    {
        $order = self::SORTS[$sort] ?? self::SORTS['newest'];
        [$where, $params] = self::searchWhereAdmin($device, $q);

        $stmt = Database::connection()->prepare(
            "SELECT pv.*, cp.title AS article_title, cp.category AS article_category
               FROM page_views pv
               LEFT JOIN content_posts cp ON pv.path = CONCAT('/article/', cp.id)
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

    public static function searchAdminCount(string $device, string $q): int
    {
        [$where, $params] = self::searchWhereAdmin($device, $q);

        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM page_views pv WHERE {$where}");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, string>} */
    private static function searchWhereAdmin(string $device, string $q): array
    {
        $where = '1 = 1';
        $params = [];

        if (in_array($device, self::DEVICES, true)) {
            $where .= ' AND pv.device_type = :device';
            $params[':device'] = $device;
        }

        if ($q !== '') {
            $where .= ' AND (pv.ip_address LIKE :like_ip OR pv.path LIKE :like_path)';
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $params[':like_ip'] = $like;
            $params[':like_path'] = $like;
        }

        return [$where, $params];
    }

    // ── Admin unique-visitors tab (one row per IP, sortable by visit count) ──

    public const UNIQUE_SORTS = [
        'most' => 'visits DESC, last_seen DESC',
        'least' => 'visits ASC, last_seen DESC',
        'newest' => 'last_seen DESC',
        'oldest' => 'first_seen ASC',
    ];

    /**
     * Groups page_views by raw IP. Rows from before ip_address was tracked
     * (migration 009) have no IP and can't be grouped, so they're excluded
     * here — they still show up in the per-visit log with IP "—".
     *
     * $device/$q filter which individual visits count toward each IP's
     * totals (same semantics as searchAdmin), not which IPs are shown.
     *
     * @return list<array<string, mixed>>
     */
    public static function searchAdminUnique(string $device, string $q, string $sort, int $limit, int $offset): array
    {
        $order = self::UNIQUE_SORTS[$sort] ?? self::UNIQUE_SORTS['most'];
        [$where, $params] = self::searchWhereAdmin($device, $q);

        $stmt = Database::connection()->prepare(
            "SELECT pv.ip_address AS ip_address,
                    COUNT(*) AS visits,
                    MIN(pv.created_at) AS first_seen,
                    MAX(pv.created_at) AS last_seen,
                    COUNT(DISTINCT pv.path) AS pages,
                    GROUP_CONCAT(DISTINCT pv.device_type ORDER BY pv.device_type SEPARATOR ', ') AS devices
               FROM page_views pv
              WHERE pv.ip_address IS NOT NULL AND {$where}
              GROUP BY pv.ip_address
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

    public static function searchAdminUniqueCount(string $device, string $q): int
    {
        [$where, $params] = self::searchWhereAdmin($device, $q);

        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM (
                 SELECT 1 FROM page_views pv
                  WHERE pv.ip_address IS NOT NULL AND {$where}
                  GROUP BY pv.ip_address
             ) t"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}
