<?php
/**
 * @var array<string, mixed> $data
 */
declare(strict_types=1);

$admin = $data['admin'];
$counts = $data['counts'];
$days = (int) $data['analyticsDays'];
$dailyCounts = $data['dailyCounts'];
$maxDaily = max(1, ...array_values($dailyCounts));
$topPaths = $data['topPaths'];
$maxPathViews = $topPaths ? max(array_column($topPaths, 'views')) : 1;
$topReferrers = $data['topReferrers'];
$device = $data['deviceBreakdown'];
$deviceTotal = max(1, array_sum($device));
$recentActivity = $data['recentActivity'];
?>
<div class="tile page page--wide">
    <h1 class="page-title">Welcome, <?= e($admin['username']) ?></h1>
    <p class="page-sub">
        MFA: <?= $admin['mfa_enabled'] ? 'enabled' : 'not enabled' ?>
        &middot; Last login: <?= $admin['last_login_at'] ? e(fmt_date($admin['last_login_at'], 'j M Y, g:i a')) : 'this is your first login' ?>
    </p>

    <div class="dash-grid">
        <a class="dash-card" href="/admin/content">
            <span class="dash-card-count"><?= (int) $counts['posts'] ?></span>
            <span class="dash-card-label">Published posts</span>
        </a>
        <a class="dash-card" href="/admin/projects">
            <span class="dash-card-count"><?= (int) $counts['projects'] ?></span>
            <span class="dash-card-label">Projects</span>
        </a>
        <a class="dash-card" href="/admin/skills">
            <span class="dash-card-count"><?= (int) $counts['skills'] ?></span>
            <span class="dash-card-label">Toolbox skills</span>
        </a>
        <a class="dash-card" href="/admin/users">
            <span class="dash-card-count"><?= (int) $counts['admins'] ?></span>
            <span class="dash-card-label">Admin accounts</span>
        </a>
        <a class="dash-card" href="/admin/contacts">
            <span class="dash-card-count"><?= (int) $data['unreadContacts'] ?></span>
            <span class="dash-card-label">Unread messages</span>
        </a>
    </div>

    <h2 class="section-title">Engagement &mdash; last <?= $days ?> days</h2>
    <div class="dash-grid dash-grid--stats">
        <div class="dash-card dash-card--static">
            <span class="dash-card-count"><?= (int) $data['totalViews'] ?></span>
            <span class="dash-card-label">Page views</span>
        </div>
        <div class="dash-card dash-card--static">
            <span class="dash-card-count"><?= (int) $data['uniqueVisitors'] ?></span>
            <span class="dash-card-label">Unique visitors</span>
        </div>
    </div>

    <div class="chart-bars" role="img" aria-label="Page views per day, last <?= $days ?> days">
        <?php foreach ($dailyCounts as $day => $count): ?>
            <div class="chart-bar" style="--pct: <?= (int) round($count / $maxDaily * 100) ?>%" title="<?= e(fmt_date($day, 'j M')) ?>: <?= (int) $count ?> views"></div>
        <?php endforeach; ?>
    </div>
    <p class="chart-caption"><?= e(fmt_date(array_key_first($dailyCounts), 'j M')) ?> &ndash; <?= e(fmt_date(array_key_last($dailyCounts), 'j M')) ?></p>

    <div class="dash-panels">
        <div class="dash-panel">
            <h3>Top pages</h3>
            <?php if (!$topPaths): ?>
                <p class="notice">No traffic yet.</p>
            <?php else: ?>
                <ul class="bar-list">
                    <?php foreach ($topPaths as $p): ?>
                        <li>
                            <span class="bar-list-label">
                                <?php if ($p['href'] !== null): ?>
                                    <a href="<?= e($p['href']) ?>" target="_blank" rel="noopener"><?= e($p['label']) ?></a>
                                <?php else: ?>
                                    <?= e($p['label']) ?>
                                <?php endif; ?>
                            </span>
                            <span class="bar-list-track"><span class="bar-list-fill" style="--pct: <?= (int) round($p['views'] / $maxPathViews * 100) ?>%"></span></span>
                            <span class="bar-list-value"><?= (int) $p['views'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="dash-panel">
            <h3>Top referrers</h3>
            <?php if (!$topReferrers): ?>
                <p class="notice">No external referrers yet.</p>
            <?php else: ?>
                <ul class="bar-list">
                    <?php foreach ($topReferrers as $r): ?>
                        <li>
                            <span class="bar-list-label"><?= e(parse_url($r['referrer'], PHP_URL_HOST) ?: $r['referrer']) ?></span>
                            <span class="bar-list-value"><?= (int) $r['views'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="dash-panel">
            <h3>Devices</h3>
            <ul class="bar-list">
                <?php foreach (['desktop' => 'Desktop', 'mobile' => 'Mobile', 'tablet' => 'Tablet', 'bot' => 'Bot/crawler'] as $key => $label): ?>
                    <li>
                        <span class="bar-list-label"><?= e($label) ?></span>
                        <span class="bar-list-track"><span class="bar-list-fill" style="--pct: <?= (int) round($device[$key] / $deviceTotal * 100) ?>%"></span></span>
                        <span class="bar-list-value"><?= (int) $device[$key] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="archive-head" style="margin-top:2rem">
        <h2 class="section-title" style="margin:0">Recent activity</h2>
        <a href="/admin/activity">View full log</a>
    </div>
    <?php if (!$recentActivity): ?>
        <p class="notice">Nothing logged yet.</p>
    <?php else: ?>
        <div class="archive-table-wrap">
            <table class="archive-table">
                <thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Result</th></tr></thead>
                <tbody>
                    <?php foreach ($recentActivity as $r): ?>
                        <tr>
                            <td data-label="When"><?= e(relative_date($r['created_at'])) ?></td>
                            <td data-label="Admin"><?= $r['admin_username'] ? e($r['admin_username']) : '&mdash;' ?></td>
                            <td data-label="Action"><code><?= e($r['action']) ?></code></td>
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
    <?php endif; ?>

    <p class="page-sub" style="margin-top:2rem">
        <a href="/" target="_blank" rel="noopener">View the live portal</a>
        &middot;
        <a href="/admin/mfa/recovery-codes">Recovery codes</a>
    </p>
</div>
