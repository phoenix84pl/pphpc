<?php

namespace Phoenix\Core\Controller;

use Psr\Http\Message\ServerRequestInterface;

class UiController
{
    public function index(ServerRequestInterface $request): mixed
    {
        // 1. Odczytujemy tryb z .env (np. 'tiles')
        $uiMode = $_ENV['UI_MODE'] ?? getenv('UI_MODE') ?: 'tiles';
        $uiMode = preg_replace('/[^a-z0-9_-]/i', '', strtolower($uiMode));

        // 2. Budujemy nazwę klasy docelowego kontrolera (np. \Phoenix\Terminal\Controller\TilesController)
        $controllerName = ucfirst($uiMode) . 'Controller';
        $classTerminal  = "\\Phoenix\\Terminal\\Controller\\{$controllerName}";
        $classApp       = "\\Phoenix\\App\\Controller\\{$controllerName}";

        $targetClass = class_exists($classTerminal) ? $classTerminal : (class_exists($classApp) ? $classApp : null);

        // 3. Odpalamy kontroler wewnętrznie i zwracamy jego wynik bezpośrednio do Routera
        if ($targetClass && method_exists($targetClass, 'index')) {
            $controller = new $targetClass();
            return $controller->index($request);
        }

        return "Brak kontrolera dla UI_MODE [{$uiMode}] w aplikacji.";
    }
}