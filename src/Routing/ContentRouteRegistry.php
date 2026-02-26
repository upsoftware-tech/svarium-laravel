<?php

namespace Upsoftware\Svarium\Routing;

class ContentRouteRegistry
{
    protected array $routes = [];

    public function register(string $route, int $priority = 0): void
    {
        $this->routes[] = [
            'class' => $route,
            'priority' => $priority,
        ];
    }

    public function all(): array
    {
        usort($this->routes, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        return array_column($this->routes, 'class');
    }
}
