<?php

namespace Phoenix\Core\Controller;

use Psr\Http\Message\ServerRequestInterface;

class UiController
{
    public function index(?ServerRequestInterface $request = null): mixed
    {
        // 1. Odczyt wariantu z .env (np. 'tiles')
        $uiMode = $_ENV['UI_MODE'] ?? getenv('UI_MODE') ?: 'tiles';
        $uiMode = preg_replace('/[^a-z0-9_-]/i', '', strtolower($uiMode));

        if ($uiMode === 'ui') {
            $uiMode = 'tiles';
        }

        // 2. Ścieżki do plików widoków
        $appRoot     = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 5);
        $viewsPath   = defined('VIEWS_PATH') ? VIEWS_PATH : $appRoot . '/views';
        
        // Ścieżka do konkretnego wariantu (np. views/tiles.phtml)
        $variantView = $viewsPath . "/{$uiMode}.phtml";
        if (!file_exists($variantView)) {
            $variantView = $viewsPath . "/tiles.phtml";
        }

        // Ścieżka do wspólnego opakowania UI (views/ui.phtml)
        $uiWrapper = $viewsPath . "/ui.phtml";

        // 3. Detekcja AJAX
        $isAjaxHeader = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $isPsrAjax    = $request && strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
        $isRender     = isset($_GET['render']) && in_array($_GET['render'], ['widget', 'window']);

        $isAjax = $isAjaxHeader || $isPsrAjax || $isRender;

        // A. Jeśli to AJAX / okno – renderujemy ui.phtml (który w środku robi require $variantView + wywołuje skrypty UI)
        ob_start();
        if (file_exists($uiWrapper)) {
            require $uiWrapper; // w środku używa $variantView
        } else {
            require $variantView;
        }
        $uiHtml = ob_get_clean();

        if ($isAjax) {
            return $uiHtml;
        }

        // B. Jeśli to wejście bezpośrednie z przeglądarki – uderzamy w layout.phtml,
        // a jako treść ($contentView) przekazujemy ui.phtml
        $contentView = file_exists($uiWrapper) ? $uiWrapper : $variantView;

        ob_start();
        $layoutPath = $viewsPath . "/layout.phtml";
        if (file_exists($layoutPath)) {
            require $layoutPath;
        } else {
            echo $uiHtml;
        }
        return ob_get_clean();
    }
}