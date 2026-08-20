<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Config;

/**
 * Admin image uploads (headshot/logo/content images).
 *
 * Resized + re-encoded with GD rather than shelling out to the host's
 * ImageMagick CLI — GD is a PHP extension already loaded (verified on the
 * host), so this needs no exec()/escapeshellarg() at all for something that
 * touches user-supplied file bytes. Content-hashed filenames double as
 * dedup + far-future cache busting: a changed image is a new URL, so there's
 * no cache header to reason about.
 *
 * Stored under storage/uploads, outside every docroot (see config.php) and
 * served back out through the controlled /media/{file} route rather than
 * being web-exposed directly.
 */
final class Uploads
{
    private const MAX_BYTES = 8 * 1024 * 1024;
    private const MAX_DIM = 1920;

    /** @var array<int, string> IMAGETYPE_* => extension */
    private const ALLOWED = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    /**
     * @param array{tmp_name?: string, error?: int, size?: int}|null $file one entry of $_FILES
     * @return string|null public URL path (/media/xxx.ext), or null if no file was submitted
     * @throws \RuntimeException on an invalid/oversized/unsupported upload — caller turns this into a form error
     */
    public static function store(?array $file, int $maxDim = self::MAX_DIM): ?string
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload failed — please try again.');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('Upload failed — please try again.');
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            throw new \RuntimeException('Image too large (max 8MB).');
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false || !isset(self::ALLOWED[$info[2]])) {
            throw new \RuntimeException('Unsupported image type — use JPG, PNG, or WebP.');
        }

        $ext = self::ALLOWED[$info[2]];
        $src = self::readImage($file['tmp_name'], $info[2]);
        if ($src === false) {
            throw new \RuntimeException('Could not read that image.');
        }

        // No imagedestroy() calls: deprecated since PHP 8.0 (GdImage objects are
        // refcounted/GC'd on their own — calling it just emits a deprecation
        // notice, which corrupts the response by writing to output before the
        // controller's redirect header() call).
        $resized = self::resize($src, $maxDim);
        $data = self::encode($resized, $info[2]);

        $filename = substr(hash('sha256', $data), 0, 32) . '.' . $ext;
        $dir = self::dir();
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Storage directory unavailable.');
        }

        if (file_put_contents($dir . '/' . $filename, $data, LOCK_EX) === false) {
            throw new \RuntimeException('Could not save the image.');
        }

        return '/media/' . $filename;
    }

    /** Best-effort cleanup when an image is replaced or a row is deleted. Never throws. */
    public static function delete(?string $url): void
    {
        if ($url === null || !str_starts_with($url, '/media/')) {
            return;
        }

        $filename = basename($url);
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $filename)) {
            return;
        }

        @unlink(self::dir() . '/' . $filename);
    }

    private static function dir(): string
    {
        return rtrim((string) Config::get('storage')['uploads_path'], '/');
    }

    /** @return \GdImage|false */
    private static function readImage(string $path, int $type)
    {
        return match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            default => false,
        };
    }

    private static function resize(\GdImage $src, int $maxDim): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1.0, $maxDim / max($w, $h));

        if ($scale >= 1.0) {
            return $src;
        }

        $newW = max(1, (int) round($w * $scale));
        $newH = max(1, (int) round($h * $scale));

        $out = imagecreatetruecolor($newW, $newH);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
        imagefill($out, 0, 0, $transparent);

        imagecopyresampled($out, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        return $out;
    }

    private static function encode(\GdImage $image, int $type): string
    {
        ob_start();
        match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, null, 85),
            IMAGETYPE_PNG => imagepng($image, null, 6),
            IMAGETYPE_WEBP => imagewebp($image, null, 85),
            default => imagejpeg($image, null, 85),
        };

        return (string) ob_get_clean();
    }
}

// lucent: JPEG/PNG/WebP only, single re-encoded size (no responsive srcset) —
// matches spec's "automatic resizing," not a full media-library upgrade.
