<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    /**
     * Rendert ein Template innerhalb des Layouts.
     *
     * @param array<string,mixed> $data
     */
    public static function render(string $template, array $data = [], string $layout = 'layout/app'): void
    {
        $content = self::partial($template, $data);
        if ($layout === '') {
            echo $content;
            return;
        }
        echo self::partial($layout, $data + ['content' => $content]);
    }

    /** @param array<string,mixed> $__data */
    public static function partial(string $__template, array $__data = []): string
    {
        $__file = VIEW_PATH . '/' . $__template . '.php';
        if (!is_file($__file)) {
            throw new \RuntimeException('Template fehlt: ' . $__template);
        }
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            include $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public static function error(int $code, string $title, string $message): never
    {
        http_response_code($code);
        if (Request::wantsJson()) {
            json_response(['error' => $message], $code);
        }
        $layout = Auth::check() ? 'layout/app' : 'layout/guest';
        self::render('errors/error', ['title' => $title, 'code' => $code, 'message' => $message, 'pageTitle' => $title], $layout);
        exit;
    }
}
