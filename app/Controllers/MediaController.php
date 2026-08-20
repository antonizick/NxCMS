<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;

/**
 * Streams admin-uploaded images from storage/uploads (outside every docroot,
 * see config.php) back out under a controlled, unauthenticated route — the
 * portal itself is public, so headshots/logos/article images need to render
 * for anyone, not just logged-in admins.
 */
final class MediaController
{
    private const TYPES = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public function show(string $filename): void
    {
        // Filenames are always content-hash + extension from Uploads::store();
        // reject anything else outright rather than attempting to sanitize
        // path segments (defense in depth against traversal even though the
        // router only captures one path segment here already).
        if (!preg_match('/^([a-f0-9]{32})\.(jpg|png|webp)$/', $filename, $m)) {
            http_response_code(404);
            return;
        }

        $dir = rtrim((string) Config::get('storage')['uploads_path'], '/');
        $path = $dir . '/' . $filename;

        if (!is_file($path)) {
            http_response_code(404);
            return;
        }

        header('Content-Type: ' . self::TYPES[$m[2]]);
        header('Content-Length: ' . (string) filesize($path));
        // Content-hashed filename: the bytes at this URL never change, so this is safe to cache forever.
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($path);
    }
}
