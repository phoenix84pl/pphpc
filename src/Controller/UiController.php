<?php

namespace Phoenix\Core\Controller;

use Psr\Http\Message\ServerRequestInterface;

class UiController
{
    public function index(?ServerRequestInterface $request = null): mixed
    {
        // 1. Ustalenie wariantu UI z .env (np. 'tiles')
        $uiMode = $_ENV['UI_MODE'] ?? getenv('UI_MODE') ?: 'tiles';
        $uiMode = preg_replace('/[^a-z0-9_-]/i', '', strtolower($uiMode));

        if ($uiMode === 'ui') {
            $uiMode = 'tiles';
        }

        // 2. Odpalenie kontrolera wariantu (np. TilesController) po dane $tiles i $orientation
        $controllerName = ucfirst($uiMode) . 'Controller';
        $classTerminal  = "\\Phoenix\\Terminal\\Controller\\{$controllerName}";
        $classApp       = "\\Phoenix\\App\\Controller\\{$controllerName}";

        $targetClass = class_exists($classTerminal) ? $classTerminal : (class_exists($classApp) ? $classApp : null);

        if ($targetClass === static::class) {
            $targetClass = null;
        }

        $variantHtml = '';
        if ($targetClass && method_exists($targetClass, 'index')) {
            $variantController = new $targetClass();
            $variantHtml       = $variantController->index($request);
        }

        // 3. Ścieżki widoków
        $appRoot     = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 5);
        $viewsPath   = defined('VIEWS_PATH') ? VIEWS_PATH : $appRoot . '/views';
        $variantView = $viewsPath . "/{$uiMode}.phtml";

        // 4. Detekcja AJAX
        $isAjaxHeader = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $isPsrAjax    = $request && strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
        $isRender     = isset($_GET['render']) && in_array($_GET['render'], ['widget', 'window', 'ajax']);
        $isAjax       = $isAjaxHeader || $isPsrAjax || $isRender;

        // A. AJAX / Przeładowanie (CMSReLoad): Zwracamy CZYSTY ui.phtml (BEZ skryptów autostartu)
        if ($isAjax) {
            ob_start();
            require $viewsPath . '/ui.phtml';
            return ob_get_clean();
        }

        // B. Pierwsze załadowanie w przeglądarce: Zwracamy index.phtml (ZE skryptami autostartu)
        $contentView = $viewsPath . '/index.phtml';

        ob_start();
        $layoutPath = $viewsPath . "/layout.phtml";
        if (file_exists($layoutPath)) {
            require $layoutPath;
        } else {
            require $contentView;
        }
        return ob_get_clean();
    }
}