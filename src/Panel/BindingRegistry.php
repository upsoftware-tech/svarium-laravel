<?php

namespace Upsoftware\Svarium\Panel;

class BindingRegistry
{
    protected array $bindings = [];

    public function bind(string $parameter, callable $resolver): void
    {
        $this->bindings[$parameter] = $resolver;
    }

    public function resolve(string $parameter, string $value): mixed
    {
        if (!isset($this->bindings[$parameter])) {
            return $value;
        }

        return call_user_func($this->bindings[$parameter], $value);
    }
}
