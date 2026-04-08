<?php

declare(strict_types=1);

abstract class Controller
{
    protected function render(string $viewPath, array $data = []): void
    {
        $fullPath = __DIR__ . '/../Views/' . $viewPath . '.php';

        if (!file_exists($fullPath)) {
            http_response_code(500);
            echo 'View khong ton tai: ' . htmlspecialchars($viewPath, ENT_QUOTES, 'UTF-8');
            return;
        }

        if (!empty($data)) {
            extract($data, EXTR_SKIP);
        }

        require $fullPath;
    }
}
