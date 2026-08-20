<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Session;
use App\Models\Admin;

/**
 * Two-stage session state: "pending" (password verified, MFA not yet
 * completed) vs. fully authenticated. Only the latter is ever treated as
 * logged in — a pending admin id in the session grants nothing by itself.
 */
final class Auth
{
    private const PENDING_TTL = 600; // 10 minutes to finish MFA after the password step

    public static function attempt(string $username, string $password): ?array
    {
        $admin = Admin::findByUsername($username);
        if ($admin === null || $admin['status'] !== 'active') {
            return null;
        }

        return password_verify($password, $admin['password_hash']) ? $admin : null;
    }

    public static function startPending(int $adminId): void
    {
        Session::start();
        session_regenerate_id(true);
        unset($_SESSION['admin_id']);
        $_SESSION['admin_pending_id'] = $adminId;
        $_SESSION['admin_pending_started_at'] = time();
    }

    /** @return array<string, mixed>|null */
    public static function pendingAdmin(): ?array
    {
        Session::start();
        $id = $_SESSION['admin_pending_id'] ?? null;
        $started = (int) ($_SESSION['admin_pending_started_at'] ?? 0);

        if ($id === null || (time() - $started) > self::PENDING_TTL) {
            self::clearPending();
            return null;
        }

        return Admin::find((int) $id);
    }

    public static function clearPending(): void
    {
        Session::start();
        unset($_SESSION['admin_pending_id'], $_SESSION['admin_pending_started_at']);
    }

    public static function login(int $adminId): void
    {
        Session::start();
        session_regenerate_id(true);
        self::clearPending();
        $_SESSION['admin_id'] = $adminId;
        $_SESSION['admin_login_at'] = time();
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        Session::start();
        $id = $_SESSION['admin_id'] ?? null;

        return $id === null ? null : Admin::find((int) $id);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function logout(): void
    {
        Session::start();
        $_SESSION = [];
        session_regenerate_id(true);
    }
}
