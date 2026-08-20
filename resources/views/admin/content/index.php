<?php
/**
 * Admin "Post content" archive list — same shape as the public /articles
 * table but includes suppressed posts and every category.
 *
 * @var array<string, mixed> $data
 */
declare(strict_types=1);

use App\Core\Csrf;
use App\Models\ContentPost;

$posts = $data['posts'];
$category = $data['category'];
$sort = $data['sort'];
$q = $data['q'];
$page = $data['page'];
$lastPage = $data['lastPage'];
$total = $data['total'];

$qs = static function (array $over) use ($category, $sort, $q, $page): string {
    $params = array_merge(['category' => $category, 'sort' => $sort, 'q' => $q, 'page' => $page], $over);
    $params = array_filter($params, static fn($v) => $v !== '' && $v !== null && $v !== 1);
    return '/admin/content' . ($params ? '?' . http_build_query($params) : '');
};
?>
<div class="tile page page--wide">
    <div class="archive-head">
        <h1 class="page-title">Post content</h1>
        <a class="btn btn--accent" href="/admin/content/new"><span>New post</span></a>
    </div>
    <p class="page-sub"><?= (int) $total ?> post<?= $total === 1 ? '' : 's' ?></p>

    <form class="archive-controls" method="get" action="/admin/content">
        <div class="search-field">
            <?= icon('search', 'icon') ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search posts&hellip;" maxlength="120">
        </div>

        <select name="category" class="archive-select">
            <option value="all" <?= $category === 'all' ? 'selected' : '' ?>>All categories</option>
            <?php foreach (ContentPost::CATEGORIES as $c): ?>
                <option value="<?= e($c) ?>" <?= $c === $category ? 'selected' : '' ?>><?= e(ContentPost::LABELS[$c] ?? $c) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="sort" class="archive-select">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
            <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Title A&ndash;Z</option>
        </select>

        <button type="submit" class="btn btn--ghost"><span>Search</span></button>
    </form>

    <?php if (!$posts): ?>
        <p class="notice">No posts match.</p>
    <?php else: ?>
        <div class="archive-table-wrap">
            <table class="archive-table">
                <thead><tr><th>Date</th><th>Category</th><th>Title</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($posts as $p): ?>
                        <tr class="<?= $p['is_suppressed'] ? 'is-suppressed-row' : '' ?>">
                            <td data-label="Date"><?= e(fmt_date($p['published_at'])) ?></td>
                            <td data-label="Category"><?= e(ContentPost::LABELS[$p['category']] ?? $p['category']) ?></td>
                            <td data-label="Title"><a class="archive-title" href="/admin/content/<?= (int) $p['id'] ?>/edit"><?= e($p['title']) ?></a></td>
                            <td data-label="Status">
                                <span class="badge <?= $p['is_suppressed'] ? 'badge--off' : 'badge--ok' ?>">
                                    <?= $p['is_suppressed'] ? 'Suppressed' : 'Published' ?>
                                </span>
                            </td>
                            <td data-label="" class="crud-actions">
                                <form method="post" action="/admin/content/<?= (int) $p['id'] ?>/toggle-suppress">
                                    <?= Csrf::field() ?>
                                    <button class="btn btn--ghost btn--small" type="submit">
                                        <span><?= $p['is_suppressed'] ? 'Unsuppress' : 'Suppress' ?></span>
                                    </button>
                                </form>
                                <form method="post" action="/admin/content/<?= (int) $p['id'] ?>/delete" data-confirm="Delete this post?">
                                    <?= Csrf::field() ?>
                                    <button class="btn btn--ghost btn--small" type="submit"><span>Delete</span></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($lastPage > 1): ?>
            <nav class="archive-pager" aria-label="Post pages">
                <a class="btn btn--ghost<?= $page <= 1 ? ' is-disabled' : '' ?>"
                   href="<?= $page > 1 ? e($qs(['page' => $page - 1])) : '#' ?>"
                   <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                    <?= icon('arrow-right', 'icon icon--flip') ?><span>Prev</span>
                </a>
                <span class="archive-pager-status">Page <?= $page ?> of <?= $lastPage ?></span>
                <a class="btn btn--ghost<?= $page >= $lastPage ? ' is-disabled' : '' ?>"
                   href="<?= $page < $lastPage ? e($qs(['page' => $page + 1])) : '#' ?>"
                   <?= $page >= $lastPage ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                    <span>Next</span><?= icon('arrow-right', 'icon') ?>
                </a>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
