<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Простой роутер с поддержкой параметров, групп и middleware.
 *
 *   $router->get('/product/{slug}', [ProductController::class, 'show']);
 *   $router->get('/order/{id:\d+}', [...]);
 *   $router->group('/admin', ['admin'], function (Router $r) { ... });
 *
 * Middleware: 'auth' — только для вошедших, 'guest' — только для гостей,
 * 'admin' — только для роли admin, 'nocsrf' — отключить проверку CSRF
 * (только для серверных callback'ов платёжных систем).
 */
final class Router
{
    private array $routes = [];
    private string $prefix = '';
    private array $groupMiddleware = [];

    public function get(string $path, array|\Closure $handler, array $middleware = []): self
    {
        return $this->add(['GET', 'HEAD'], $path, $handler, $middleware);
    }

    public function post(string $path, array|\Closure $handler, array $middleware = []): self
    {
        return $this->add(['POST'], $path, $handler, $middleware);
    }

    public function match(array $methods, string $path, array|\Closure $handler, array $middleware = []): self
    {
        return $this->add(array_map('strtoupper', $methods), $path, $handler, $middleware);
    }

    public function group(string $prefix, array $middleware, \Closure $callback): void
    {
        $previousPrefix     = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->prefix          = $previousPrefix . '/' . trim($prefix, '/');
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);

        $callback($this);

        $this->prefix          = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    private function add(array $methods, string $path, array|\Closure $handler, array $middleware): self
    {
        $full = rtrim($this->prefix . '/' . trim($path, '/'), '/');
        $full = $full === '' ? '/' : $full;

        $this->routes[] = [
            'methods'    => $methods,
            'path'       => $full,
            'regex'      => $this->compile($full),
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
        return $this;
    }

    /** Превращает "/product/{slug}" в регулярное выражение с именованными группами. */
    private function compile(string $path): string
    {
        // Шаблон параметра может содержать квантификаторы в фигурных скобках: {token:[a-f0-9]{64}}
        $regex = preg_replace_callback(
            '#\{([a-z_][a-z0-9_]*)(?::((?:[^{}]|\{[^{}]*\})+))?\}#i',
            static fn (array $m): string => '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')',
            $path
        );
        return '#^' . $regex . '$#u';
    }

    public function dispatch(Request $request): void
    {
        $path   = $request->path();
        $method = $request->method();

        // Единый вид адресов: /catalog/ -> /catalog (301)
        $raw = $request->rawPath();
        if ($method === 'GET' && strlen($raw) > 1 && str_ends_with($raw, '/') && $path !== '/') {
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            Response::redirect(url($path) . ($qs !== '' ? '?' . $qs : ''), 301);
        }

        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if (!in_array($method, $route['methods'], true)) {
                $allowed = array_merge($allowed, $route['methods']);
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $this->runMiddleware($route['middleware'], $request);
            $this->call($route['handler'], $request, $params);
            return;
        }

        if ($allowed) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            throw new HttpException(405);
        }
        throw new HttpException(404);
    }

    private function runMiddleware(array $middleware, Request $request): void
    {
        // CSRF проверяется для любого POST, если маршрут явно не исключён
        if ($request->isPost() && !in_array('nocsrf', $middleware, true)) {
            Csrf::verify($request);
        }

        foreach ($middleware as $name) {
            switch ($name) {
                case 'auth':
                    if (!Auth::check()) {
                        if ($request->wantsJson()) {
                            Response::json(['ok' => false, 'error' => 'Необходимо войти в аккаунт'], 401);
                        }
                        Session::set('auth.intended', $request->rawPath() . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']));
                        Session::flash('info', 'Войдите в аккаунт, чтобы продолжить');
                        Response::redirect(url('/login'));
                    }
                    break;

                case 'guest':
                    if (Auth::check()) {
                        Response::redirect(url('/account'));
                    }
                    break;

                case 'admin':
                    // Для посторонних админка «не существует» — отдаём 404
                    if (!Auth::check()) {
                        Session::set('auth.intended', $request->rawPath());
                        Response::redirect(url('/login'));
                    }
                    if (!Auth::isAdmin()) {
                        throw new HttpException(404);
                    }
                    break;
            }
        }
    }

    private function call(array|\Closure $handler, Request $request, array $params): void
    {
        if ($handler instanceof \Closure) {
            $result = $handler($request, ...array_values($params));
        } else {
            [$class, $action] = $handler;
            $controller = new $class($request);
            $result = $controller->{$action}(...array_values($params));
        }

        if (is_string($result)) {
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=utf-8');
            }
            echo $result;
        }
    }
}
