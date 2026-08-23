<?php

declare(strict_types=1);

namespace App\Http\Routes;

class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function put(string $path, callable $handler): void
    {
        $this->routes['PUT'][$path] = $handler;
    }

    public function delete(string $path, callable $handler): void
    {
        $this->routes['DELETE'][$path] = $handler;
    }

    public function resolve(string $method, string $path): mixed
    {
        if (isset($this->routes[$method][$path])) {
            return $this->routes[$method][$path]();
        }

        foreach ($this->routes[$method] ?? [] as $route => $handler) {
            $params = $this->matchRoute($route, $path);

            if ($params !== null) {
                return $handler(...$params);
            }
        }

        $allowedMethods = $this->allowedMethodsForPath($path);

        if ($allowedMethods !== []) {
            http_response_code(405);
            header('Allow: ' . implode(', ', $allowedMethods));
            return ['error' => 'Método não permitido'];
        }

        http_response_code(404);
        return ['error' => 'Rota não encontrada'];
    }

    /** @return list<string> */
    private function allowedMethodsForPath(string $path): array
    {
        $allowed = [];

        foreach ($this->routes as $method => $routes) {
            if (isset($routes[$path])) {
                $allowed[] = $method;
                continue;
            }

            foreach ($routes as $route => $handler) {
                if ($this->matchRoute($route, $path) !== null) {
                    $allowed[] = $method;
                    break;
                }
            }
        }

        return $allowed;
    }

    /** @return list<string>|null */
    private function matchRoute(string $route, string $path): ?array
    {
        $routeParts = explode('/', trim($route, '/'));
        $pathParts = explode('/', trim($path, '/'));

        if (count($routeParts) !== count($pathParts)) {
            return null;
        }

        $params = [];

        foreach ($routeParts as $i => $part) {
            if (preg_match('/^\{(\w+)(?::(\w+))?\}$/', $part, $matches) === 1) {
                $constraint = $matches[2] ?? null;
                $value = $pathParts[$i];

                if (!$this->matchesConstraint($constraint, $value)) {
                    return null;
                }

                $params[] = $value;
                continue;
            }

            if ($part !== $pathParts[$i]) {
                return null;
            }
        }

        return $params;
    }

    private function matchesConstraint(?string $constraint, string $value): bool
    {
        return match ($constraint) {
            null => true,
            'uuid' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1,
            default => false,
        };
    }
}
