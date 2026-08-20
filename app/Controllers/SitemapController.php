<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Models\ContentPost;

final class SitemapController
{
    /**
     * robots.txt is generated rather than shipped as a static file: the Sitemap
     * line needs this install's own absolute URL, which is only known from
     * config. A static file here would also shadow this route, since .htaccess
     * passes real files straight through.
     */
    public function robots(): void
    {
        $appUrl = rtrim((string) (Config::get('app')['url'] ?? ''), '/');

        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\n";
        echo "Allow: /\n";
        echo "Disallow: /admin\n";
        echo "\n";
        echo 'Sitemap: ' . $appUrl . "/sitemap.xml\n";
    }

    public function index(): void
    {
        $appUrl = rtrim((string) (Config::get('app')['url'] ?? ''), '/');

        $urls = [
            ['loc' => $appUrl . '/', 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => $appUrl . '/articles', 'changefreq' => 'weekly', 'priority' => '0.6'],
            ['loc' => $appUrl . '/contact', 'changefreq' => 'monthly', 'priority' => '0.5'],
        ];

        foreach (ContentPost::allPublished() as $post) {
            $urls[] = [
                'loc' => $appUrl . '/article/' . (int) $post['id'],
                'lastmod' => (new \DateTimeImmutable((string) $post['updated_at']))->format('Y-m-d'),
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ];
        }

        header('Content-Type: application/xml; charset=utf-8');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            echo '  <url>' . "\n";
            echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>' . "\n";
            if (isset($u['lastmod'])) {
                echo '    <lastmod>' . $u['lastmod'] . '</lastmod>' . "\n";
            }
            echo '    <changefreq>' . $u['changefreq'] . '</changefreq>' . "\n";
            echo '    <priority>' . $u['priority'] . '</priority>' . "\n";
            echo '  </url>' . "\n";
        }
        echo '</urlset>' . "\n";
    }
}
