<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Config;

/**
 * Self-hosted proof-of-work CAPTCHA (Altcha-style challenge/response), gated
 * on the contact form only once the site has taken 5+ submissions in a day
 * (spec). No third-party widget/CDN/npm package — the challenge is signed
 * with the app's existing AES key (see Crypto) so it's stateless: nothing is
 * written to the database to issue or verify one, and the client solves it
 * with the browser's native crypto.subtle.digest (no bundled JS sha256 lib).
 */
final class Captcha
{
    private const MAX_NUMBER = 60000;
    private const TTL_SECONDS = 600;

    /** @return array{salt: string, challenge: string, maxnumber: int, expires: int, signature: string} */
    public static function challenge(): array
    {
        $salt = bin2hex(random_bytes(12));
        $number = random_int(1, self::MAX_NUMBER);
        $challenge = hash('sha256', $salt . $number);
        $expires = time() + self::TTL_SECONDS;

        return [
            'salt' => $salt,
            'challenge' => $challenge,
            'maxnumber' => self::MAX_NUMBER,
            'expires' => $expires,
            'signature' => self::sign($salt, $challenge, self::MAX_NUMBER, $expires),
        ];
    }

    /** @param array<string, mixed> $payload decoded client-submitted challenge JSON */
    public static function verify(array $payload, string $number): bool
    {
        $salt = (string) ($payload['salt'] ?? '');
        $challenge = (string) ($payload['challenge'] ?? '');
        $maxnumber = (int) ($payload['maxnumber'] ?? 0);
        $expires = (int) ($payload['expires'] ?? 0);
        $signature = (string) ($payload['signature'] ?? '');

        if ($salt === '' || $challenge === '' || $signature === '' || !ctype_digit($number)) {
            return false;
        }
        if (!hash_equals(self::sign($salt, $challenge, $maxnumber, $expires), $signature)) {
            return false; // tampered or forged payload
        }
        if ($expires < time()) {
            return false; // stale — solved too slowly, or replayed
        }

        $n = (int) $number;
        if ($n < 0 || $n > $maxnumber) {
            return false;
        }

        return hash_equals($challenge, hash('sha256', $salt . $n));
    }

    private static function sign(string $salt, string $challenge, int $maxnumber, int $expires): string
    {
        $key = base64_decode((string) Config::get('security')['app_key'], true);
        if ($key === false || $key === '') {
            http_response_code(500);
            exit('Configuration missing.');
        }

        return hash_hmac('sha256', $salt . '|' . $challenge . '|' . $maxnumber . '|' . $expires, $key);
    }
}
