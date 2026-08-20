<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\ContentPost;
use App\Models\PageView;
use App\Models\Settings;
use App\Support\Markdown;

final class ArticleController
{
    public function show(string $id): void
    {
        PageView::recordCurrent();

        $post = ctype_digit($id) ? ContentPost::find((int) $id) : null;

        if ($post === null) {
            http_response_code(404);
            View::render('errors/404', [
                'site' => Settings::site(),
                'pageTitle' => 'Not found',
            ]);
            return;
        }

        $site = Settings::site();

        View::render('article', [
            'site' => $site,
            'post' => $post,
            // Sidebar shows history across all categories, newest first.
            'history' => ContentPost::search('all', '', 'newest', 12, 0),
            'bodyHtml' => Markdown::toHtml($post['body'] ?? ''),
            'pageTitle' => $post['title'] . ' — ' . ($site['display_name'] ?? ''),
            'metaDescription' => HomeController::excerpt((string) ($post['body'] ?? ''), 160),
            'ogImage' => $post['image_url'] ?? null,
        ]);
    }
}
