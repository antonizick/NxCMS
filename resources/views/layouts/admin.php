<?php
/**
 * Admin layout — login, MFA enrollment/verify, and (Phase 5) the CMS shell.
 * Deliberately not the public layout: no theme-toggle/copy-link/articles
 * chrome that only makes sense on the marketing site.
 *
 * @var array<string, mixed> $data
 * @var string $content
 */

declare(strict_types=1);

use App\Models\Settings;
use App\Support\Theme;

$theme = Theme::current();
$site = Settings::site();
$pageTitle = (string) ($data['pageTitle'] ?? 'Admin');
$admin = $data['admin'] ?? null;
$wideShell = !empty($data['wideShell']);

$navItems = [
    '/admin' => 'Dashboard',
    '/admin/content' => 'Post content',
    '/admin/projects' => 'Projects',
    '/admin/skills' => 'Toolbox',
    '/admin/profile' => 'Profile',
    '/admin/settings' => 'Site settings',
    '/admin/theme' => 'Theme colors',
    '/admin/contacts' => 'Messages',
    '/admin/visitors' => 'Visitors',
    '/admin/activity' => 'Activity log',
    '/admin/users' => 'Admins',
];
$currentPath = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="dark light">
<link rel="icon" href="/favicon.svg?v=<?= e(App\Support\Monogram::version((string) ($site['initials'] ?? ''), (string) ($site['page_title'] ?? ''), (string) ($site['theme_dark_accent'] ?? '#22d3ee'))) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<?= theme_color_overrides($site) ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<div class="shell shell--admin<?= $wideShell ? ' shell--wide' : '' ?>">
    <header class="site-head">
        <span class="brand">
            <span class="brand-mark" id="adminBrandMark" aria-hidden="true">NA</span>
            <span class="brand-name">Admin</span>
        </span>

        <?php if ($admin): ?>
        <form method="post" action="/admin/logout" class="admin-signout">
            <?= \App\Core\Csrf::field() ?>
            <button class="btn btn--ghost btn--small" type="submit"><span>Sign out</span></button>
        </form>
        <?php endif; ?>
    </header>

    <?php if ($admin): ?>
    <nav class="admin-nav" aria-label="Admin sections">
        <?php foreach ($navItems as $href => $label): ?>
            <a class="admin-nav-link<?= $currentPath === $href ? ' is-active' : '' ?>"
               href="<?= e($href) ?>"<?= $currentPath === $href ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <main id="main" class="<?= $admin ? 'has-nav' : '' ?>"><?= $content ?></main>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
