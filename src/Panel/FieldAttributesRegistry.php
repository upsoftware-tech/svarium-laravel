<?php

namespace Upsoftware\Svarium\Panel;

class FieldAttributesRegistry
{
    protected array $inputAttributes = [];

    protected array $columnAttributes = [];

    public function setDefinitions(array $definitions): void
    {
        $this->clear();

        foreach ($definitions as $field => $definition) {
            if (! is_string($field) || trim($field) === '') {
                continue;
            }

            $normalizedField = trim($field);
            $normalizedDefinition = $this->normalizeDefinition($definition);

            if ($normalizedDefinition === null) {
                continue;
            }

            $this->inputAttributes[$normalizedField] = $normalizedDefinition['input'];
            $this->columnAttributes[$normalizedField] = $normalizedDefinition['column'];
        }
    }

    public function clear(): void
    {
        $this->inputAttributes = [];
        $this->columnAttributes = [];
    }

    public function input(string $name): array
    {
        return $this->inputAttributes[$name] ?? [];
    }

    public function column(string $name): array
    {
        return $this->columnAttributes[$name] ?? [];
    }

    public function columnAttributes(): array
    {
        return $this->columnAttributes;
    }

    protected function normalizeDefinition(mixed $definition): ?array
    {
        if (is_string($definition)) {
            $definition = ['label' => $definition];
        }

        if (! is_array($definition)) {
            return null;
        }

        $reserved = [
            'input',
            'form',
            'column',
            'table',
            'attributes',
            'common',
        ];

        $direct = array_filter(
            $definition,
            static fn ($key) => is_string($key) && ! in_array($key, $reserved, true),
            ARRAY_FILTER_USE_KEY
        );

        $common = [];

        foreach (['attributes', 'common'] as $key) {
            if (isset($definition[$key]) && is_array($definition[$key])) {
                $common = [
                    ...$common,
                    ...$definition[$key],
                ];
            }
        }

        $common = [
            ...$common,
            ...$direct,
        ];

        $input = [];
        if (isset($definition['input']) && is_array($definition['input'])) {
            $input = $definition['input'];
        } elseif (isset($definition['form']) && is_array($definition['form'])) {
            $input = $definition['form'];
        }

        $column = [];
        if (isset($definition['column']) && is_array($definition['column'])) {
            $column = $definition['column'];
        } elseif (isset($definition['table']) && is_array($definition['table'])) {
            $column = $definition['table'];
        }

        return [
            'input' => [
                ...$common,
                ...$input,
            ],
            'column' => [
                ...$common,
                ...$column,
            ],
        ];
    }
}
