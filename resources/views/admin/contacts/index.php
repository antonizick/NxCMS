<?php
/**
 * Admin review screen for contact form submissions.
 *
 * @var array<string, mixed> $data
 */
declare(strict_types=1);

$submissions = $data['submissions'];
$filter = $data['filter'];
$sort = $data['sort'];
$q = $data['q'];
$page = $data['page'];
$lastPage = $data['lastPage'];
$total = $data['total'];
$unread = $data['unread'];

$qs = static function (array $over) use ($filter, $sort, $q, $page): string {
    $params = array_merge(['filter' => $filter, 'sort' => $sort, 'q' => $q, 'page' => $page], $over);
    $params = array_filter($params, static fn($v) => $v !== '' && $v !== null && $v !== 1 && $v !== 'all');
    return '/admin/contacts' . ($params ? '?' . http_build_query($params) : '');
};
?>
<div class="tile page page--wide">
    <div class="archive-head">
        <h1 class="page-title">Messages</h1>
    </div>
    <p class="page-sub">
        <?= (int) $total ?> message<?= $total === 1 ? '' : 's' ?>
        <?php if ($unread > 0): ?> &middot; <span class="badge badge--protected"><?= (int) $unread ?> unread</span><?php endif; ?>
    </p>

    <form class="archive-controls" method="get" action="/admin/contacts">
        <div class="search-field">
            <?= icon('search', 'icon') ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email, message&hellip;" maxlength="120">
        </div>

        <select name="filter" class="archive-select">
            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All</option>
            <option value="unread" <?= $filter === 'unread' ? 'selected' : '' ?>>Unread</option>
            <option value="read" <?= $filter === 'read' ? 'selected' : '' ?>>Read</option>
        </select>

        <select name="sort" class="archive-select">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
        </select>

        <button type="submit" class="btn btn--ghost"><span>Search</span></button>
    </form>

    <?php if (!$submissions): ?>
        <p class="notice">No messages match.</p>
    <?php else: ?>
        <div class="archive-table-wrap">
            <table class="archive-table">
                <thead><tr><th>Date</th><th>From</th><th>Message</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($submissions as $s): ?>
                        <tr class="<?= $s['is_read'] ? '' : 'is-unread-row' ?>">
                            <td data-label="Date"><?= e(fmt_date($s['created_at'], 'j M Y, g:i a')) ?></td>
                            <td data-label="From">
                                <a class="archive-title" href="/admin/contacts/<?= (int) $s['id'] ?>"><?= e($s['name']) ?></a><br>
                                <span style="color:var(--text-mute);font-size:13px"><?= e($s['email']) ?></span>
                            </td>
                            <td data-label="Message" style="color:var(--text-mute)"><?= e(mb_strimwidth($s['message'], 0, 90, '…')) ?></td>
                            <td data-label="Status">
                                <span class="badge <?= $s['is_read'] ? '' : 'badge--protected' ?>">
                                    <?= $s['is_read'] ? 'Read' : 'Unread' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($lastPage > 1): ?>
            <nav class="archive-pager" aria-label="Message pages">
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
