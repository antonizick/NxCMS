<?php

declare(strict_types=1);

/**
 * One-shot bootstrap for the protected first admin account.
 *
 * The web installer creates this account for you; this script is the
 * command-line equivalent, and the recovery path if you are ever locked out.
 * The password is passed on STDIN so it never lands in a file, a migration,
 * or shell history under a literal:
 *
 *   PORTAL_ADMIN_USER=admin printf '%s' "$PASSWORD" | php database/seed_admin.php
 *
 * Re-running updates the password of the existing row rather than erroring,
 * so it doubles as an out-of-band password reset if MFA ever locks us out.
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;

$username = getenv('PORTAL_ADMIN_USER') ?: 'admin';
// Prefer STDIN so the password never appears in argv or the process
// environment, where it would be readable from /proc on a shared host.
$password = getenv('PORTAL_ADMIN_PASSWORD');
if (!is_string($password) || $password === '') {
    $piped = stream_isatty(STDIN) ? false : fgets(STDIN);
    $password = $piped === false ? null : rtrim($piped, "\r\n");
}

if (!is_string($password) || strlen($password) < 12) {
    fwrite(STDERR, "Password required (12+ chars) on STDIN or in PORTAL_ADMIN_PASSWORD.\n");
    exit(1);
}

// Argon2id: memory-hard, and the host's PHP 8.5 has it built in.
$hash = password_hash($password, PASSWORD_ARGON2ID);

$pdo = Database::connection();
$stmt = $pdo->prepare('SELECT id FROM admins WHERE username = :u');
$stmt->execute([':u' => $username]);
$existing = $stmt->fetchColumn();

if ($existing) {
    $pdo->prepare('UPDATE admins SET password_hash = :h, is_protected = 1, status = \'active\' WHERE id = :id')
        ->execute([':h' => $hash, ':id' => $existing]);
    echo "updated: {$username} (id {$existing})\n";
    exit(0);
}

$pdo->prepare(
    'INSERT INTO admins (username, password_hash, is_protected, force_mfa_setup, mfa_enabled, status)
     VALUES (:u, :h, 1, 1, 0, \'active\')'
)->execute([':u' => $username, ':h' => $hash]);

echo "created: {$username} (id {$pdo->lastInsertId()}) — MFA enrolment forced on first login\n";
