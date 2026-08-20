<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\ContentPost;
use App\Models\Project;
use App\Models\PageView;
use App\Models\Settings;
use App\Models\Skill;
use App\Support\Markdown;

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
            // Tile 1 and tile 7 carousels. Empty arrays are the normal case
            // and collapse each tile back to its single original slide.
            'profileRotation' => ContentPost::rotation('profile'),
            'mapRotation' => ContentPost::rotation('map'),
            'pageTitle' => $site['page_title'] ?? 'Portal',
            'metaDescription' => self::excerpt(Settings::profile()['bio'] ?? '', 160),
        ]);
    }

    /**
     * Only the opening of a body can ever reach an excerpt, and some bodies
     * run to tens of thousands of characters. No reason to parse all of one
     * to quote its first few hundred.
     */
    private const PLAIN_TEXT_WINDOW = 8000;

    /** @var array<string, string> memo: each body is reduced twice per slide — the text, then "was it cut?". */
    private static array $plainTextCache = [];

    public static function excerpt(string $text, int $length): string
    {
        $text = self::plainText($text);

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $length - 1), " ,.;:") . '…';
    }

    /**
     * A body reduced to plain prose.
     *
     * Post bodies are Markdown, so they have to go through the same converter
     * the article page uses before the text can be quoted anywhere. Running
     * strip_tags() over raw Markdown leaks the syntax onto the page: a post
     * opening with a heading renders on the home page as "# Heading The rest
     * of the sentence…". Converting rather than regex-stripping markers means
     * an excerpt quotes whatever the article actually renders as — links,
     * emphasis, lists, images and code fences included — instead of whichever
     * subset of Markdown a pattern remembered to cover.
     *
     * Two details that are easy to get wrong here:
     *  - Closing block tags become spaces first, or a heading welds itself to
     *    the paragraph beneath it ("TitleBody text").
     *  - Entities are decoded after the tags come out, because the view
     *    escapes again on the way to the page. Leaving CommonMark's &amp;
     *    in place would show the visitor a literal "&amp;".
     */
    public static function plainText(string $text): string
    {
        return self::$plainTextCache[md5($text)] ??= self::toPlainText($text);
    }

    private static function toPlainText(string $text): string
    {
        $html = Markdown::toHtml(mb_substr($text, 0, self::PLAIN_TEXT_WINDOW));
        $html = preg_replace('#</(?:p|h[1-6]|li|blockquote|div|tr|pre|figcaption)>|<br\s*/?>#i', ' ', $html) ?? $html;
        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $plain) ?? '');
    }
}

assert(HomeController::excerpt('a b  c', 40) === 'a b c');
// The leak this pipeline exists to stop: Markdown syntax reaching the page.
assert(HomeController::excerpt("# Heading\n\nBody text", 40) === 'Heading Body text');
assert(HomeController::excerpt('**bold** and [a link](https://example.com)', 60) === 'bold and a link');
// ...without double-escaping on the way out, since the view escapes again.
assert(HomeController::excerpt('Ampersands & angles < here', 60) === 'Ampersands & angles < here');
