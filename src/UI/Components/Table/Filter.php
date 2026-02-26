<?php

namespace Upsoftware\Svarium\UI\Components\Table;

class Filter
{
    protected string $field = '';

    protected ?string $label = null;

    protected string $type = 'string';

    protected string $mode = 'single';

    protected bool $ruleConfigured = false;

    protected array $operators = [];

    public static function make(string $field): static
    {
        $instance = new static;
        $instance->field = trim($field);

        return $instance;
    }

    public function label(string $label): static
    {
        $this->label = trim($label);

        return $this;
    }

    public function type(string $type): static
    {
        $this->type = trim($type);

        return $this;
    }

    public function filter(array|string ...$config): static
    {
        if (count($config) === 0) {
            return $this;
        }

        if (count($config) === 1 && is_array($config[0]) && $this->isAssocArray($config[0])) {
            return $this->filterFromAssocConfig($config[0]);
        }

        $tokens = $this->normalizeStringList($config);

        foreach ($tokens as $token) {
            $normalized = strtolower($token);

            if (in_array($normalized, ['single', 'multiple'], true)) {
                $this->mode = $normalized;
                continue;
            }

            throw new \InvalidArgumentException(
                "Invalid filter option [{$token}]. Allowed values for external filters: single, multiple."
            );
        }

        return $this;
    }

    public function multiple(bool $enabled = true): static
    {
        $this->mode = $enabled ? 'multiple' : 'single';

        return $this;
    }

    public function single(bool $enabled = true): static
    {
        $this->mode = $enabled ? 'single' : 'multiple';

        return $this;
    }

    public function filterRule(array|string ...$rules): static
    {
        $this->ruleConfigured = true;

        if (count($rules) === 0) {
            $this->operators = [];

            return $this;
        }

        $this->operators = $this->normalizeOperatorsList($rules);

        return $this;
    }

    public function operators(array $operators): static
    {
        $this->ruleConfigured = true;
        $this->operators = $this->normalizeOperatorsList($operators);

        return $this;
    }

    public function toArray(string $defaultAppearance = 'drawer'): array
    {
        if ($this->field === '') {
            throw new \InvalidArgumentException('Filter field is required.');
        }

        $appearance = 'drawer';

        $mode = strtolower(trim($this->mode));
        if (! in_array($mode, ['single', 'multiple'], true)) {
            $mode = 'single';
        }

        $ruleEnabled = $this->ruleConfigured || $mode === 'multiple';
        $operators = $ruleEnabled
            ? (! empty($this->operators) ? $this->operators : $this->defaultOperatorsForType($this->type))
            : [];

        return [
            'field' => $this->field,
            'label' => $this->label ?? ucfirst($this->field),
            'type' => $this->type !== '' ? $this->type : 'string',
            'appearance' => $appearance,
            'mode' => $mode,
            'multiple' => $mode === 'multiple',
            'rule' => $ruleEnabled,
            'operators' => $this->toOperatorOptions($operators),
        ];
    }

    protected function defaultOperatorsForType(string $type): array
    {
        return match (strtolower($type)) {
            'number', 'numeric', 'int', 'integer', 'float', 'decimal' => [
                '=', '!=', '>', '>=', '<', '<=', 'between', 'in', 'not_in', 'is_null', 'is_not_null',
            ],
            'date', 'datetime', 'timestamp' => [
                '=', '!=', 'after', 'before', 'between', 'is_null', 'is_not_null',
            ],
            'bool', 'boolean' => [
                '=', '!=', 'is_null', 'is_not_null',
            ],
            default => [
                'contains', 'starts_with', 'ends_with', '=', '!=', 'is_null', 'is_not_null', 'is_empty', 'is_not_empty',
            ],
        };
    }

    protected function normalizeStringList(array $items): array
    {
        $flattened = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                foreach ($item as $nestedItem) {
                    if (is_string($nestedItem) && trim($nestedItem) !== '') {
                        $flattened[] = trim($nestedItem);
                    }
                }

                continue;
            }

            if (is_string($item) && trim($item) !== '') {
                $flattened[] = trim($item);
            }
        }

        return array_values(array_unique($flattened));
    }

    protected function normalizeOperatorsList(array $operators): array
    {
        return array_values(array_unique(array_map(
            fn (string $operator) => $this->normalizeOperatorName($operator),
            $this->normalizeStringList($operators)
        )));
    }

    protected function normalizeOperatorName(string $operator): string
    {
        $normalized = strtolower(trim($operator));

        return match ($normalized) {
            'start_with' => 'starts_with',
            'end_with' => 'ends_with',
            'not_start_with' => 'not_starts_with',
            'not_end_with' => 'not_ends_with',
            default => $normalized,
        };
    }

    protected function toOperatorOptions(array $operators): array
    {
        return array_map(
            fn (string $operator) => [
                'value' => $operator,
                'label' => $this->operatorLabel($operator),
            ],
            $operators
        );
    }

    protected function operatorLabel(string $operator): string
    {
        return match ($operator) {
            'contains' => __('contains'),
            'starts_with' => __('starts with'),
            'ends_with' => __('ends with'),
            'not_starts_with' => __('does not start with'),
            'not_ends_with' => __('does not end with'),
            '=' => __('is equal to'),
            '!=' => __('is not equal to'),
            '>' => __('is greater than'),
            '>=' => __('is greater than or equal to'),
            '<' => __('is less than'),
            '<=' => __('is less than or equal to'),
            'between' => __('between'),
            'in' => __('in'),
            'not_in' => __('not in'),
            'after' => __('after'),
            'before' => __('before'),
            'is_null' => __('is null'),
            'is_not_null' => __('is not null'),
            'is_empty' => __('is empty'),
            'is_not_empty' => __('is not empty'),
            default => __(str_replace('_', ' ', $operator)),
        };
    }

    protected function isAssocArray(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    protected function filterFromAssocConfig(array $config): static
    {
        if (isset($config['label']) && is_string($config['label'])) {
            $this->label = trim($config['label']);
        }

        if (isset($config['type']) && is_string($config['type'])) {
            $this->type = trim($config['type']);
        }

        if (isset($config['appearance'])) {
            throw new \InvalidArgumentException(
                'External filters in filters() are always rendered in drawer and do not support appearance.'
            );
        }

        if (isset($config['mode']) && is_string($config['mode'])) {
            $mode = strtolower(trim($config['mode']));
            if (in_array($mode, ['single', 'multiple'], true)) {
                $this->mode = $mode;
            }
        }

        if (isset($config['multiple']) && is_bool($config['multiple'])) {
            $this->mode = $config['multiple'] ? 'multiple' : 'single';
        }

        if (isset($config['operators']) && is_array($config['operators'])) {
            $this->ruleConfigured = true;
            $this->operators = $this->normalizeOperatorsList($config['operators']);
        }

        return $this;
    }
}
