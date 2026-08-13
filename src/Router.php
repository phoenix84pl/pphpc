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
        if (in_array($typ, ['action', 'api', 'file', 'core']) && isset($czesci[1])) {
            
            if ($typ === 'core' && $czesci[1] === 'status') {
                // Wyjątek dla wbudowanego statusu systemu w rdzeniu
                $className = "\\Phoenix\\Core\\Controller\\Status";
                $akcja = $czesci[2] ?? 'index';
            } else {
                // Dynamiczne mapowanie podwójne/potrójne z detekcją priorytetu (Terminal -> App -> Core)
                $subNamespace = ucfirst($typ);         // "action" -> "Action", "api" -> "Api"
                $controllerName = ucfirst($czesci[1]); // "cmsupdate" -> "Cmsupdate"
                $akcja = $czesci[2] ?? 'index';        // Domyślnie "index"

                $classTerminal = "\\Phoenix\\Terminal\\Controller\\{$subNamespace}\\{$controllerName}{$subNamespace}";
                $classApp      = "\\Phoenix\\App\\Controller\\{$subNamespace}\\{$controllerName}{$subNamespace}";
                $classCore     = "\\Phoenix\\Core\\Controller\\{$subNamespace}\\{$controllerName}{$subNamespace}";

                if (class_exists($classTerminal)) {
                    $className = $classTerminal;
                } elseif (class_exists($classApp)) {
                    $className = $classApp;
                } else {
                    $className = $classCore;
                }
            }

            if (class_exists($className) && method_exists($className, $akcja)) {
                return $this->executeHandler([$className, $akcja], $request);
            }

            return new Response(404, ['Content-Type' => 'application/json'], json_encode([
                'status' => 'ERROR',
                'message' => "Endpoint [{$typ}] not found or method [{$akcja}] missing in class {$className}."
            ]));
        }

        // 3. Obsługa WIDOKÓW (z automatyczną detekcją Kontrolera)
        $viewUri = ($typ === 'view') ? implode('/', array_slice($czesci, 1)) : $uri;
        
        // Zgłoszenie na stronę główną lub podstronę
        if ($viewUri === '') {
            $viewName = 'index';
            $viewFile = $this->viewsPath . '/index.phtml';
            
            if (!file_exists($viewFile)) {
                $viewFile = $this->viewsPath . '/welcome.phtml';
            }
        } else {
            $viewName = $viewUri;
            $viewFile = $this->viewsPath . '/' . $viewUri . '.phtml';
        }

        // A. PRIORYTET: Szukamy najpierw klasy w dedykowanej aplikacji (\Phoenix\Terminal), potom w bazie (\Phoenix\App), na końcu w silniku (\Phoenix\Core)
        $segments = explode('/', $viewName);
        $formattedSegments = array_map(fn($s) => ucfirst($s), $segments);
        $relativeClass = implode('\\', $formattedSegments) . "Controller";

        $classTerminal = "\\Phoenix\\Terminal\\Controller\\" . $relativeClass;
        $classApp      = "\\Phoenix\\App\\Controller\\" . $relativeClass;
        $classCore     = "\\Phoenix\\Core\\Controller\\" . $relativeClass;

        if (class_exists($classTerminal)) {
            $controllerClass = $classTerminal;
        } elseif (class_exists($classApp)) {
            $controllerClass = $classApp;
        } else {
            $controllerClass = $classCore;
        }

        if (class_exists($controllerClass) && method_exists($controllerClass, 'index')) {
            return $this->executeHandler([$controllerClass, 'index'], $request);
        }

        // B. FALLBACK: Jeśli kontrolera brak, ale istnieje sam widok .phtml — renderujemy widok
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

        // 2. Obsługa plików widoków (.phtml) - widoki widzą zmienną $db
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
                    // Wywołanie metody kontrolera – jeśli wyrzuci błąd lub wyjątek,
                    // poleci on bezpośrednio do globalnego handlera z Bootstrap.php
                    $result = $controller->$method($request);
                    if ($result instanceof ResponseInterface) return $result;
                    return new Response(200, [], (string)$result);
                }
            }
        }

        // Błąd konfiguracji samego routera (np. brakująca klasa lub metoda)
        return new Response(500, [], '<h1>500 - Invalid Handler Configuration</h1>');
    }
}