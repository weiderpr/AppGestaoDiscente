<?php
/**
 * Vértice Acadêmico — Router
 */

namespace Core;

class Router {
    private array $routes = [];
    private array $namedRoutes = [];

    public function get(string $path, callable|array $handler, string $name = ''): self {
        return $this->addRoute('GET', $path, $handler, $name);
    }

    public function post(string $path, callable|array $handler, string $name = ''): self {
        return $this->addRoute('POST', $path, $handler, $name);
    }

    public function put(string $path, callable|array $handler, string $name = ''): self {
        return $this->addRoute('PUT', $path, $handler, $name);
    }

    public function delete(string $path, callable|array $handler, string $name = ''): self {
        return $this->addRoute('DELETE', $path, $handler, $name);
    }

    private function addRoute(string $method, string $path, callable|array $handler, string $name): self {
        $this->routes[$method][$path] = $handler;
        
        if ($name) {
            $this->namedRoutes[$name] = ['method' => $method, 'path' => $path];
        }

        return $this;
    }

    public function dispatch(string $method, string $uri): void {
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/') ?: '/';

        if (isset($this->routes[$method][$uri])) {
            $handler = $this->routes[$method][$uri];
            $this->executeHandler($handler);
            return;
        }

        foreach ($this->routes[$method] ?? [] as $path => $handler) {
            $params = $this->matchRoute($path, $uri);
            if ($params !== false) {
                $this->executeHandler($handler, $params);
                return;
            }
        }

        $this->notFound();
    }

    private function matchRoute(string $pattern, string $uri): array|false {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $uri, $matches)) {
            return array_filter($matches, fn($key) => !is_numeric($key), ARRAY_FILTER_USE_KEY);
        }

        return false;
    }

    private function executeHandler(callable|array $handler, array $params = []): void {
        if (is_callable($handler)) {
            $handler($params);
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$controller, $action] = $handler;
            
            if (is_string($controller)) {
                $controller = new $controller();
            }

            if (method_exists($controller, $action)) {
                $controller->$action($params);
                return;
            }
        }

        http_response_code(500);
        echo 'Erro: Handler inválido';
    }

    private function notFound(): void {
        http_response_code(404);
        echo '404 - Página não encontrada';
    }

    public function route(string $name, array $params = []): string {
        if (!isset($this->namedRoutes[$name])) {
            return '#';
        }

        $path = $this->namedRoutes[$name]['path'];
        
        foreach ($params as $key => $value) {
            $path = str_replace("{{$key}}", $value, $path);
            $path = str_replace("{{$key}}", urlencode($value), $path);
        }

        return $path;
    }

    public function redirect(string $uri): void {
        header("Location: {$uri}");
        exit;
    }
}
