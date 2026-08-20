<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\ContentPost;
use App\Models\PageView;
use App\Models\Settings;

final class ArchiveController
{
    private const PER_PAGE = 10;

    public function index(): void
    {
        PageView::recordCurrent();

        $category = (string) ($_GET['category'] ?? 'all');
        if ($category !== 'all' && !in_array($category, ContentPost::CATEGORIES, true)) {
            $category = 'all';
        }

        $sort = (string) ($_GET['sort'] ?? 'newest');
        if (!array_key_exists($sort, ContentPost::SORTS)) {
            $sort = 'newest';
        }

        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120);

        $total = ContentPost::searchCount($category, $q);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($lastPage, (int) ($_GET['page'] ?? 1)));

        $posts = ContentPost::search($category, $q, $sort, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        $site = Settings::site();
        $label = $category === 'all' ? 'Article' : (ContentPost::LABELS[$category] ?? $category);

        View::render('articles', [
            'site' => $site,
            'posts' => $posts,
            'category' => $category,
            'sort' => $sort,
            'q' => $q,
            'page' => $page,
            'lastPage' => $lastPage,
            'total' => $total,
            'perPage' => self::PER_PAGE,
            'categories' => array_merge(['all'], ContentPost::CATEGORIES),
            'pageTitle' => $label . ' archive — ' . ($site['display_name'] ?? ''),
            'metaDescription' => 'Browse the full ' . strtolower($label) . ' archive.',
        ]);
    }
}
