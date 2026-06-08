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
        $typ = $czesci[0] ?? '';

        // 2. Dynamiczna obsługa podziału architektonicznego (action, api, file, core)
        if (in_array($typ, ['action', 'api', 'file', 'core']) && isset($czesci[1], $czesci[2])) {
            
            if ($typ === 'core' && $czesci[1] === 'status') {
                // Wyjątek dla wbudowanego statusu systemu w rdzeniu
                $className = "\\Phoenix\\Core\\Controller\\Status";
                $akcja = $czesci[2];
            } else {
                // Dynamiczne mapowanie: /api/stock/list -> \Phoenix\App\Controller\Api\Stock->list()
                $subNamespace = ucfirst($typ);         // "api" -> "Api", "action" -> "Action"
                $controllerName = ucfirst($czesci[1]); // "stock" -> "Stock"
                $akcja = $czesci[2];                  // "list"
                
                $className = "\\Phoenix\\App\\Controller\\{$subNamespace}\\{$controllerName}";
            }

            if (class_exists($className) && method_exists($className, $akcja)) {
                return $this->executeHandler([$className, $akcja], $request);
            }

            return new Response(404, ['Content-Type' => 'application/json'], json_encode([
                'status' => 'ERROR',
                'message' => "Endpoint [{$typ}] not found or method missing in class {$className}."
            ]));
        }

        // 3. Obsługa WIDOKÓW (view / lub czysty fallback bez prefiksu dla ładnych URL)
        $viewUri = ($typ === 'view') ? implode('/', array_slice($czesci, 1)) : $uri;
        
        // Zgłoszenie na stronę główną
        if ($viewUri === '') {
            $viewFile = $this->viewsPath . '/index.phtml';
            
            // ARCHITEKTONICZNY FALLBACK: Jeśli w projekcie nie ma index.phtml,
            // ładujemy welcome.phtml (dzięki temu szablon A nigdy nie nadpisze strony głównej PT)
            if (!file_exists($viewFile)) {
                $viewFile = $this->viewsPath . '/welcome.phtml';
            }
        } else {
            // Standardowa ścieżka dla podstron (np. /kontakt -> kontakt.phtml)
            $viewFile = $this->viewsPath . '/' . $viewUri . '.phtml';
        }

        if (file_exists($viewFile)) {
            return $this->executeHandler($viewFile, $request);
        }

        // 4. Całkowity brak dopasowania (Zwraca JSON dla kodu, HTML dla stron)
        if (in_array($typ, ['api', 'action'])) {
            return new Response(404, ['Content-Type' => 'application/json'], json_encode(['status' => 'ERROR', 'message' => '404 - Endpoint Not Found']));
        }
        
        return new Response(404, [], '<h1>404 - Not Found (Phoenix Core)</h1>');
    }

    private function executeHandler(mixed $handler, ServerRequestInterface $request): ResponseInterface
    {
        // 1. Wyciągamy bazę z pamięci globalnej raz na początku
        global $db;

        if (is_callable($handler)) {
            $result = $handler($request);
            if ($result instanceof ResponseInterface) return $result;
            return new Response(200, [], (string)$result);
        }

        // 2. Obsługa plików widoków (.phtml) - dzięki temu widoki widzą zmienną $db
        if (is_string($handler) && file_exists($handler)) {
            ob_start();
            require $handler;
            $content = ob_get_clean();
            return new Response(200, [], $content);
        }

        // 3. Obsługa kontrolerów i akcji
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

        return new Response(500, [], '<h1>500 - Invalid Handler</h1>');
    }
}