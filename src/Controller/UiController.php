<?php

namespace Phoenix\Core\Controller;

use Psr\Http\Message\ServerRequestInterface;

class UiController
{
    public function index(ServerRequestInterface $request): mixed
    {
        // 1. Pobranie wariantu UI z .env (domyślnie 'tiles')
        $uiMode = $_ENV['UI_MODE'] ?? getenv('UI_MODE') ?: 'tiles';
        $uiMode = preg_replace('/[^a-z0-9_-]/i', '', strtolower($uiMode));

        if ($uiMode === 'ui') {
            $uiMode = 'tiles';
        }

        // 2. Wyznaczenie docelowego kontrolera (np. TilesController)
        $controllerName = ucfirst($uiMode) . 'Controller';
        $classTerminal  = "\\Phoenix\\Terminal\\Controller\\{$controllerName}";
        $classApp       = "\\Phoenix\\App\\Controller\\{$controllerName}";

        $targetClass = class_exists($classTerminal) ? $classTerminal : (class_exists($classApp) ? $classApp : null);

        // 3. Wykrycie, czy zapytanie idzie z AJAX (jQuery automatycznie wysyła ten nagłówek)
        $queryParams = $request->getQueryParams();
        $renderParam = $queryParams['render'] ?? null;
        $isAjax      = strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';

        // A. Jeśli to strzał AJAX lub parametr render=widget/window - zwracamy czysty widok z kontrolera wariantu
        if ($isAjax || in_array($renderParam, ['widget', 'window'])) {
            if ($targetClass && method_exists($targetClass, 'index')) {
                $controller = new $targetClass();
                return $controller->index($request);
            }
        }

        // B. Wywołanie bezpośrednio z paska adresu przeglądarki - zwracamy pełny szkielet layout.phtml
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