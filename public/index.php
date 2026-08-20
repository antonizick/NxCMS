<?php

declare(strict_types=1);

// Two supported layouts:
//
//   Single root (the default, and what the installer produces) — public/ sits
//   inside the app root alongside app/, config/, vendor/. Those directories
//   ship an .htaccess denying all access, so this is safe even when the host
//   points the docroot at the app root itself.
//
//   Split root — the panel owns a fixed docroot that cannot be moved or
//   symlinked (common on shared hosts: ~/www/site + ~/apps/site). The two
//   trees have no fixed relationship on disk, so the path must be stated:
//   either the PORTAL_APP_ROOT environment variable, or an app-root.php file
//   next to this one that returns the absolute path.
define('APP_ROOT', (static function (): string {
    if ($fromEnv = getenv('PORTAL_APP_ROOT')) {
        return rtrim($fromEnv, '/');
    }

    if (is_file(__DIR__ . '/app-root.php')) {
        return rtrim((string) require __DIR__ . '/app-root.php', '/');
    }

    // config/config.php is the marker rather than vendor/autoload.php: the
    // installer runs before Composer dependencies are necessarily present.
    if (is_file(dirname(__DIR__) . '/config/config.php')) {
        return dirname(__DIR__);
    }

    http_response_code(500);
    exit('Cannot locate the application root. Set PORTAL_APP_ROOT or create public/app-root.php.');
})());
define('PUBLIC_ROOT', __DIR__);

require APP_ROOT . '/vendor/autoload.php';

use App\Controllers\AdminActivityController;
use App\Controllers\AdminVisitorsController;
use App\Controllers\AdminContactController;
use App\Controllers\AdminContentController;
use App\Controllers\AdminProfileController;
use App\Controllers\AdminProjectController;
use App\Controllers\AdminSiteController;
use App\Controllers\AdminSkillController;
use App\Controllers\AdminThemeController;
use App\Controllers\AdminUserController;
use App\Controllers\ArchiveController;
use App\Controllers\ArticleController;
use App\Controllers\AuthController;
use App\Controllers\ContactController;
use App\Controllers\HomeController;
use App\Controllers\MediaController;
use App\Controllers\SitemapController;
use App\Core\Config;
use App\Core\Database;
use App\Core\Router;
use App\Support\Csp;

$debug = (bool) Config::get('app')['debug'];
error_reporting($debug ? E_ALL : 0);
ini_set('display_errors', $debug ? '1' : '0');

// Nonce-based CSP: set once, before any output, so layouts/public.php can
// read the same nonce back for its one inline script (see Csp.php).
Csp::sendHeader();

$router = new Router();

$router->get('/', [new HomeController(), 'index']);
$router->get('/articles', [new ArchiveController(), 'index']);
$router->get('/article/{id}', [new ArticleController(), 'show']);
$router->get('/contact', [new ContactController(), 'show']);
$router->post('/contact', [new ContactController(), 'submit']);
$sitemap = new SitemapController();
$router->get('/sitemap.xml', [$sitemap, 'index']);
$router->get('/robots.txt', [$sitemap, 'robots']);

// Generated from this install's own `initials` setting rather than served as
// a shipped file — see Monogram. Cached hard because the href carries a
// version derived from the same inputs, so edited initials change the URL.
$router->get('/favicon.svg', function () {
    $site = \App\Models\Settings::site();
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=31536000, immutable');
    echo \App\Support\Monogram::svg(
        (string) ($site['initials'] ?? ''),
        (string) ($site['page_title'] ?? ''),
        (string) ($site['theme_dark_accent'] ?? '#22d3ee')
    );
});

$auth = new AuthController();
$router->get('/admin', [$auth, 'dashboard']);
$router->get('/admin/login', [$auth, 'showLogin']);
$router->post('/admin/login', [$auth, 'login']);
$router->get('/admin/password/change', [$auth, 'showPasswordChange']);
$router->post('/admin/password/change', [$auth, 'changePassword']);
$router->get('/admin/mfa/setup', [$auth, 'showMfaSetup']);
$router->post('/admin/mfa/setup', [$auth, 'mfaSetup']);
$router->get('/admin/mfa/verify', [$auth, 'showMfaVerify']);
$router->post('/admin/mfa/verify', [$auth, 'mfaVerify']);
$router->get('/admin/mfa/recovery-codes', [$auth, 'recoveryCodes']);
$router->post('/admin/logout', [$auth, 'logout']);

$router->get('/media/{filename}', [new MediaController(), 'show']);

$site = new AdminSiteController();
$router->get('/admin/settings', [$site, 'edit']);
$router->post('/admin/settings', [$site, 'update']);
$router->post('/admin/settings/delete-demo', [$site, 'deleteDemo']);

$theme = new AdminThemeController();
$router->get('/admin/theme', [$theme, 'edit']);
$router->post('/admin/theme', [$theme, 'update']);

$profile = new AdminProfileController();
$router->get('/admin/profile', [$profile, 'edit']);
$router->post('/admin/profile', [$profile, 'update']);
$router->post('/admin/profile/social-links', [$profile, 'createSocialLink']);
$router->post('/admin/profile/social-links/{id}', [$profile, 'updateSocialLink']);
$router->post('/admin/profile/social-links/{id}/delete', [$profile, 'deleteSocialLink']);

$projects = new AdminProjectController();
$router->get('/admin/projects', [$projects, 'index']);
$router->get('/admin/projects/new', [$projects, 'new']);
$router->post('/admin/projects', [$projects, 'create']);
$router->get('/admin/projects/{id}/edit', [$projects, 'edit']);
$router->post('/admin/projects/{id}', [$projects, 'update']);
$router->post('/admin/projects/{id}/delete', [$projects, 'delete']);

$skills = new AdminSkillController();
$router->get('/admin/skills', [$skills, 'index']);
$router->get('/admin/skills/new', [$skills, 'new']);
$router->post('/admin/skills', [$skills, 'create']);
$router->get('/admin/skills/{id}/edit', [$skills, 'edit']);
$router->post('/admin/skills/{id}', [$skills, 'update']);
$router->post('/admin/skills/{id}/delete', [$skills, 'delete']);

$content = new AdminContentController();
$router->get('/admin/content', [$content, 'index']);
$router->get('/admin/content/new', [$content, 'new']);
$router->post('/admin/content', [$content, 'create']);
$router->post('/admin/content/preview', [$content, 'preview']);
$router->post('/admin/content/upload-image', [$content, 'uploadImage']);
$router->get('/admin/content/{id}/edit', [$content, 'edit']);
$router->post('/admin/content/{id}', [$content, 'update']);
$router->post('/admin/content/{id}/toggle-suppress', [$content, 'toggleSuppress']);
$router->post('/admin/content/{id}/rotation/{tile}', [$content, 'setRotation']);
$router->post('/admin/content/{id}/delete', [$content, 'delete']);

$users = new AdminUserController();
$router->get('/admin/users', [$users, 'index']);
$router->get('/admin/users/new', [$users, 'new']);
$router->post('/admin/users', [$users, 'create']);
$router->get('/admin/users/{id}/edit', [$users, 'edit']);
$router->post('/admin/users/{id}', [$users, 'update']);
$router->post('/admin/users/{id}/delete', [$users, 'delete']);
$router->post('/admin/users/{id}/reset-password', [$users, 'resetPassword']);
$router->post('/admin/users/{id}/reset-mfa', [$users, 'resetMfa']);

$activity = new AdminActivityController();
$router->get('/admin/activity', [$activity, 'index']);

$visitors = new AdminVisitorsController();
$router->get('/admin/visitors', [$visitors, 'index']);
$router->get('/admin/visitors/unique', [$visitors, 'unique']);

$contacts = new AdminContactController();
$router->get('/admin/contacts', [$contacts, 'index']);
$router->get('/admin/contacts/{id}', [$contacts, 'show']);
$router->post('/admin/contacts/{id}/delete', [$contacts, 'delete']);

$router->get('/health', function () {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'php' => PHP_VERSION]);
});

$router->get('/health/db', function () {
    header('Content-Type: application/json');
    try {
        $stmt = Database::connection()->query('SELECT 1');
        echo json_encode(['status' => 'ok', 'db' => $stmt->fetchColumn()]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error']);
    }
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
