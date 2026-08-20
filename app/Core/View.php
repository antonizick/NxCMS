<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    /**
     * Render a view inside the public layout.
     *
     * Note the deliberate lack of extract(): `require` shares the caller's
     * local scope (the bug that cost us Phase 0), so views read $data
     * explicitly rather than having variables injected around them.
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], string $layout = 'layouts/public'): void
    {
        echo self::capture($view, $data, $layout);
    }

    /** @param array<string, mixed> $data */
    public static function partial(string $view, array $data = []): string
    {
        return self::capture($view, $data, null);
    }

    /** @param array<string, mixed> $data */
    private static function capture(string $view, array $data, ?string $layout): string
    {
        $file = self::path($view);
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        ob_start();
        (static function (string $__file, array $data): void {
            require $__file;
        })($file, $data);
        $content = (string) ob_get_clean();

        if ($layout === null) {
            return $content;
        }

        ob_start();
        (static function (string $__file, array $data, string $content): void {
            require $__file;
        })(self::path($layout), $data, $content);

        return (string) ob_get_clean();
    }

    private static function path(string $view): string
    {
        return dirname(__DIR__, 2) . '/resources/views/' . $view . '.php';
    }

    /** Escape for HTML text/attribute context. */
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
