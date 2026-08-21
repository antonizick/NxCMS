<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Models\Admin;
use App\Models\ActivityLog;
use App\Models\ContactSubmission;
use App\Models\ContentPost;
use App\Models\MfaRecoveryCode;
use App\Models\PageView;
use App\Support\Auth;
use App\Support\Crypto;
use App\Support\LoginThrottle;
use App\Support\Migrator;
use App\Support\RecoveryCodes;
use App\Support\Totp;

final class AuthController
{
    // ── Login (stage 1: username + password) ──────────────────────────

    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/admin');
        }

        View::render('admin/login', [
            'pageTitle' => 'Sign in — Admin',
        ], 'layouts/admin');
    }

    public function login(): void
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->renderLogin('Session expired — please try again.');
            return;
        }

        if (LoginThrottle::blocked($ip, $username)) {
            http_response_code(429);
            $this->renderLogin('Too many attempts. Try again in a few minutes.');
            return;
        }

        $admin = Auth::attempt($username, $password);

        if ($admin === null) {
            ActivityLog::record(null, 'login_failed', $username, false);
            $this->renderLogin('Invalid username or password.');
            return;
        }

        Auth::startPending((int) $admin['id']);
        $this->redirectAfterPassword($admin);
    }

    // ── Forced password change (temp password from an admin reset) ─────

    public function showPasswordChange(): void
    {
        $admin = Auth::pendingAdmin();
        if ($admin === null || !$admin['must_change_password']) {
            $this->redirect('/admin/login');
            return;
        }

        View::render('admin/password_change', [
            'pageTitle' => 'Set a new password — Admin',
        ], 'layouts/admin');
    }

    public function changePassword(): void
    {
        $admin = Auth::pendingAdmin();
        if ($admin === null || !$admin['must_change_password']) {
            $this->redirect('/admin/login');
            return;
        }

        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->renderPasswordChange('Session expired — please try again.');
            return;
        }
        if (strlen($password) < 12) {
            $this->renderPasswordChange('Password must be at least 12 characters.');
            return;
        }
        if ($password !== $confirm) {
            $this->renderPasswordChange('Passwords do not match.');
            return;
        }

        $adminId = (int) $admin['id'];
        Admin::completePasswordChange($adminId, password_hash($password, PASSWORD_ARGON2ID));
        ActivityLog::record($adminId, 'admin_password_changed', $admin['username']);

        $this->redirectAfterPassword((array) Admin::find($adminId));
    }

    // ── MFA enrollment (forced on first login / after an admin reset) ──

    public function showMfaSetup(): void
    {
        $admin = Auth::pendingAdmin();
        if ($admin === null) {
            $this->redirect('/admin/login');
            return;
        }
        if ($admin['must_change_password']) {
            $this->redirect('/admin/password/change');
            return;
        }

        $secret = $_SESSION['mfa_setup_secret'] ?? null;
        if (!is_string($secret)) {
            $secret = Totp::generateSecret();
            $_SESSION['mfa_setup_secret'] = $secret;
        }

        View::render('admin/mfa_setup', [
            'pageTitle' => 'Set up two-factor — Admin',
            'qr' => Totp::qrDataUri($admin['username'], $secret),
            'manualKey' => Totp::manualKey($secret),
        ], 'layouts/admin');
    }

    public function mfaSetup(): void
    {
        $admin = Auth::pendingAdmin();
        if ($admin === null) {
            $this->redirect('/admin/login');
            return;
        }
        if ($admin['must_change_password']) {
            $this->redirect('/admin/password/change');
            return;
        }

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $code = trim((string) ($_POST['code'] ?? ''));
        $secret = $_SESSION['mfa_setup_secret'] ?? null;

        if (!Csrf::check($_POST['_csrf'] ?? null) || !is_string($secret)) {
            $this->redirect('/admin/mfa/setup');
            return;
        }

        if (LoginThrottle::blocked($ip, $admin['username'])) {
            http_response_code(429);
            $this->renderMfaSetup($secret, $admin['username'], 'Too many attempts. Try again in a few minutes.');
            return;
        }

        if (!Totp::verify($secret, $code)) {
            ActivityLog::record((int) $admin['id'], 'mfa_failed', $admin['username'], false);
            $this->renderMfaSetup($secret, $admin['username'], 'That code didn\'t match — check the time on your device and try again.');
            return;
        }

        $adminId = (int) $admin['id'];
        Admin::setMfaSecret($adminId, Crypto::encrypt($secret));
        Admin::activateMfa($adminId);

        $codes = RecoveryCodes::generate(10);
        MfaRecoveryCode::replaceAll($adminId, $codes);

        unset($_SESSION['mfa_setup_secret']);
        $_SESSION['mfa_recovery_codes_display'] = $codes;

        Auth::login($adminId);
        Admin::touchLastLogin($adminId);
        ActivityLog::record($adminId, 'login_success', 'mfa_enrolled');
        $this->applyPendingMigrations($adminId);

        $this->redirect('/admin/mfa/recovery-codes');
    }

    public function recoveryCodes(): void
    {
        $admin = Auth::user();
        if ($admin === null) {
            $this->redirect('/admin/login');
            return;
        }

        $codes = $_SESSION['mfa_recovery_codes_display'] ?? null;
        unset($_SESSION['mfa_recovery_codes_display']); // shown exactly once, ever

        View::render('admin/mfa_recovery_codes', [
            'pageTitle' => 'Recovery codes — Admin',
            'admin' => $admin,
            'codes' => is_array($codes) ? $codes : null,
        ], 'layouts/admin');
    }

    // ── MFA verify (returning login, already enrolled) ─────────────────

    public function showMfaVerify(): void
    {
        $admin = Auth::pendingAdmin();
        if ($admin !== null && $admin['must_change_password']) {
            $this->redirect('/admin/password/change');
            return;
        }
        if ($admin === null || !$admin['mfa_enabled'] || $admin['force_mfa_setup']) {
            $this->redirect('/admin/login');
            return;
        }

        View::render('admin/mfa_verify', [
            'pageTitle' => 'Verify — Admin',
        ], 'layouts/admin');
    }

    public function mfaVerify(): void
    {
        $admin = Auth::pendingAdmin();
        if ($admin !== null && $admin['must_change_password']) {
            $this->redirect('/admin/password/change');
            return;
        }
        if ($admin === null || !$admin['mfa_enabled'] || $admin['force_mfa_setup']) {
            $this->redirect('/admin/login');
            return;
        }

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $input = trim((string) ($_POST['code'] ?? ''));

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->renderMfaVerify('Session expired — please try again.');
            return;
        }

        if (LoginThrottle::blocked($ip, $admin['username'])) {
            http_response_code(429);
            $this->renderMfaVerify('Too many attempts. Try again in a few minutes.');
            return;
        }

        $adminId = (int) $admin['id'];
        if (preg_match('/^\d{6}$/', $input) === 1) {
            $secretPlain = $admin['mfa_secret'] !== null ? Crypto::decrypt((string) $admin['mfa_secret']) : null;
            $ok = $secretPlain !== null && Totp::verify($secretPlain, $input);
        } else {
            $ok = MfaRecoveryCode::consume($adminId, $input);
        }

        if (!$ok) {
            ActivityLog::record($adminId, 'mfa_failed', $admin['username'], false);
            $this->renderMfaVerify('Invalid code.');
            return;
        }

        Auth::login($adminId);
        Admin::touchLastLogin($adminId);
        ActivityLog::record($adminId, 'login_success', null);
        $this->applyPendingMigrations($adminId);

        $this->redirect('/admin');
    }

    // ── Logout / dashboard stub ─────────────────────────────────────────

    public function logout(): void
    {
        if (Csrf::check($_POST['_csrf'] ?? null)) {
            Auth::logout();
        }
        $this->redirect('/admin/login');
    }

    public function dashboard(): void
    {
        $admin = Auth::user();
        if ($admin === null) {
            $this->redirect('/admin/login');
            return;
        }

        $db = Database::connection();
        $days = 30;
        $hideAdmin = ($_GET['hide_admin_set'] ?? '') === '1'
            ? ($_GET['hide_admin'] ?? '') === '1'
            : true;

        View::render('admin/dashboard', [
            'pageTitle' => 'Dashboard — Admin',
            'admin' => $admin,
            'counts' => [
                'posts' => (int) $db->query('SELECT COUNT(*) FROM content_posts WHERE is_suppressed = 0')->fetchColumn(),
                'projects' => (int) $db->query('SELECT COUNT(*) FROM projects')->fetchColumn(),
                'skills' => (int) $db->query('SELECT COUNT(*) FROM skills')->fetchColumn(),
                'admins' => (int) $db->query('SELECT COUNT(*) FROM admins')->fetchColumn(),
            ],
            'analyticsDays' => $days,
            'hideAdmin' => $hideAdmin,
            'totalViews' => PageView::totalViews($days, $hideAdmin),
            'uniqueVisitors' => PageView::uniqueVisitors($days, $hideAdmin),
            'dailyCounts' => PageView::dailyCounts($days, $hideAdmin),
            'topPaths' => $this->topPathsWithArticleLinks(PageView::topPaths($days, 8, $hideAdmin)),
            'topReferrers' => PageView::topReferrers($days, 8, $hideAdmin),
            'deviceBreakdown' => PageView::deviceBreakdown($days, $hideAdmin),
            'unreadContacts' => ContactSubmission::unreadCount(),
            'recentActivity' => ActivityLog::searchAdmin('all', 'all', '', 'newest', 8, 0),
        ], 'layouts/admin');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Top-pages rows for an /article/{id} path get the post's title as their
     * label and a link out to the public page, in place of the bare path —
     * admin pages can be on a different origin from the public site (e.g. a
     * dedicated subdomain), so the link is built from app.url rather than a
     * relative href. Any other path (/, /articles, a 404'd path, …) passes
     * through unchanged with no link.
     *
     * @param list<array{path: string, views: int}> $paths
     * @return list<array{path: string, views: int, label: string, href: ?string}>
     */
    private function topPathsWithArticleLinks(array $paths): array
    {
        $appUrl = rtrim((string) (Config::get('app')['url'] ?? ''), '/');

        foreach ($paths as &$p) {
            $p['label'] = $p['path'];
            $p['href'] = null;

            if (preg_match('#^/article/(\d+)$#', $p['path'], $m) === 1) {
                $post = ContentPost::findAny((int) $m[1]);
                if ($post !== null) {
                    $p['label'] = $post['title'];
                    $p['href'] = $appUrl . post_url($post);
                }
            }
        }

        return $paths;
    }

    /**
     * A no-CLI shared-hosting upgrade is "replace the files, reload the
     * site" — this is what makes that true. Runs on every completed login
     * rather than blocking it: a migration failure shouldn't lock the admin
     * out of an otherwise-working account.
     */
    private function applyPendingMigrations(int $adminId): void
    {
        try {
            $applied = Migrator::runPending();
            if ($applied !== []) {
                ActivityLog::record($adminId, 'migrations_applied', implode(',', $applied));
            }
        } catch (\Throwable $e) {
            error_log('Migrator::runPending failed: ' . $e->getMessage());
        }
    }

    /**
     * Where to send an admin once their password is no longer in question —
     * right after login, and again after a forced password change completes.
     * must_change_password wins over everything else: no point starting MFA
     * enrollment against an account that's about to log itself out.
     */
    private function redirectAfterPassword(array $admin): void
    {
        if ($admin['must_change_password']) {
            $this->redirect('/admin/password/change');
        } elseif ($admin['force_mfa_setup']) {
            $this->redirect('/admin/mfa/setup');
        } elseif ($admin['mfa_enabled']) {
            $this->redirect('/admin/mfa/verify');
        } else {
            // Defensive fallback only — admins.force_mfa_setup defaults to 1,
            // so a non-enrolled account should never reach this branch.
            $this->redirect('/admin/mfa/setup');
        }
    }

    private function renderPasswordChange(string $error): void
    {
        View::render('admin/password_change', [
            'pageTitle' => 'Set a new password — Admin',
            'error' => $error,
        ], 'layouts/admin');
    }

    private function renderLogin(string $error): void
    {
        View::render('admin/login', [
            'pageTitle' => 'Sign in — Admin',
            'error' => $error,
        ], 'layouts/admin');
    }

    private function renderMfaSetup(string $secret, string $username, string $error): void
    {
        View::render('admin/mfa_setup', [
            'pageTitle' => 'Set up two-factor — Admin',
            'qr' => Totp::qrDataUri($username, $secret),
            'manualKey' => Totp::manualKey($secret),
            'error' => $error,
        ], 'layouts/admin');
    }

    private function renderMfaVerify(string $error): void
    {
        View::render('admin/mfa_verify', [
            'pageTitle' => 'Verify — Admin',
            'error' => $error,
        ], 'layouts/admin');
    }

    private function redirect(string $path): void
    {
        header('Location: ' . $path, true, 303);
    }
}
