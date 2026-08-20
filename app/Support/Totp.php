<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Settings;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

/**
 * TOTP (RFC 6238) via robthree/twofactorauth. QR codes render locally with
 * bacon/bacon-qr-code (SVG) — the library also ships providers that phone a
 * secret out to a remote image API (Google/Image-Charts/QRServer/QRicket).
 * Never use those: the otpauth:// URI they'd receive contains the raw TOTP
 * secret in plaintext.
 */
final class Totp
{
    private const ISSUER_FALLBACK = 'Portal';

    /**
     * The issuer is what the admin sees as the account name in their
     * authenticator app, so it must be this install's own site title — a
     * hardcoded constant would label every deployment with the same name.
     */
    private static function issuer(): string
    {
        $title = trim((string) (Settings::site()['page_title'] ?? ''));

        // Colons separate issuer from account in the otpauth:// label.
        return str_replace(':', ' ', $title) ?: self::ISSUER_FALLBACK;
    }

    public static function generateSecret(): string
    {
        return self::instance()->createSecret();
    }

    public static function verify(string $secret, string $code): bool
    {
        // Reject anything that isn't a clean 6-digit code before it reaches
        // the library — empty/garbage input must never happen to verify.
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        return self::instance()->verifyCode($secret, $code);
    }

    public static function qrDataUri(string $username, string $secret): string
    {
        return self::instance()->getQRCodeImageAsDataUri(self::issuer() . ':' . $username, $secret, 220);
    }

    /** Grouped for manual entry when a camera isn't available: "ABCD EFGH ...". */
    public static function manualKey(string $secret): string
    {
        return trim((string) chunk_split($secret, 4, ' '));
    }

    private static function instance(): TwoFactorAuth
    {
        return new TwoFactorAuth(new BaconQrCodeProvider(format: 'svg'), self::issuer());
    }
}
