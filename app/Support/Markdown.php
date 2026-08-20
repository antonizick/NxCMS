<?php

declare(strict_types=1);

namespace App\Support;

use League\CommonMark\CommonMarkConverter;

final class Markdown
{
    private static ?CommonMarkConverter $converter = null;

    /**
     * Article bodies are Markdown with inline HTML allowed, per spec.
     *
     * html_input=allow is safe only because bodies are authored exclusively
     * by authenticated admins — there is no public write path into this
     * column. If that ever changes, flip to 'strip' before anything else.
     */
    public static function toHtml(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        self::$converter ??= new CommonMarkConverter([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
        ]);

        return (string) self::$converter->convert($markdown);
    }
}
