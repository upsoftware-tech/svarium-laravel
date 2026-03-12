<?php

namespace Upsoftware\Svarium\Roles;

use Closure;

class RoleParameterRegistry
{
    protected array $definitions = [];

    public function clear(): void
    {
        $this->definitions = [];
    }

    public function register(string $key, array $definition, ?string $source = null): void
    {
        $normalizedKey = trim($key);

        if ($normalizedKey === '') {
            return;
        }

        $this->definitions[$normalizedKey] = [
            'key' => $normalizedKey,
            'label' => $definition['label'] ?? $normalizedKey,
            'description' => $definition['description'] ?? null,
            'options' => $definition['options'] ?? [],
            'setting_key' => $definition['setting_key'] ?? $normalizedKey,
            'source' => $source ?? ($definition['source'] ?? null),
        ];
    }

    public function registerMany(array $definitions, ?string $source = null): void
    {
        foreach ($definitions as $key => $definition) {
            if (! is_string($key) || ! is_array($definition)) {
                continue;
            }

            $this->register($key, $definition, $source);
        }
    }

    public function all(): array
    {
        $resolved = [];

        foreach ($this->definitions as $key => $definition) {
            $resolved[$key] = [
                ...$definition,
                'options' => $this->resolveOptions($definition['options'] ?? []),
            ];
        }

        return $resolved;
    }

    protected function resolveOptions(mixed $options): array
    {
        if ($options instanceof Closure) {
            $options = $options();
        }

        return is_array($options) ? $options : [];
    }
}
