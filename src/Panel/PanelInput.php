<?php

namespace Upsoftware\Svarium\Panel;

class PanelInput
{
    public function __construct(
        protected array $data = []
    ) {}

    public function all(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->data, array_flip($keys));
    }
}
