<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Support\RecoveryCodes;

final class MfaRecoveryCode
{
    /**
     * Replaces the full set on (re-)enrollment — old codes from a previous
     * enrollment must stop working the moment a new set is issued.
     *
     * @param list<string> $plainCodes
     */
    public static function replaceAll(int $adminId, array $plainCodes): void
    {
        $db = Database::connection();
        $db->prepare('DELETE FROM mfa_recovery_codes WHERE admin_id = :id')->execute([':id' => $adminId]);

        $stmt = $db->prepare('INSERT INTO mfa_recovery_codes (admin_id, code_hash) VALUES (:id, :hash)');
        foreach ($plainCodes as $code) {
            $stmt->execute([':id' => $adminId, ':hash' => RecoveryCodes::hash($code)]);
        }
    }

    /** Consumes a matching unused code. One-time use — a leaked printout stops working after first use. */
    public static function consume(int $adminId, string $code): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id FROM mfa_recovery_codes WHERE admin_id = :id AND code_hash = :hash AND used_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $adminId, ':hash' => RecoveryCodes::hash($code)]);
        $row = $stmt->fetch();

        if ($row === false) {
            return false;
        }

        $db->prepare('UPDATE mfa_recovery_codes SET used_at = NOW() WHERE id = :id')->execute([':id' => $row['id']]);

        return true;
    }
}
