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
    private string $coreViewsPath;

    public function __construct(string $viewsPath, ?string $coreViewsPath = null)
    {
        $this->viewsPath = rtrim($viewsPath, '/');
        $this->coreViewsPath = $coreViewsPath ? rtrim($coreViewsPath, '/') : dirname(__DIR__) . '/views';
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
        $isExplicitLayer = false;

        if (in_array($firstSegment, ['core', 'app'])) {
            $layer = ucfirst($firstSegment); // "Core" lub "App"
            $isExplicitLayer = true;         // Wymuszono jawnie warstwę w URL
            array_shift($czesci);            // Usuwamy prefiks warstwy z dalszego przetwarzania
        } else {
            $layer = 'Terminal';             // Domyślna warstwa aplikacji
        }

        if (empty($czesci)) {
            $czesci = ['index'];
        }

        // PRZELICZENIE $typ PO PRZESUNIĘCIU PREFIKSU
        $typ = strtolower($czesci[0]);

        // 3. Dynamiczna obsługa AKCJI/API/PLIKÓW/DANYCH (/action/..., /api/..., /file/..., /data/...)
        if (in_array($typ, ['action', 'api', 'file', 'data']) && isset($czesci[1])) {
            $subNamespace   = ucfirst($typ);                // "Action", "Api", "File", "Data"
            $controllerName = ucfirst($czesci[1]);          // "Logout", "Update", "Status", "TickerSzukaj"
            $akcja          = !empty($czesci[2]) ? trim($czesci[2]) : 'index';

            // Konstruujemy klasę dla wyznaczonej warstwy
            $className = "\\Phoenix\\{$layer}\\Controller\\{$subNamespace}\\{$controllerName}{$subNamespace}";

            // Jeśli nie podano jawnie warstwy w URL i nie znaleziono w Terminal, sprawdzamy Core
            if (!class_exists($className) && !$isExplicitLayer && $layer === 'Terminal') {
                $className = "\\Phoenix\\Core\\Controller\\{$subNamespace}\\{$controllerName}{$subNamespace}";
            }

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

        // Wyznaczenie ścieżek pliku widoku: w aplikacji oraz w silniku Core
        $appViewFile  = $this->viewsPath . '/' . $viewUri . '.phtml';
        $coreViewFile = $this->coreViewsPath . '/' . $viewUri . '.phtml';

        // A. KASKADA KONTROLERA WIDOKU:
        // 1. Szukamy w domyślnej/wskazanej warstwie (np. Terminal)
        $formattedSegments = array_map(fn($s) => ucfirst($s), $viewUriParts);
        $relativeClass     = implode('\\', $formattedSegments) . "Controller";
        $controllerClass   = "\\Phoenix\\{$layer}\\Controller\\" . $relativeClass;

        // 2. Jeśli w Terminal brak kontrolera i nie wymuszono warstwy w URL -> szukamy w Core
        if (!class_exists($controllerClass) && !$isExplicitLayer && $layer === 'Terminal') {
            $controllerClass = "\\Phoenix\\Core\\Controller\\" . $relativeClass;
        }

        if (class_exists($controllerClass) && method_exists($controllerClass, 'index')) {
            return $this->executeHandler([$controllerClass, 'index'], $request);
        }

        // B. KASKADA PLIKÓW WIDOKU (.phtml):
        // Najpierw szukamy w Aplikacji (pt), potem w Silniku (pphpc)
        if (file_exists($appViewFile)) {
            return $this->executeHandler($appViewFile, $request);
        }

        if (file_exists($coreViewFile)) {
            return $this->executeHandler($coreViewFile, $request);
        }

        // 5. Całkowity brak dopasowania
        if (in_array($typ, ['api', 'action', 'data'])) {
            return new Response(404, ['Content-Type' => 'application/json; charset=utf-8'], json_encode([
                'status' => 'ERROR', 
                'message' => "404 - Endpoint Not Found in layer {$layer}"
            ]));
        }
        
        $errorCode = 404;
        ob_start();

        $errorViewApp = $this->viewsPath . '/error.phtml';
        $errorViewCore = $this->coreViewsPath . '/error.phtml';

        if (file_exists($errorViewApp)) {
            require $errorViewApp;
        } elseif (file_exists($errorViewCore)) {
            require $errorViewCore;
        } else {
            echo '<h1>404 - Not Found</h1>';
        }

        $content = ob_get_clean();
        return new Response(404, [], $content);
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

        $errorCode = 500;
        ob_start();

        $errorViewApp = $this->viewsPath . '/error.phtml';
        $errorViewCore = $this->coreViewsPath . '/error.phtml';

        if (file_exists($errorViewApp)) {
            require $errorViewApp;
        } elseif (file_exists($errorViewCore)) {
            require $errorViewCore;
        } else {
            echo '<h1>500 - Invalid Handler Configuration</h1>';
        }

        $content = ob_get_clean();
        return new Response(500, [], $content);
    }
}