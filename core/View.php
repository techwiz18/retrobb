<?php
declare(strict_types=1);

namespace RetroBB\Core;

class View
{
    public static function render(string $template, array $vars = [], ?string $layout = 'layout'): void
    {
        extract($vars, EXTR_SKIP);
        $viewFile = dirname(__DIR__) . '/views/' . $template . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'Missing view: ' . htmlspecialchars($template);
            return;
        }
        if ($layout === null) {
            require $viewFile;
            return;
        }
        $contentFile = $viewFile;
        $layoutFile = dirname(__DIR__) . '/views/' . $layout . '.php';
        // $content buffering so layout can echo $content
        ob_start();
        require $contentFile;
        $content = ob_get_clean();
        require $layoutFile;
    }

    public static function partial(string $template, array $vars = []): void
    {
        extract($vars, EXTR_SKIP);
        $file = dirname(__DIR__) . '/views/' . $template . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
}
