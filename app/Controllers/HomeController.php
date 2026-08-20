<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\ContentPost;
use App\Models\Project;
use App\Models\PageView;
use App\Models\Settings;
use App\Models\Skill;

final class HomeController
{
    public function index(): void
    {
        PageView::recordCurrent();

        $site = Settings::site();

        View::render('home', [
            'site' => $site,
            'profile' => Settings::profile(),
            'socials' => Settings::socialLinks(),
            'projects' => Project::published(),
            'skills' => Skill::all(),
            // Tile 4 = newest news, Tile 8 = second newest news.
            'news1' => ContentPost::latest('news', 0),
            'news2' => ContentPost::latest('news', 1),
            'youtube' => ContentPost::latest('youtube'),
            'projectWork' => ContentPost::latest('project_work'),
            'pageTitle' => $site['page_title'] ?? 'Portal',
            'metaDescription' => self::excerpt(Settings::profile()['bio'] ?? '', 160),
        ]);
    }

    public static function excerpt(string $text, int $length): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $length - 1), " ,.;:") . '…';
    }
}

assert(HomeController::excerpt('a b  c', 40) === 'a b c');
