<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\MfaRecoveryCode;

/**
 * Admin account management. The 'nick' row (is_protected) can never be
 * deleted, disabled, or have its own delete/disable attempted here — enforced
 * both in this controller and, redundantly, checked against fresh DB state
 * on every mutating action rather than trusting a value read earlier in the
 * request.
 */
final class AdminUserController extends AdminController
{
    public function index(): void
    {
        $admin = $this->admin();

        View::render('admin/users/index', [
            'pageTitle' => 'Admin accounts — Admin',
            'admin' => $admin,
            'admins' => Admin::all(),
        ], 'layouts/admin');
    }

    public function new(): void
    {
        $admin = $this->admin();

        $this->form($admin, null, null, ['username' => '']);
    }

    public function create(): void
    {
        $admin = $this->admin();

        if (!$this->csrfOk()) {
            $this->form($admin, null, null, $_POST, 'Session expired — please try again.');
            return;
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $error = $this->validateUsername($username, null)
            ?? $this->validatePassword($password);

        if ($error !== null) {
            $this->form($admin, null, null, ['username' => $username], $error);
            return;
        }

        $newId = Admin::create($username, password_hash($password, PASSWORD_ARGON2ID));
        ActivityLog::record((int) $admin['id'], 'admin_created', $username);

        $this->redirect('/admin/users');
    }

    public function edit(string $id): void
    {
        $admin = $this->admin();

        $target = Admin::find((int) $id);
        if ($target === null) {
            $this->redirect('/admin/users');
            return;
        }

        $this->form($admin, (int) $id, $target, ['username' => $target['username']]);
    }

    public function update(string $id): void
    {
        $admin = $this->admin();
        $targetId = (int) $id;
        $target = Admin::find($targetId);
        if ($target === null) {
            $this->redirect('/admin/users');
            return;
        }

        if (!$this->csrfOk()) {
            $this->form($admin, $targetId, $target, $_POST, 'Session expired — please try again.');
            return;
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $status = ($_POST['status'] ?? 'active') === 'disabled' ? 'disabled' : 'active';

        if ($status === 'disabled' && $target['is_protected']) {
            $this->form($admin, $targetId, $target, ['username' => $username], 'The nick account can never be disabled.');
            return;
        }

        $error = $this->validateUsername($username, $targetId);
        if ($error !== null) {
            $this->form($admin, $targetId, $target, ['username' => $username], $error);
            return;
        }

        Admin::updateUsername($targetId, $username);
        Admin::updateStatus($targetId, $status);

        $this->redirect('/admin/users');
    }

    public function delete(string $id): void
    {
        $admin = $this->admin();
        $targetId = (int) $id;

        if ($this->csrfOk() && !Admin::isProtected($targetId) && $targetId !== (int) $admin['id']) {
            Admin::delete($targetId);
            ActivityLog::record((int) $admin['id'], 'admin_deleted', (string) $targetId);
        }

        $this->redirect('/admin/users');
    }

    /** Generates a one-time temp password, shown exactly once on the next edit-page render. */
    public function resetPassword(string $id): void
    {
        $admin = $this->admin();
        $targetId = (int) $id;
        $target = Admin::find($targetId);

        if ($this->csrfOk() && $target !== null) {
            $temp = rtrim(strtr(base64_encode(random_bytes(15)), '+/', '-_'), '=');
            Admin::updatePasswordHash($targetId, password_hash($temp, PASSWORD_ARGON2ID));
            $_SESSION['admin_temp_password_display'] = ['id' => $targetId, 'password' => $temp];
            ActivityLog::record((int) $admin['id'], 'admin_password_reset', $target['username']);
        }

        $this->redirect('/admin/users/' . $targetId . '/edit');
    }

    /** Clears MFA enrollment; the account is forced through setup again on its next login. */
    public function resetMfa(string $id): void
    {
        $admin = $this->admin();
        $targetId = (int) $id;
        $target = Admin::find($targetId);

        if ($this->csrfOk() && $target !== null) {
            Admin::resetMfa($targetId);
            MfaRecoveryCode::replaceAll($targetId, []);
            ActivityLog::record((int) $admin['id'], 'admin_mfa_reset', $target['username']);
        }

        $this->redirect('/admin/users/' . $targetId . '/edit');
    }

    private function validateUsername(string $username, ?int $exceptId): ?string
    {
        if ($username === '' || mb_strlen($username) > 64 || !preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            return 'Username must be 1-64 characters (letters, numbers, ._- only).';
        }
        if (Admin::usernameTaken($username, $exceptId)) {
            return 'That username is already in use.';
        }

        return null;
    }

    private function validatePassword(string $password): ?string
    {
        return strlen($password) < 12 ? 'Password must be at least 12 characters.' : null;
    }

    /**
     * @param array<string, mixed> $admin
     * @param array<string, mixed>|null $target
     * @param array<string, mixed> $fields
     */
    private function form(array $admin, ?int $id, ?array $target, array $fields, ?string $error = null): void
    {
        $tempPassword = null;
        if ($id !== null && isset($_SESSION['admin_temp_password_display']) && $_SESSION['admin_temp_password_display']['id'] === $id) {
            $tempPassword = $_SESSION['admin_temp_password_display']['password'];
            unset($_SESSION['admin_temp_password_display']); // shown exactly once, same rule as MFA recovery codes
        }

        View::render('admin/users/form', [
            'pageTitle' => ($id === null ? 'New admin' : 'Edit admin') . ' — Admin',
            'admin' => $admin,
            'id' => $id,
            'target' => $target,
            'fields' => $fields,
            'error' => $error,
            'tempPassword' => $tempPassword,
        ], 'layouts/admin');
    }
}
