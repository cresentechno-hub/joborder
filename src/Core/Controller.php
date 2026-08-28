<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    /**
     * Render a view file inside a layout. $data keys become variables
     * available in both the view and the layout (e.g. $content, $user).
     */
    protected function view(string $view, array $data = [], string $layout = 'layouts/main'): void
    {
        extract($data, EXTR_SKIP);

        $viewFile = ROOT_PATH . '/views/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        $layoutFile = ROOT_PATH . '/views/' . $layout . '.php';
        if ($layout !== '' && is_file($layoutFile)) {
            require $layoutFile;
        } else {
            echo $content;
        }
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }
}
