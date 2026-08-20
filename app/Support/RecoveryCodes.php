<?php

declare(strict_types=1);

namespace App\Support;

/**
 * One-time MFA recovery codes. Generated in plaintext, shown to the admin
 * exactly once (see AuthController::recoveryCodes), then only ever stored
 * hashed — same principle as a password, except comparison doesn't need
 * Argon2id here: these are 50 bits of CSPRNG entropy each, not a user-chosen
 * secret, so a fast hash carries no meaningful brute-force risk (the same
 * reasoning GitHub/AWS use for API-key hashing).
 */
final class RecoveryCodes
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // no 0/O/1/I/L
    private const GROUPS = 2;
    private const GROUP_LEN = 5;

    /** @return list<string> plaintext codes, e.g. "7XQK4-M9WYD". */
    public static function generate(int $count = 10): array
    {
        return array_map(static fn () => self::one(), range(1, $count));
    }

    public static function hash(string $code): string
    {
        return hash('sha256', self::normalize($code));
    }

    /** Strips separators/case so "7xqk4 m9wyd" and "7XQK4-M9WYD" hash identically. */
    public static function normalize(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    private static function one(): string
    {
        $groups = [];
        for ($g = 0; $g < self::GROUPS; $g++) {
            $chars = '';
            for ($i = 0; $i < self::GROUP_LEN; $i++) {
                $chars .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $groups[] = $chars;
        }

        return implode('-', $groups);
    }
}

assert(RecoveryCodes::normalize('7xqk4-m9wyd') === RecoveryCodes::normalize('7XQK4 M9WYD'));
