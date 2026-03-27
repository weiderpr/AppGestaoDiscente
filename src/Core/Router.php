<?php
/**
 * Vértice Acadêmico — Router
 */

namespace Core;

class Router {
    private array $routes = [];
    private array $namedRoutes = [];
    private string $currentRoutePath = '';
    private string $currentRouteMethod = '';
    private array $globalMiddlewares = [];

    public function globalMiddleware(string|object $middleware): self {
        $this->globalMiddlewares[] = $middleware;
        return $this;
    }

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
        $this->routes[$method][$path] = [
            'handler' => $handler,
            'middlewares' => [],
            'name' => $name
        ];
        
        $this->currentRouteMethod = $method;
        $this->currentRoutePath = $path;

        if ($name) {
            $this->namedRoutes[$name] = ['method' => $method, 'path' => $path];
        }

        return $this;
    }

    public function middleware(string|object $middleware): self {
        if ($this->currentRouteMethod && $this->currentRoutePath) {
            $this->routes[$this->currentRouteMethod][$this->currentRoutePath]['middlewares'][] = $middleware;
        }
        return $this;
    }

    public function dispatch(string $method, string $uri): void {
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/') ?: '/';

        if (isset($this->routes[$method][$uri])) {
            $route = $this->routes[$method][$uri];
            $this->executeChain($route['handler'], $route['middlewares'], [], $route['name']);
            return;
        }

        foreach ($this->routes[$method] ?? [] as $path => $route) {
            $params = $this->matchRoute($path, $uri);
            if ($params !== false) {
                $this->executeChain($route['handler'], $route['middlewares'], $params, $route['name']);
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

    private function executeChain(callable|array $handler, array $middlewares, array $params = [], string $name = ''): void {
        $next = function(array $params) use ($handler) {
            $this->executeHandler($handler, $params);
        };

        // Combina middlewares globais com os específicos da rota
        $allMiddlewares = array_merge($this->globalMiddlewares, $middlewares);

        foreach (array_reverse($allMiddlewares) as $middleware) {
            $prevNext = $next;
            $next = function(array $params) use ($middleware, $prevNext, $name) {
                if (is_string($middleware)) {
                    $middleware = new $middleware();
                }

                if ($middleware instanceof Middleware) {
                    // Se for RoleMiddleware, podemos passar o nome da rota se ele suportar
                    if (method_exists($middleware, 'setResource')) {
                        $middleware->setResource($name);
                    }
                    $middleware->handle($params, $prevNext);
                } else {
                    $prevNext($params);
                }
            };
        }

        $next($params);
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
