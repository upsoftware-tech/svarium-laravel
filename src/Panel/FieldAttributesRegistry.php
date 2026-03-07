<?php

namespace Upsoftware\Svarium\Panel;

class FieldAttributesRegistry
{
    protected array $lockedInputAttributes = [];

    protected array $lockedColumnAttributes = [];

    protected array $baseInputAttributes = [];

    protected array $baseColumnAttributes = [];

    protected array $inputAttributes = [];

    protected array $columnAttributes = [];

    public function addLockedDefinitions(array $definitions): void
    {
        foreach ($definitions as $field => $definition) {
            if (! is_string($field) || trim($field) === '') {
                continue;
            }

            $normalizedField = trim($field);
            $normalizedDefinition = $this->normalizeDefinition($definition);

            if ($normalizedDefinition === null) {
                continue;
            }

            $this->lockedInputAttributes[$normalizedField] = [
                ...($this->lockedInputAttributes[$normalizedField] ?? []),
                ...$normalizedDefinition['input'],
            ];

            $this->lockedColumnAttributes[$normalizedField] = [
                ...($this->lockedColumnAttributes[$normalizedField] ?? []),
                ...$normalizedDefinition['column'],
            ];
        }
    }

    public function addDefinitions(array $definitions): void
    {
        foreach ($definitions as $field => $definition) {
            if (! is_string($field) || trim($field) === '') {
                continue;
            }

            $normalizedField = trim($field);
            $normalizedDefinition = $this->normalizeDefinition($definition);

            if ($normalizedDefinition === null) {
                continue;
            }

            $this->baseInputAttributes[$normalizedField] = [
                ...($this->baseInputAttributes[$normalizedField] ?? []),
                ...$normalizedDefinition['input'],
            ];

            $this->baseColumnAttributes[$normalizedField] = [
                ...($this->baseColumnAttributes[$normalizedField] ?? []),
                ...$normalizedDefinition['column'],
            ];
        }

        $this->clear();
    }

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

            $this->inputAttributes[$normalizedField] = [
                ...($this->inputAttributes[$normalizedField] ?? []),
                ...$normalizedDefinition['input'],
            ];

            $this->columnAttributes[$normalizedField] = [
                ...($this->columnAttributes[$normalizedField] ?? []),
                ...$normalizedDefinition['column'],
            ];
        }
    }

    public function clear(): void
    {
        $this->inputAttributes = $this->baseInputAttributes;
        $this->columnAttributes = $this->baseColumnAttributes;
    }

    public function clearAll(): void
    {
        $this->lockedInputAttributes = [];
        $this->lockedColumnAttributes = [];
        $this->baseInputAttributes = [];
        $this->baseColumnAttributes = [];
        $this->inputAttributes = [];
        $this->columnAttributes = [];
    }

    public function input(string $name): array
    {
        return [
            ...($this->inputAttributes[$name] ?? []),
            ...($this->lockedInputAttributes[$name] ?? []),
        ];
    }

    public function column(string $name): array
    {
        return [
            ...($this->columnAttributes[$name] ?? []),
            ...($this->lockedColumnAttributes[$name] ?? []),
        ];
    }

    public function columnAttributes(): array
    {
        $keys = array_values(array_unique(array_merge(
            array_keys($this->columnAttributes),
            array_keys($this->lockedColumnAttributes)
        )));

        $resolved = [];

        foreach ($keys as $key) {
            $resolved[$key] = $this->column($key);
        }

        return $resolved;
    }

    protected function normalizeDefinition(mixed $definition): ?array
    {
        if (
            is_string($definition)
            || is_int($definition)
            || is_float($definition)
            || is_bool($definition)
        ) {
            $definition = ['label' => (string) $definition];
        } elseif (is_object($definition) && method_exists($definition, '__toString')) {
            $definition = ['label' => (string) $definition];
        }

        if (! is_array($definition)) {
            return null;
        }

        if (array_key_exists(0, $definition) && ! array_key_exists('label', $definition)) {
            $candidateLabel = $definition[0];
            if (is_scalar($candidateLabel) || (is_object($candidateLabel) && method_exists($candidateLabel, '__toString'))) {
                $definition['label'] = (string) $candidateLabel;
            }
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
