<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Admin
{
    /** @return array<string, mixed>|null */
    public static function findByUsername(string $username): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM admins WHERE username = :u');
        $stmt->execute([':u' => $username]);

        return $stmt->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM admins WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public static function touchLastLogin(int $id): void
    {
        Database::connection()
            ->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = :id')
            ->execute([':id' => $id]);
    }

    public static function setMfaSecret(int $id, string $encryptedSecret): void
    {
        Database::connection()
            ->prepare('UPDATE admins SET mfa_secret = :s WHERE id = :id')
            ->execute([':s' => $encryptedSecret, ':id' => $id]);
    }

    /** Enrollment (or re-enrollment) confirmed: MFA on, forced-setup flag cleared. */
    public static function activateMfa(int $id): void
    {
        Database::connection()
            ->prepare('UPDATE admins SET mfa_enabled = 1, force_mfa_setup = 0 WHERE id = :id')
            ->execute([':id' => $id]);
    }

    /** @return list<array<string, mixed>> everyone except password_hash/mfa_secret — this feeds the admin list screen, not auth. */
    public static function all(): array
    {
        return Database::connection()->query(
            'SELECT id, username, is_protected, status, mfa_enabled, force_mfa_setup, last_login_at, created_at
               FROM admins ORDER BY is_protected DESC, username'
        )->fetchAll();
    }

    public static function usernameTaken(string $username, ?int $exceptId = null): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM admins WHERE username = :u AND id != :except'
        );
        $stmt->execute([':u' => $username, ':except' => $exceptId ?? 0]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function create(string $username, string $passwordHash): int
    {
        // force_mfa_setup=1: every new admin enrolls their own MFA on first login,
        // same as the first admin — nobody else ever sees the secret.
        Database::connection()->prepare(
            'INSERT INTO admins (username, password_hash, force_mfa_setup, mfa_enabled, status)
             VALUES (:u, :h, 1, 0, \'active\')'
        )->execute([':u' => $username, ':h' => $passwordHash]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function updateUsername(int $id, string $username): void
    {
        Database::connection()
            ->prepare('UPDATE admins SET username = :u WHERE id = :id')
            ->execute([':u' => $username, ':id' => $id]);
    }

    public static function updatePasswordHash(int $id, string $hash): void
    {
        Database::connection()
            ->prepare('UPDATE admins SET password_hash = :h WHERE id = :id')
            ->execute([':h' => $hash, ':id' => $id]);
    }

    /** @param 'active'|'disabled' $status */
    public static function updateStatus(int $id, string $status): void
    {
        Database::connection()
            ->prepare('UPDATE admins SET status = :s WHERE id = :id')
            ->execute([':s' => $status, ':id' => $id]);
    }

    /** Clears enrollment; the account is forced through setup again on next login. */
    public static function resetMfa(int $id): void
    {
        Database::connection()
            ->prepare('UPDATE admins SET mfa_secret = NULL, mfa_enabled = 0, force_mfa_setup = 1 WHERE id = :id')
            ->execute([':id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::connection()->prepare('DELETE FROM admins WHERE id = :id')->execute([':id' => $id]);
    }

    public static function isProtected(int $id): bool
    {
        $admin = self::find($id);

        return $admin !== null && (bool) $admin['is_protected'];
    }
}
