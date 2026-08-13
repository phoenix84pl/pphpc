<?php

namespace Phoenix\Core\Controller;

use Psr\Http\Message\ServerRequestInterface;

class UiController
{
    public function index(?ServerRequestInterface $request = null): mixed
    {
        // 1. Odczyt wariantu z .env
        $uiMode = $_ENV['UI_MODE'] ?? getenv('UI_MODE') ?: 'tiles';
        $uiMode = preg_replace('/[^a-z0-9_-]/i', '', strtolower($uiMode));

        if ($uiMode === 'ui') {
            $uiMode = 'tiles';
        }

        // 2. Wyznaczenie kontrolera wariantu
        $controllerName = ucfirst($uiMode) . 'Controller';
        $classTerminal  = "\\Phoenix\\Terminal\\Controller\\{$controllerName}";
        $classApp       = "\\Phoenix\\App\\Controller\\{$controllerName}";

        $targetClass = class_exists($classTerminal) ? $classTerminal : (class_exists($classApp) ? $classApp : null);

        // 3. Bezpieczna i uniwersalna detekcja AJAX (PSR-7 OR $_SERVER OR GET render)
        $isAjaxHeader = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $isPsrAjax    = $request && strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
        $isRender     = isset($_GET['render']) && in_array($_GET['render'], ['widget', 'window']);

        $isAjax = $isAjaxHeader || $isPsrAjax || $isRender;

        // A. Jeśli to AJAX lub jawny render - zwracamy sam środek z TilesController
        if ($isAjax) {
            if ($targetClass && method_exists($targetClass, 'index')) {
                $controller = new $targetClass();
                return $controller->index($request);
            }
        }

        // B. Zwykłe wejście z przeglądarki - zwracamy pustą powłokę layout.phtml
        $appRoot     = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 5);
        $viewsPath   = defined('VIEWS_PATH') ? VIEWS_PATH : $appRoot . '/views';
        $contentView = $viewsPath . "/{$uiMode}.phtml";

        if (!file_exists($contentView)) {
            $contentView = $viewsPath . "/tiles.phtml";
        }

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