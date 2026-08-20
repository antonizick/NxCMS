<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Config;

/**
 * AES-256-GCM at-rest encryption for admins.mfa_secret. The only column that
 * needs this — everything else in the schema is either public or already
 * one-way hashed (passwords, recovery codes).
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const NONCE_LEN = 12;
    private const TAG_LEN = 16;

    public static function encrypt(string $plaintext): string
    {
        $key = self::key();
        $nonce = random_bytes(self::NONCE_LEN);
        $tag = '';

        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, '', self::TAG_LEN);
        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed.');
        }

        return base64_encode($nonce . $tag . $ciphertext);
    }

    /** Returns null on a tampered payload or key mismatch — callers must treat that as "no secret". */
    public static function decrypt(string $payload): ?string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < self::NONCE_LEN + self::TAG_LEN) {
            return null;
        }

        $nonce = substr($raw, 0, self::NONCE_LEN);
        $tag = substr($raw, self::NONCE_LEN, self::TAG_LEN);
        $ciphertext = substr($raw, self::NONCE_LEN + self::TAG_LEN);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $nonce, $tag, '');

        return $plaintext === false ? null : $plaintext;
    }

    private static function key(): string
    {
        $b64 = Config::get('security')['app_key'] ?? '';
        $key = base64_decode((string) $b64, true);

        if ($key === false || strlen($key) !== 32) {
            http_response_code(500);
            exit('Configuration missing.');
        }

        return $key;
    }
}

assert(Crypto::decrypt('not-valid-base64-payload-!!') === null);
