<?php

namespace Phoenix\Core\Controller;

use Psr\Http\Message\ServerRequestInterface;

class IndexController
{
    public function index(?ServerRequestInterface $request = null): mixed
    {
        // 1. Pobieramy wyrenderowany wariant z UiController (np. kafelki)
        $uiController = new \Phoenix\Core\Controller\UiController();
        $content      = $uiController->index($request);

        // 2. Detekcja AJAX - jeśli to zapytanie AJAX, oddajemy czystą treść bez szkieletu index.phtml
        $isAjaxHeader = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $isPsrAjax    = $request && strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
        $isRender     = isset($_GET['render']) && in_array($_GET['render'], ['widget', 'window', 'ajax']);

        if ($isAjaxHeader || $isPsrAjax || $isRender) {
            return $content;
        }

        // 3. Wejście bezpośrednie z przeglądarki -> kaskadowe oprawianie w szkielet index.phtml
        $appRoot     = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 5);
        $appViews    = defined('VIEWS_PATH') ? VIEWS_PATH : $appRoot . '/views';
        $coreViews   = dirname(__DIR__, 2) . '/views'; // pphpc/views

        $indexPath = file_exists($appViews . '/index.phtml') 
            ? $appViews . '/index.phtml' 
            : $coreViews . '/index.phtml';

        ob_start();
        if (file_exists($indexPath)) {
            require $indexPath;
        } else {
            echo $content;
        }
        return ob_get_clean();
    }
}