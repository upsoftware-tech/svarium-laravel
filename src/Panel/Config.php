<?php

namespace Upsoftware\Svarium\Panel;

use Illuminate\Support\Arr;

class Config
{
    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public static function make(array $items = []): static
    {
        return new static($items);
    }

    public static function add(string $key, mixed $value): array
    {
        $config = [];
        Arr::set($config, $key, $value);

        return $config;
    }

    public function set(string $key, mixed $value): static
    {
        Arr::set($this->items, $key, $value);

        return $this;
    }

    public function merge(array|self $config): static
    {
        $items = $config instanceof self ? $config->toArray() : $config;
        $this->items = array_replace_recursive($this->items, $items);

        return $this;
    }

    public function toArray(): array
    {
        return $this->items;
    }
}

