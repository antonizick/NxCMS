<?php
/**
 * Admin unique-visitors tab — one row per IP, aggregated across all their
 * visits, sortable by visit count so the admin can see who's visited most
 * (or least).
 *
 * @var array<string, mixed> $data
 */
declare(strict_types=1);

$rows = $data['rows'];
$device = $data['device'];
$sort = $data['sort'];
$q = $data['q'];
$page = $data['page'];
$lastPage = $data['lastPage'];
$total = $data['total'];

$qs = static function (array $over) use ($device, $sort, $q, $page): string {
    $params = array_merge(['device' => $device, 'sort' => $sort, 'q' => $q, 'page' => $page], $over);
    $params = array_filter($params, static fn($v) => $v !== '' && $v !== null && $v !== 1 && $v !== 'all');
    return '/admin/visitors/unique' . ($params ? '?' . http_build_query($params) : '');
};
?>
<div class="tile page page--wide">
    <div class="archive-head">
        <h1 class="page-title">Visitors</h1>
    </div>
    <p class="page-sub"><?= (int) $total ?> unique visitor<?= $total === 1 ? '' : 's' ?></p>

    <nav class="subtabs" aria-label="Visitor views">
        <a class="subtab-link" href="/admin/visitors">Visits</a>
        <a class="subtab-link is-active" href="/admin/visitors/unique" aria-current="page">Unique visitors</a>
    </nav>

    <form class="archive-controls" method="get" action="/admin/visitors/unique">
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
            <option value="most" <?= $sort === 'most' ? 'selected' : '' ?>>Most visits</option>
            <option value="least" <?= $sort === 'least' ? 'selected' : '' ?>>Fewest visits</option>
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Most recently seen</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>First seen</option>
        </select>

        <button type="submit" class="btn btn--ghost"><span>Search</span></button>
    </form>

    <?php if (!$rows): ?>
        <p class="notice">No matching visitors.</p>
    <?php else: ?>
        <div class="archive-table-wrap">
            <table class="archive-table">
                <thead><tr><th>IP</th><th>Visits</th><th>Pages</th><th>Devices</th><th>First seen</th><th>Last seen</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td data-label="IP"><?= e((string) $r['ip_address']) ?></td>
                            <td data-label="Visits"><?= (int) $r['visits'] ?></td>
                            <td data-label="Pages"><?= (int) $r['pages'] ?></td>
                            <td data-label="Devices"><?= e(ucwords((string) $r['devices'])) ?></td>
                            <td data-label="First seen"><?= e(fmt_date($r['first_seen'], 'j M Y, g:i a')) ?></td>
                            <td data-label="Last seen"><?= e(fmt_date($r['last_seen'], 'j M Y, g:i a')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($lastPage > 1): ?>
            <nav class="archive-pager" aria-label="Unique visitor pages">
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
