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
$rotation = $data['rotation'];
$rotationCounts = $data['rotationCounts'];
$rotationLimit = $data['rotationLimit'];
$page = $data['page'];
$lastPage = $data['lastPage'];
$total = $data['total'];

$qs = static function (array $over) use ($category, $rotation, $sort, $q, $page): string {
    $params = array_merge(['category' => $category, 'rotation' => $rotation, 'sort' => $sort, 'q' => $q, 'page' => $page], $over);
    $params = array_filter($params, static fn($v) => $v !== '' && $v !== null && $v !== 1 && $v !== 'all');
    return '/admin/content' . ($params ? '?' . http_build_query($params) : '');
};

/* Every row action posts the filter state it was taken under, so toggling a
 * checkbox lands back on the same filtered page instead of resetting to an
 * unfiltered list halfway through curating one. The controller re-validates
 * all of it before building the redirect. */
$filterState = static function () use ($category, $rotation, $sort, $q, $page): string {
    $out = '';
    foreach (['category' => $category, 'rotation' => $rotation, 'sort' => $sort, 'q' => $q, 'page' => (string) $page] as $name => $value) {
        if ($value !== '' && $value !== 'all') {
            $out .= '<input type="hidden" name="' . $name . '" value="' . e((string) $value) . '">';
        }
    }
    return $out;
};

$rotationFilterLabels = [
    'all' => 'Any carousel state',
    'either' => 'On either carousel',
    'profile' => 'On profile carousel',
    'map' => 'On map carousel',
    'none' => 'On neither carousel',
];
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

        <select name="rotation" class="archive-select">
            <?php foreach ($rotationFilterLabels as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $value === $rotation ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="sort" class="archive-select">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
            <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Title A&ndash;Z</option>
        </select>

        <button type="submit" class="btn btn--ghost"><span>Search</span></button>
    </form>

    <?php /* Flagging past the cap is silently a no-op — the home page takes the
             newest ROTATION_LIMIT and the rest never appear. Say so here rather
             than leaving it to be discovered. */ ?>
    <p class="rotation-slots">
        <?php foreach (['profile' => 'Profile tile', 'map' => 'Map tile'] as $tile => $label):
            $count = (int) ($rotationCounts[$tile] ?? 0);
            $over = $count > $rotationLimit;
        ?>
            <span class="rotation-slot<?= $over ? ' is-over' : '' ?>">
                <?= e($label) ?>:
                <?php if ($over): ?>
                    <strong>showing <?= $rotationLimit ?> of <?= $count ?></strong>
                    &mdash; the <?= $count - $rotationLimit ?> oldest never appear
                <?php else: ?>
                    <strong><?= $count ?> of <?= $rotationLimit ?></strong> slots used
                <?php endif; ?>
            </span>
        <?php endforeach; ?>
    </p>

    <?php if (!$posts): ?>
        <p class="notice">No posts match.</p>
    <?php else: ?>
        <div class="archive-table-wrap">
            <table class="archive-table">
                <thead><tr><th>Date</th><th>Category</th><th>Title</th><th>Status</th><th>Carousel</th><th></th></tr></thead>
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
                            <td data-label="Carousel" class="rotation-cell">
                                <?php foreach (['profile' => 'Profile', 'map' => 'Map'] as $tile => $tileLabel):
                                    $on = !empty($p['show_in_' . $tile]); ?>
                                    <form method="post" action="/admin/content/<?= (int) $p['id'] ?>/rotation/<?= e($tile) ?>">
                                        <?= Csrf::field() ?>
                                        <?= $filterState() ?>
                                        <label class="rotation-toggle">
                                            <?php /* .js-auto-submit saves on click, matching the "Omit admin
                                                     traffic" checkbox on the dashboard — no Save button, and no
                                                     inline onchange for the strict CSP to block. */ ?>
                                            <input type="checkbox" name="on" value="1" class="js-auto-submit"
                                                   <?= $on ? 'checked' : '' ?>
                                                   aria-label="<?= e($tileLabel) ?> carousel &mdash; <?= e($p['title']) ?>">
                                            <span><?= e($tileLabel) ?></span>
                                        </label>
                                    </form>
                                <?php endforeach; ?>
                            </td>
                            <td data-label="" class="crud-actions">
                                <form method="post" action="/admin/content/<?= (int) $p['id'] ?>/toggle-suppress">
                                    <?= Csrf::field() ?>
                                    <?= $filterState() ?>
                                    <button class="btn btn--ghost btn--small" type="submit">
                                        <span><?= $p['is_suppressed'] ? 'Unsuppress' : 'Suppress' ?></span>
                                    </button>
                                </form>
                                <form method="post" action="/admin/content/<?= (int) $p['id'] ?>/delete" data-confirm="Delete this post?">
                                    <?= Csrf::field() ?>
                                    <?= $filterState() ?>
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
