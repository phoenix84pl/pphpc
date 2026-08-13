<?php

namespace Phoenix\Core\Controller;

class UiController
{
    public function index(): string
    {
        // 1. Pobranie wariantu UI z .env (domyślnie 'tiles')
        $uiMode = $_ENV['UI_MODE'] ?? getenv('UI_MODE') ?: 'tiles';
        $uiMode = preg_replace('/[^a-z0-9_-]/i', '', strtolower($uiMode));

        // 2. Pobieramy katalog widoków aplikacji (zdefiniowany w stałej lub konfiguracji)
        $viewsPath = defined('VIEWS_PATH') ? VIEWS_PATH : dirname(__DIR__, 4) . '/views';

        // 3. Ścieżka do podwidoku UI aplikacji
        $viewPath = $viewsPath . "/ui/{$uiMode}.phtml";

        // Fallback: jeśli wskazany plik nie istnieje, ładujemy domyślny tiles.phtml
        if (!file_exists($viewPath)) {
            $viewPath = $viewsPath . "/ui/tiles.phtml";
        }

        // 4. Obsługa wariantów renderowania (Page vs Widget / Window / AJAX)
        $render = $_GET['render'] ?? 'page';

        ob_start();
        if ($render === 'window' || $render === 'widget' || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            require $viewPath;
        } else {
            $layoutPath = $viewsPath . "/layout.phtml";
            if (file_exists($layoutPath)) {
                require $layoutPath;
            } else {
                require $viewPath;
            }
        }

        return ob_get_clean();
    }
}