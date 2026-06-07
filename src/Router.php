<?php
// pphpc/src/Router.php

namespace Phoenix\Core;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

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

        if (isset($this->routes[$method][$uri])) {
            return $this->executeHandler($this->routes[$method][$uri], $request);
        }

        // Automatyczny fallback dla migracji Twoich starych plików
        $autoFile = $this->viewsPath . '/' . ($uri === '' ? 'index' : $uri) . '.php';
        if (file_exists($autoFile)) {
            return $this->executeHandler($autoFile, $request);
        }

        return new \Nyholm\Psr7\Response(404, [], '<h1>404 - Not Found (Phoenix Core)</h1>');
    }

    private function executeHandler(mixed $handler, ServerRequestInterface $request): ResponseInterface
    {
        if (is_callable($handler)) {
            $result = $handler($request);
            if ($result instanceof ResponseInterface) return $result;
            return new \Nyholm\Psr7\Response(200, [], (string)$result);
        }

        if (is_string($handler) && file_exists($handler)) {
            ob_start();
            require $handler;
            $content = ob_get_clean();
            return new \Nyholm\Psr7\Response(200, [], $content);
        }

        if (is_array($handler)) {
            [$controllerClass, $method] = $handler;
            $controller = new $controllerClass();
            $result = $controller->$method($request);
            if ($result instanceof ResponseInterface) return $result;
            return new \Nyholm\Psr7\Response(200, [], (string)$result);
        }

        return new \Nyholm\Psr7\Response(500, [], '<h1>500 - Invalid Handler</h1>');
    }
}