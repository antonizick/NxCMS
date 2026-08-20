<?php
/**
 * Article archive — table view (article.table.jpg). Search + category
 * filter + sort, all via GET so results are linkable/bookmarkable.
 *
 * @var array<string, mixed> $data
 */

declare(strict_types=1);

use App\Models\ContentPost;

$posts = $data['posts'];
$category = $data['category'];
$sort = $data['sort'];
$q = $data['q'];
$page = $data['page'];
$lastPage = $data['lastPage'];
$total = $data['total'];
$categories = $data['categories'];

$qs = static function (array $over) use ($category, $sort, $q, $page): string {
    $params = array_merge(['category' => $category, 'sort' => $sort, 'q' => $q, 'page' => $page], $over);
    $params = array_filter($params, static fn($v) => $v !== '' && $v !== null && $v !== 1);
    return '/articles' . ($params ? '?' . http_build_query($params) : '');
};
?>
<div class="tile page page--wide">
    <p class="page-back"><a href="/"><?= icon('arrow-right', 'icon icon--flip') ?><span>Back to the portal</span></a></p>

    <div class="archive-head">
        <h1 class="page-title"><?= $category === 'all' ? 'Article' : e(ContentPost::LABELS[$category] ?? $category) ?> archive</h1>
        <p class="page-sub"><?= (int) $total ?> article<?= $total === 1 ? '' : 's' ?></p>
    </div>

    <form class="archive-controls" method="get" action="/articles">
        <div class="search-field">
            <?= icon('search', 'icon') ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search articles&hellip;" maxlength="120">
        </div>

        <select name="category" class="archive-select">
            <?php foreach ($categories as $c): ?>
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
        <p class="notice">No articles match<?= $q !== '' ? ' &ldquo;' . e($q) . '&rdquo;' : '' ?>.</p>
    <?php else: ?>
        <div class="archive-table-wrap">
            <table class="archive-table">
                <thead>
                    <tr><th>Date</th><th>Type</th><th>Title</th><th>Analysis Preview</th><th>Image</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $p): ?>
                        <tr>
                            <td data-label="Date"><time datetime="<?= e($p['published_at']) ?>"><?= e(fmt_date($p['published_at'])) ?></time></td>
                            <td data-label="Type" class="archive-type"><?= e(ContentPost::LABELS[$p['category']] ?? $p['category']) ?></td>
                            <td data-label="Title"><a class="archive-title" href="<?= e(post_url($p)) ?>"><?= e($p['title']) ?></a></td>
                            <td data-label="Analysis Preview">
                                <?= e(excerpt($p['body'], 150)) ?>
                                <a class="see-more" href="<?= e(post_url($p)) ?>">(see more)</a>
                            </td>
                            <td data-label="Image" class="archive-thumb">
                                <?php if (($p['image_url'] ?? '') !== ''): ?>
                                    <img src="<?= e($p['image_url']) ?>" alt="" loading="lazy">
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($lastPage > 1): ?>
            <nav class="archive-pager" aria-label="Article pages">
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
