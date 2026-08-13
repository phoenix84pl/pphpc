<?php

namespace Phoenix\Core\Controller;

use Psr\Http\Message\ServerRequestInterface;

class UiController
{
    public function index(?ServerRequestInterface $request = null): mixed
    {
        // 1. Ustalenie wariantu UI z .env
        $uiMode = $_ENV['UI_MODE'] ?? getenv('UI_MODE') ?: 'tiles';
        $uiMode = preg_replace('/[^a-z0-9_-]/i', '', strtolower($uiMode));

        if ($uiMode === 'ui') {
            $uiMode = 'tiles';
        }

        // 2. Szukamy i uruchamiamy kontroler konkretnego wariantu (np. TilesController),
        // aby przygotował nam zmienne $orientation i $tiles!
        $controllerName = ucfirst($uiMode) . 'Controller';
        $classTerminal  = "\\Phoenix\\Terminal\\Controller\\{$controllerName}";
        $classApp       = "\\Phoenix\\App\\Controller\\{$controllerName}";

        $targetClass = class_exists($classTerminal) ? $classTerminal : (class_exists($classApp) ? $classApp : null);

        // Zapobiegamy pętli (gdyby targetClass okazał się tym samym UiController)
        if ($targetClass === static::class) {
            $targetClass = null;
        }

        // 3. Jeśli mamy kontroler wariantu, pobieramy z niego wyrenderowany HTML kafelków
        // LUB uruchamiamy go, aby przygotował zmienne
        $variantHtml = '';
        if ($targetClass && method_exists($targetClass, 'index')) {
            $variantController = new $targetClass();
            $variantHtml       = $variantController->index($request);
        }

        // Ścieżka do opakowania UI
        $appRoot     = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 5);
        $viewsPath   = defined('VIEWS_PATH') ? VIEWS_PATH : $appRoot . '/views';
        $variantView = $viewsPath . "/{$uiMode}.phtml";
        $uiWrapper   = $viewsPath . "/ui.phtml";

        // Detekcja AJAX
        $isAjaxHeader = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $isPsrAjax    = $request && strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
        $isRender     = isset($_GET['render']) && in_array($_GET['render'], ['widget', 'window']);
        $isAjax       = $isAjaxHeader || $isPsrAjax || $isRender;

        // Buforujemy wyjście ui.phtml
        ob_start();
        if (file_exists($uiWrapper)) {
            // Jeśli kontroler wariantu zwrócił już gotowy HTML kafelków, 
            // wyświetlamy go bezpośrednio, a jeśli nie - ładujemy plik wariantu
            require $uiWrapper;
        } else {
            if ($variantHtml) {
                echo $variantHtml;
            } else {
                require file_exists($variantView) ? $variantView : $viewsPath . "/tiles.phtml";
            }
        }
        $uiHtml = ob_get_clean();

        if ($isAjax) {
            return $uiHtml;
        }

        // Wejście przez przeglądarkę (SSR z layout.phtml)
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