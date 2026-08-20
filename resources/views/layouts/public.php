<?php
/**
 * Public layout. $data and $content are provided by View::capture().
 *
 * @var array<string, mixed> $data
 * @var string $content
 */

declare(strict_types=1);

use App\Models\Settings;
use App\Support\Csp;
use App\Support\Theme;

$site = $data['site'] ?? Settings::site();
$theme = Theme::current();
$appUrl = rtrim(\App\Core\Config::get('app')['url'] ?? '', '/');
$canonical = $appUrl . ($_SERVER['REQUEST_URI'] ?? '/');
$canonical = strtok($canonical, '?');
$pageTitle = (string) ($data['pageTitle'] ?? ($site['page_title'] ?? 'Portal'));
$description = (string) ($data['metaDescription'] ?? '');
$ogImage = $data['ogImage'] ?? ($data['profile']['headshot_url'] ?? null);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<?php if ($description !== ''): ?>
<meta name="description" content="<?= e($description) ?>">
<?php endif; ?>
<link rel="canonical" href="<?= e($canonical) ?>">
<meta name="color-scheme" content="dark light">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($site['display_name'] ?? '') ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<?php if ($ogImage): ?>
<meta property="og:image" content="<?= e($appUrl . $ogImage) ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="/favicon.svg?v=<?= e(App\Support\Monogram::version((string) ($site['initials'] ?? ''), (string) ($site['page_title'] ?? ''), (string) ($site['theme_dark_accent'] ?? '#22d3ee'))) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<?= theme_color_overrides($site) ?>
<script type="application/ld+json" nonce="<?= e(Csp::nonce()) ?>"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Person',
    'name' => $site['display_name'] ?? '',
    'url' => $appUrl,
    'description' => $description,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<div class="ambient" aria-hidden="true">
    <span class="ambient-orb ambient-orb--1"></span>
    <span class="ambient-orb ambient-orb--2"></span>
    <span class="ambient-orb ambient-orb--3"></span>
    <span class="ambient-scan"></span>
</div>

<div class="shell">
    <header class="site-head">
        <a class="brand" href="/">
            <span class="brand-mark" aria-hidden="true"><?= e($site['initials'] ?? 'NA') ?></span>
            <span class="brand-name"><?= e($site['page_title'] ?? '') ?></span>
        </a>

        <div class="head-actions">
            <a class="pill" href="/articles">
                <?= icon('layers', 'icon') ?>
                <span class="pill-text">Articles</span>
            </a>

            <?php if (($site['copy_link_url'] ?? '') !== ''): ?>
            <button type="button" class="pill" id="copy-link"
                    data-copy="<?= e($site['copy_link_url']) ?>"
                    data-label="<?= e($site['copy_link_text'] ?: 'Copy link') ?>">
                <?= icon('link', 'icon') ?>
                <span class="pill-text"><?= e($site['copy_link_text'] ?: 'Copy link') ?></span>
            </button>
            <?php endif; ?>

            <button type="button" class="icon-btn" id="theme-toggle"
                    aria-label="Switch to <?= $theme === 'dark' ? 'light' : 'dark' ?> theme"
                    aria-pressed="<?= $theme === 'light' ? 'true' : 'false' ?>">
                <span class="theme-icon theme-icon--dark"><?= icon('moon', 'icon') ?></span>
                <span class="theme-icon theme-icon--light"><?= icon('sun', 'icon') ?></span>
            </button>
        </div>
    </header>

    <main id="main"><?= $content ?></main>

    <footer class="site-foot">
        <p class="copyright">
            &copy; <?= e($site['copyright_year'] ?? '') ?> <span><?= e($site['copyright_text'] ?? '') ?></span>
        </p>
        <p class="footnote"><?= e($site['footer_text'] ?? '') ?></p>
    </footer>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
