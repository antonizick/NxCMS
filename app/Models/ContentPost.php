<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ContentPost
{
    public const CATEGORIES = ['news', 'youtube', 'project_work'];

    public const LABELS = [
        'all' => 'All',
        'news' => 'News',
        'youtube' => 'YouTube',
        'project_work' => 'Project work',
    ];

    /** Single-letter category badges — article sidebar history list. */
    public const INITIALS = [
        'news' => 'N',
        'youtube' => 'Y',
        'project_work' => 'P',
    ];

    public const SORTS = [
        'newest' => 'published_at DESC, id DESC',
        'oldest' => 'published_at ASC, id ASC',
        'title'  => 'title ASC, id ASC',
    ];

    /**
     * Most recent visible posts in a category.
     *
     * `is_suppressed = 0` is applied on every public read without exception:
     * per spec, a suppressed post must look to the public as though it never
     * existed. Keep that predicate in this class and nowhere else.
     *
     * @return list<array<string, mixed>>
     */
    public static function recent(string $category, int $limit = 1, int $offset = 0): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM content_posts
              WHERE category = :category AND is_suppressed = 0
              ORDER BY published_at DESC, id DESC
              LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':category', $category);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function latest(string $category, int $offset = 0): ?array
    {
        return self::recent($category, 1, $offset)[0] ?? null;
    }

    /** Every visible post, for sitemap.xml. @return list<array<string, mixed>> */
    public static function allPublished(): array
    {
        return Database::connection()
            ->query('SELECT id, updated_at FROM content_posts WHERE is_suppressed = 0 ORDER BY published_at DESC')
            ->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM content_posts WHERE id = :id AND is_suppressed = 0'
        );
        $stmt->execute([':id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Paginated, searched, sorted listing for the archive table (Phase 3).
     * $sort must be a key of self::SORTS — validated by the caller so this
     * class stays the only place composing the query.
     *
     * @return list<array<string, mixed>>
     */
    public static function search(string $category, string $q, string $sort, int $limit, int $offset): array
    {
        $order = self::SORTS[$sort] ?? self::SORTS['newest'];
        [$where, $params] = self::searchWhere($category, $q);

        $stmt = Database::connection()->prepare(
            "SELECT * FROM content_posts WHERE {$where}
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

    public static function searchCount(string $category, string $q): int
    {
        [$where, $params] = self::searchWhere($category, $q);

        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM content_posts WHERE {$where}");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Shared WHERE clause + bind params for search()/searchCount(). Named
     * placeholders can't repeat under PDO::ATTR_EMULATE_PREPARES = false
     * (real server-side prepares, one placeholder = one bind), so the LIKE
     * clause is only added at all when there's a query to search for.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private static function searchWhere(string $category, string $q): array
    {
        $where = 'is_suppressed = 0';
        $params = [];

        if (in_array($category, self::CATEGORIES, true)) {
            $where .= ' AND category = :category';
            $params[':category'] = $category;
        }

        if ($q !== '') {
            $where .= ' AND (title LIKE :like_title OR body LIKE :like_body)';
            $like = '%' . self::escapeLike($q) . '%';
            $params[':like_title'] = $like;
            $params[':like_body'] = $like;
        }

        return [$where, $params];
    }

    /** Escape LIKE wildcards in user input so a literal "%"/"_" searches literally. */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    // ── Admin (Phase 5) — unlike the public reads above, these never filter
    // on is_suppressed: the CMS list has to show suppressed posts so an
    // admin can find and un-suppress them. ─────────────────────────────────

    /** @return array<string, mixed>|null */
    public static function findAny(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM content_posts WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> */
    public static function searchAdmin(string $category, string $q, string $sort, int $limit, int $offset): array
    {
        $order = self::SORTS[$sort] ?? self::SORTS['newest'];
        [$where, $params] = self::searchWhereAdmin($category, $q);

        $stmt = Database::connection()->prepare(
            "SELECT * FROM content_posts WHERE {$where}
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

    public static function searchAdminCount(string $category, string $q): int
    {
        [$where, $params] = self::searchWhereAdmin($category, $q);

        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM content_posts WHERE {$where}");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, string>} */
    private static function searchWhereAdmin(string $category, string $q): array
    {
        $where = '1 = 1';
        $params = [];

        if (in_array($category, self::CATEGORIES, true)) {
            $where .= ' AND category = :category';
            $params[':category'] = $category;
        }

        if ($q !== '') {
            $where .= ' AND (title LIKE :like_title OR body LIKE :like_body)';
            $like = '%' . self::escapeLike($q) . '%';
            $params[':like_title'] = $like;
            $params[':like_body'] = $like;
        }

        return [$where, $params];
    }

    /** @param array<string, mixed> $data */
    public static function create(array $data): int
    {
        Database::connection()->prepare(
            'INSERT INTO content_posts (category, title, author, body, link_url, image_url, published_at, is_suppressed)
             VALUES (:category, :title, :author, :body, :link_url, :image_url, :published_at, :is_suppressed)'
        )->execute([
            ':category' => $data['category'],
            ':title' => $data['title'],
            ':author' => $data['author'],
            ':body' => $data['body'],
            ':link_url' => $data['link_url'],
            ':image_url' => $data['image_url'],
            ':published_at' => $data['published_at'],
            ':is_suppressed' => $data['is_suppressed'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public static function update(int $id, array $data): void
    {
        Database::connection()->prepare(
            'UPDATE content_posts SET category = :category, title = :title, author = :author, body = :body,
                link_url = :link_url, image_url = :image_url, published_at = :published_at,
                is_suppressed = :is_suppressed, is_demo = 0
              WHERE id = :id'
        )->execute([
            ':category' => $data['category'],
            ':title' => $data['title'],
            ':author' => $data['author'],
            ':body' => $data['body'],
            ':link_url' => $data['link_url'],
            ':image_url' => $data['image_url'],
            ':published_at' => $data['published_at'],
            ':is_suppressed' => $data['is_suppressed'],
            ':id' => $id,
        ]);
    }

    public static function toggleSuppress(int $id): void
    {
        Database::connection()
            ->prepare('UPDATE content_posts SET is_suppressed = 1 - is_suppressed WHERE id = :id')
            ->execute([':id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::connection()->prepare('DELETE FROM content_posts WHERE id = :id')->execute([':id' => $id]);
    }
}

assert(ContentPost::SORTS['newest'] !== '');

// lucent: single-table content model, no pagination/search yet — Phase 3 adds
// the archive table + focus view (search/filter/sort/pagination) on top of this.
