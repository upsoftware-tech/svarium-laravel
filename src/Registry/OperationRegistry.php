<?php

namespace Upsoftware\Svarium\Registry;


class OperationRegistry
{
    protected array $map = [];

    public function register(string $operation): void
    {
        $this->map[$operation::uri()] = $operation;
    }

    public function resolve(string $path): ?string
    {
        return $this->map[$path] ?? null;
    }
}
