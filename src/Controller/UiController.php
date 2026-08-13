<?php

namespace Phoenix\Core\Controller;

class UiController
{
    public function index(): string
    {
        // 1. Pobranie wariantu UI z .env (domyślnie 'tiles')
        $uiMode = $_ENV['UI_MODE'] ?? getenv('UI_MODE') ?: 'tiles';
        $uiMode = preg_replace('/[^a-z0-9_-]/i', '', strtolower($uiMode));

        // 2. Ustalamy korzeń aplikacji pt (wyjście z vendor/phoenix84pl/pphpc/src/Controller)
        $appRoot = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 5);
        $viewsPath = defined('VIEWS_PATH') ? VIEWS_PATH : $appRoot . '/views';

        // 3. Szukamy widoku płasko w katalogu views/ (np. views/tiles.phtml)
        $contentView = $viewsPath . "/{$uiMode}.phtml";

        // Fallback: jeśli plik wskazanego UI nie istnieje, ładujemy domyślny views/tiles.phtml
        if (!file_exists($contentView)) {
            $contentView = $viewsPath . "/tiles.phtml";
        }

        // 4. Obsługa wariantów renderowania PT (Full Page vs Widget / Window / AJAX)
        $render = $_GET['render'] ?? 'page';

        ob_start();
        if ($render === 'window' || $render === 'widget' || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            // Sam widok kafelkowy bez otoczki HTML
            require $contentView;
        } else {
            // Pełna strona – layout.phtml dostaje zmienną $contentView i robi require wewnątrz
            $layoutPath = $viewsPath . "/layout.phtml";
            if (file_exists($layoutPath)) {
                require $layoutPath;
            } else {
                require $contentView;
            }
        }

        return ob_get_clean();
    }
}