<?php
// pphpc/src/Router.php

namespace Phoenix\Core;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Response;

class Router 
{
    private array $routes = [];
    private string $viewsPath;

    public function __construct(string $viewsPath)
    {
        $this->viewsPath = rtrim($viewsPath, '/');
    }

    public function add(string $method, string $uri, mixed $handler): void
    {
        $this->routes[strtoupper($method)][trim($uri, '/')] = $handler;
    }

    public function get(string $uri, mixed $handler): void { $this->add('GET', $uri, $handler); }
    public function post(string $uri, mixed $handler): void { $this->add('POST', $uri, $handler); }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        $uri = trim($request->getUri()->getPath(), '/');

        // 1. Priorytet: Sztywne, ręczne trasy dewelopera
        if (isset($this->routes[$method][$uri])) {
            return $this->executeHandler($this->routes[$method][$uri], $request);
        }

        // Rozbijamy URL na części do routingu dynamicznego
        $czesci = array_values(array_filter(explode('/', $uri)));
        if (empty($czesci)) {
            $czesci = ['index'];
        }

        // 2. Detekcja warstwy architektonicznej (core, app lub domyślnie terminal)
        $firstSegment = strtolower($czesci[0]);
        if (in_array($firstSegment, ['core', 'app'])) {
            $layer = ucfirst($firstSegment); // "Core" lub "App"
            array_shift($czesci);            // Usuwamy prefiks warstwy z dalszego przetwarzania
        } else {
            $layer = 'Terminal';             // Domyślna warstwa aplikacji
        }

        if (empty($czesci)) {
            $czesci = ['index'];
        }

        // PRZELICZENIE $typ PO PRZESUNIĘCIU PREFIKSU
        $typ = strtolower($czesci[0]);

        // 3. Dynamiczna obsługa AKCJI/API/PLIKÓW (/action/..., /api/..., /file/...)
        if (in_array($typ, ['action', 'api', 'file']) && isset($czesci[1])) {
            $subNamespace   = ucfirst($typ);                // "Action", "Api", "File"
            $controllerName = ucfirst($czesci[1]);          // "Logout", "Update", "Status"
            $akcja          = !empty($czesci[2]) ? trim($czesci[2]) : 'index';

            // Konstruujemy jednoznaczną klasę dla wyznaczonej warstwy
            $className = "\\Phoenix\\{$layer}\\Controller\\{$subNamespace}\\{$controllerName}{$subNamespace}";

            if (class_exists($className) && method_exists($className, $akcja)) {
                return $this->executeHandler([$className, $akcja], $request);
            }

            return new Response(404, ['Content-Type' => 'application/json; charset=utf-8'], json_encode([
                'status' => 'ERROR',
                'message' => "Endpoint [{$typ}] not found or method [{$akcja}] missing in class {$className}."
            ]));
        }

        // 4. Obsługa KONTROLERÓW WIDOKÓW (/tiles, /intro, /view/...)
        $viewUriParts = ($typ === 'view') ? array_slice($czesci, 1) : $czesci;
        if (empty($viewUriParts)) {
            $viewUriParts = ['index'];
        }

        $viewUri = implode('/', $viewUriParts);
        $viewFile = $this->viewsPath . '/' . $viewUri . '.phtml';

        // Przywrócona oryginalna obsługa welcome.phtml dla strony głównej
        if ($viewUri === 'index' && !file_exists($viewFile)) {
            $viewFile = $this->viewsPath . '/welcome.phtml';
        }

        // A. Szukamy jednoznacznego kontrolera dla danej warstwy
        $formattedSegments = array_map(fn($s) => ucfirst($s), $viewUriParts);
        $relativeClass     = implode('\\', $formattedSegments) . "Controller";
        $controllerClass   = "\\Phoenix\\{$layer}\\Controller\\" . $relativeClass;

        if (class_exists($controllerClass) && method_exists($controllerClass, 'index')) {
            return $this->executeHandler([$controllerClass, 'index'], $request);
        }

        // B. FALLBACK: Jeśli kontrolera brak, ale istnieje sam widok .phtml (index.phtml lub welcome.phtml)
        if (file_exists($viewFile)) {
            return $this->executeHandler($viewFile, $request);
        }

        // 5. Całkowity brak dopasowania
        if (in_array($typ, ['api', 'action'])) {
            return new Response(404, ['Content-Type' => 'application/json; charset=utf-8'], json_encode([
                'status' => 'ERROR', 
                'message' => "404 - Endpoint Not Found in layer {$layer}"
            ]));
        }
        
        return new Response(404, [], '<h1>404 - Not Found (Phoenix Core)</h1>');
    }

    private function executeHandler(mixed $handler, ServerRequestInterface $request): ResponseInterface
    {
        global $db;

        if (is_callable($handler)) {
            $result = $handler($request);
            if ($result instanceof ResponseInterface) return $result;
            return new Response(200, [], (string)$result);
        }

        if (is_string($handler) && file_exists($handler)) {
            ob_start();
            require $handler;
            $content = ob_get_clean();
            return new Response(200, [], $content);
        }

        if (is_array($handler)) {
            [$controllerClass, $method] = $handler;
            
            if (class_exists($controllerClass)) {
                $controller = new $controllerClass();
                
                if (method_exists($controller, $method)) {
                    $result = $controller->$method($request);
                    if ($result instanceof ResponseInterface) return $result;
                    return new Response(200, [], (string)$result);
                }
            }
        }

        return new Response(500, [], '<h1>500 - Invalid Handler Configuration</h1>');
    }
}