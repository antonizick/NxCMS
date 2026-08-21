<?php
/**
 * Admin visitor log — one row per public page view, with IP address and
 * (when the path is an article) which article was accessed.
 *
 * @var array<string, mixed> $data
 */
declare(strict_types=1);

use App\Models\ContentPost;

$rows = $data['rows'];
$device = $data['device'];
$sort = $data['sort'];
$q = $data['q'];
$page = $data['page'];
$lastPage = $data['lastPage'];
$total = $data['total'];
$hideAdmin = (bool) $data['hideAdmin'];

$qs = static function (array $over) use ($device, $sort, $q, $page, $hideAdmin): string {
    $params = array_merge(['device' => $device, 'sort' => $sort, 'q' => $q, 'page' => $page, 'hide_admin' => $hideAdmin ? '1' : '0', 'hide_admin_set' => '1'], $over);
    $params = array_filter($params, static fn($v) => $v !== '' && $v !== null && $v !== 1 && $v !== 'all');
    return '/admin/visitors' . ($params ? '?' . http_build_query($params) : '');
};
?>
<div class="tile page page--wide">
    <div class="archive-head">
        <h1 class="page-title">Visitors</h1>
        <form method="get" action="/admin/visitors" class="checkbox" title="Excludes traffic from IPs that have completed an admin login">
            <?php foreach (['device' => $device, 'sort' => $sort, 'q' => $q] as $k => $v): ?>
                <?php if ($v !== '' && $v !== 'all'): ?>
                    <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <input type="hidden" name="hide_admin_set" value="1">
            <input type="checkbox" name="hide_admin" value="1" id="hide_admin" class="js-auto-submit" <?= $hideAdmin ? 'checked' : '' ?>>
            <label for="hide_admin">Omit admin traffic</label>
        </form>
    </div>
    <p class="page-sub"><?= (int) $total ?> visit<?= $total === 1 ? '' : 's' ?></p>

    <nav class="subtabs" aria-label="Visitor views">
        <a class="subtab-link is-active" href="/admin/visitors" aria-current="page">Visits</a>
        <a class="subtab-link" href="/admin/visitors/unique?<?= e(http_build_query(['hide_admin' => $hideAdmin ? '1' : '0', 'hide_admin_set' => '1'])) ?>">Unique visitors</a>
    </nav>

    <form class="archive-controls" method="get" action="/admin/visitors">
        <input type="hidden" name="hide_admin" value="<?= $hideAdmin ? '1' : '0' ?>">
        <input type="hidden" name="hide_admin_set" value="1">
        <div class="search-field">
            <?= icon('search', 'icon') ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search IP or page&hellip;" maxlength="120">
        </div>

        <select name="device" class="archive-select">
            <option value="all" <?= $device === 'all' ? 'selected' : '' ?>>All devices</option>
            <option value="desktop" <?= $device === 'desktop' ? 'selected' : '' ?>>Desktop</option>
            <option value="mobile" <?= $device === 'mobile' ? 'selected' : '' ?>>Mobile</option>
            <option value="tablet" <?= $device === 'tablet' ? 'selected' : '' ?>>Tablet</option>
            <option value="bot" <?= $device === 'bot' ? 'selected' : '' ?>>Bot</option>
        </select>

        <select name="sort" class="archive-select">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
        </select>

        <button type="submit" class="btn btn--ghost"><span>Search</span></button>
    </form>

    <?php if (!$rows): ?>
        <p class="notice">No matching visits.</p>
    <?php else: ?>
        <div class="archive-table-wrap">
            <table class="archive-table">
                <thead><tr><th>When</th><th>IP</th><th>Page / article</th><th>Device</th><th>Referrer</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td data-label="When"><?= e(fmt_date($r['created_at'], 'j M Y, g:i:s a')) ?></td>
                            <td data-label="IP"><?= e($r['ip_address'] ?? '&mdash;') ?></td>
                            <td data-label="Page / article">
                                <?php if ($r['article_title'] !== null): ?>
                                    <span class="badge badge--ok"><?= e(ContentPost::LABELS[$r['article_category']] ?? '') ?></span>
                                    <?= e($r['article_title']) ?>
                                <?php else: ?>
                                    <code><?= e($r['path']) ?></code>
                                <?php endif; ?>
                            </td>
                            <td data-label="Device"><?= e(ucfirst((string) $r['device_type'])) ?></td>
                            <td data-label="Referrer"><?= $r['referrer'] !== null ? e($r['referrer']) : '&mdash;' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($lastPage > 1): ?>
            <nav class="archive-pager" aria-label="Visitor pages">
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
