<?php

namespace Upsoftware\Svarium\Resources\Schemas;

abstract class Schema
{
    abstract public function schema(): array;

    public static function make(): static
    {
        return new static();
    }

    public function render(): array {
        return collect($this->schema())->map->toArray()->all();
    }
}
