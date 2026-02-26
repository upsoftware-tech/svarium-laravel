<?php

namespace Upsoftware\Svarium\Modules;

class ActivationRegistry
{
    protected array $enabledModules = [];

    public function enable(string $moduleClass): void
    {
        $this->enabledModules[$moduleClass] = true;
    }

    public function disable(string $moduleClass): void
    {
        unset($this->enabledModules[$moduleClass]);
    }

    public function isEnabled(string $moduleClass): bool
    {
        return isset($this->enabledModules[$moduleClass]);
    }

    public function all(): array
    {
        return array_keys($this->enabledModules);
    }
}
