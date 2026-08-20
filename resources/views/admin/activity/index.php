<?php
/**
 * Admin activity log viewer — every login attempt (success/failed) and
 * every administrative mutation, searchable/filterable/sortable/paginated.
 *
 * @var array<string, mixed> $data
 */
declare(strict_types=1);

$rows = $data['rows'];
$actions = $data['actions'];
$action = $data['action'];
$outcome = $data['outcome'];
$sort = $data['sort'];
$q = $data['q'];
$page = $data['page'];
$lastPage = $data['lastPage'];
$total = $data['total'];

$qs = static function (array $over) use ($action, $outcome, $sort, $q, $page): string {
    $params = array_merge(['action' => $action, 'outcome' => $outcome, 'sort' => $sort, 'q' => $q, 'page' => $page], $over);
    $params = array_filter($params, static fn($v) => $v !== '' && $v !== null && $v !== 1 && $v !== 'all');
    return '/admin/activity' . ($params ? '?' . http_build_query($params) : '');
};
?>
<div class="tile page page--wide">
    <div class="archive-head">
        <h1 class="page-title">Activity log</h1>
    </div>
    <p class="page-sub"><?= (int) $total ?> event<?= $total === 1 ? '' : 's' ?></p>

    <form class="archive-controls" method="get" action="/admin/activity">
        <div class="search-field">
            <?= icon('search', 'icon') ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search detail or IP&hellip;" maxlength="120">
        </div>

        <select name="action" class="archive-select">
            <option value="all" <?= $action === 'all' ? 'selected' : '' ?>>All actions</option>
            <?php foreach ($actions as $a): ?>
                <option value="<?= e($a) ?>" <?= $a === $action ? 'selected' : '' ?>><?= e($a) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="outcome" class="archive-select">
            <option value="all" <?= $outcome === 'all' ? 'selected' : '' ?>>Success + failed</option>
            <option value="success" <?= $outcome === 'success' ? 'selected' : '' ?>>Success only</option>
            <option value="failed" <?= $outcome === 'failed' ? 'selected' : '' ?>>Failed only</option>
        </select>

        <select name="sort" class="archive-select">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
        </select>

        <button type="submit" class="btn btn--ghost"><span>Search</span></button>
    </form>

    <?php if (!$rows): ?>
        <p class="notice">No matching events.</p>
    <?php else: ?>
        <div class="archive-table-wrap">
            <table class="archive-table">
                <thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Detail</th><th>IP</th><th>Result</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td data-label="When"><?= e(fmt_date($r['created_at'], 'j M Y, g:i:s a')) ?></td>
                            <td data-label="Admin"><?= $r['admin_username'] ? e($r['admin_username']) : '&mdash;' ?></td>
                            <td data-label="Action"><code><?= e($r['action']) ?></code></td>
                            <td data-label="Detail"><?= $r['detail'] !== null ? e($r['detail']) : '&mdash;' ?></td>
                            <td data-label="IP"><?= e($r['ip_address']) ?></td>
                            <td data-label="Result">
                                <span class="badge <?= $r['success'] ? 'badge--ok' : 'badge--off' ?>">
                                    <?= $r['success'] ? 'Success' : 'Failed' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($lastPage > 1): ?>
            <nav class="archive-pager" aria-label="Activity pages">
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
