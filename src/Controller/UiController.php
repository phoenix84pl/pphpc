<?php

namespace Phoenix\Core\Controller;

use Psr\Http\Message\ServerRequestInterface;

class UiController
{
    public function index(?ServerRequestInterface $request = null): mixed
    {
        // 1. Pobieramy wariant z .env (domyślnie 'tiles')
        $uiMode = $_ENV['UI_MODE'] ?? getenv('UI_MODE') ?: 'tiles';
        $uiMode = preg_replace('/[^a-z0-9_-]/i', '', strtolower($uiMode));

        if ($uiMode === 'ui') {
            $uiMode = 'tiles';
        }

        // 2. Odnajdujemy kontroler dla tego wariantu (np. TilesController)
        $controllerName = ucfirst($uiMode) . 'Controller';
        $classTerminal  = "\\Phoenix\\Terminal\\Controller\\{$controllerName}";
        $classApp       = "\\Phoenix\\App\\Controller\\{$controllerName}";

        $targetClass = class_exists($classTerminal) ? $classTerminal : (class_exists($classApp) ? $classApp : null);

        // Zapobieganie pętli
        if (!$targetClass || $targetClass === static::class) {
            $targetClass = "\\Phoenix\\Terminal\\Controller\\TilesController";
        }

        // 3. ODPALAMY KONTROLER WARIANTU DIRECTLY! 
        // UiController nie dodaje własnych divów ani szablonów - po prostu zwraca to, co złożył TilesController.
        $variantController = new $targetClass();
        return $variantController->index($request);
    }
}