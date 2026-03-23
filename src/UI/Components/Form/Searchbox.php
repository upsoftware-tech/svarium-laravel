<?php

namespace Upsoftware\Svarium\UI\Components\Form;

use Illuminate\Contracts\Support\Arrayable;
use Upsoftware\Svarium\Support\ModelOptionsBuilder;
use Upsoftware\Svarium\UI\Components\Repeater;

class Searchbox extends Repeater
{
    public function mode(string $mode): static
    {
        $normalized = strtolower(trim($mode));
        if (! in_array($normalized, ['select', 'list'], true)) {
            return $this;
        }

        return $this->prop('searchMode', $normalized);
    }

    public function selectMode(): static
    {
        return $this->mode('select');
    }

    public function listMode(): static
    {
        return $this->mode('list');
    }

    public function autocomplete(string|bool $autocomplete = true): static
    {
        if (is_bool($autocomplete)) {
            return $this->prop('autocomplete', $autocomplete);
        }

        $normalized = strtolower(trim($autocomplete));

        if ($normalized === 'off' || $normalized === 'false' || $normalized === '0') {
            $this->prop('autocomplete', false);
        } elseif ($normalized === 'on' || $normalized === 'true' || $normalized === '1') {
            $this->prop('autocomplete', true);
        } elseif ($normalized !== '') {
            // Keep compatibility with native HTML autocomplete value if someone passes it explicitly.
            parent::autocomplete($autocomplete);
            $this->prop('autocomplete', true);
        }

        return $this;
    }

    public function dependsOn(
        string $field,
        ?string $optionField = null,
        bool $clearOnChange = true,
        bool $showWhenEmpty = false,
    ): static {
        return $this->appendDependency(
            $field,
            $optionField,
            $clearOnChange,
            $showWhenEmpty,
            false
        );
    }

    public function dependsOnOptional(
        string $field,
        ?string $optionField = null,
        bool $clearOnChange = true,
        bool $includeNull = false,
    ): static {
        return $this->appendDependency(
            $field,
            $optionField,
            $clearOnChange,
            true,
            $includeNull
        );
    }

    public function where(
        string $field,
        ?string $optionField = null,
        bool $clearOnChange = true,
        bool $showWhenEmpty = false,
    ): static {
        return $this->dependsOn($field, $optionField, $clearOnChange, $showWhenEmpty);
    }

    public function whereOptional(
        string $field,
        ?string $optionField = null,
        bool $clearOnChange = true,
        bool $includeNull = false,
    ): static {
        return $this->dependsOnOptional($field, $optionField, $clearOnChange, $includeNull);
    }

    public function options(array|Arrayable|ModelOptionsBuilder $options): static
    {
        if ($options instanceof ModelOptionsBuilder || $options instanceof Arrayable) {
            $options = $options->toArray();
        }

        return $this->prop('options', $this->normalizeOptions($options));
    }

    public function optionsModel(
        string $modelClass,
        string $value = 'id',
        string $label = 'name',
        string|array|null $orderBy = null
    ): static {
        $builder = new ModelOptionsBuilder($modelClass, $value, $label);
        $orders = [];

        if (is_string($orderBy) && trim($orderBy) !== '') {
            $column = trim($orderBy);
            $builder->orderBy($column);
            $orders[] = ['column' => $column, 'direction' => 'asc'];
        }

        if (is_array($orderBy)) {
            if (array_is_list($orderBy) && isset($orderBy[0]) && is_string($orderBy[0])) {
                $column = trim((string) $orderBy[0]);
                $direction = isset($orderBy[1]) ? (string) $orderBy[1] : 'asc';

                if ($column !== '') {
                    $builder->orderBy($column, $direction);
                    $orders[] = [
                        'column' => $column,
                        'direction' => strtolower(trim($direction)) === 'desc' ? 'desc' : 'asc',
                    ];
                }
            } else {
                foreach ($orderBy as $column => $direction) {
                    if (is_int($column)) {
                        if (is_string($direction) && trim($direction) !== '') {
                            $normalized = trim($direction);
                            $builder->orderBy($normalized);
                            $orders[] = ['column' => $normalized, 'direction' => 'asc'];
                        }
                        continue;
                    }

                    $normalizedColumn = trim((string) $column);
                    if ($normalizedColumn === '') {
                        continue;
                    }

                    $normalizedDirection = strtolower(trim((string) $direction)) === 'desc' ? 'desc' : 'asc';
                    $builder->orderBy($normalizedColumn, $normalizedDirection);
                    $orders[] = [
                        'column' => $normalizedColumn,
                        'direction' => $normalizedDirection,
                    ];
                }
            }
        }

        $limit = (int) ($this->getProp('limit') ?? config('upsoftware.form.select_options.limit', 200));
        $limit = max(1, min($limit, 500));

        $this->prop('optionsModel', [
            'model' => $modelClass,
            'value' => trim($value) !== '' ? $value : 'id',
            'label' => trim($label) !== '' ? $label : 'name',
            'orders' => $orders,
            'endpoint' => $this->resolveModelOptionsEndpoint(),
            'limit' => $limit,
        ]);

        $autocomplete = (bool) ($this->getProp('autocomplete') ?? true);
        $hasDependencies = is_array($this->getProp('dependsOn'));
        $useRemote = $autocomplete || $hasDependencies;

        $this->prop('optionsRemote', $useRemote);

        if ($useRemote) {
            $this->prop('options', []);

            return $this;
        }

        return $this->options($builder->limit($limit));
    }

    public function limit(int $limit): static
    {
        $limit = max(1, min($limit, 500));
        $this->prop('limit', $limit);

        $optionsModel = $this->getProp('optionsModel');
        if (is_array($optionsModel)) {
            $optionsModel['limit'] = $limit;
            $this->prop('optionsModel', $optionsModel);
        }

        return $this;
    }

    public function minLetter(int $count): static
    {
        return $this->prop('minLetter', max(0, $count));
    }

    public function valueKey(string $key): static
    {
        $normalized = trim($key);
        if ($normalized === '') {
            $normalized = 'id';
        }

        return $this->prop('valueKey', $normalized);
    }

    public function labelKey(string $key): static
    {
        $normalized = trim($key);
        if ($normalized === '') {
            $normalized = 'name';
        }

        return $this->prop('labelKey', $normalized);
    }

    public function unique(bool $enabled = true): static
    {
        return $this->prop('unique', $enabled);
    }

    public function placeholder(string $placeholder): static
    {
        return $this->prop('placeholder', trim($placeholder));
    }

    public function toArray(): array
    {
        $autocomplete = $this->getProp('autocomplete');
        if (is_string($autocomplete)) {
            $normalized = strtolower(trim($autocomplete));
            $autocomplete = ! ($normalized === 'off' || $normalized === 'false' || $normalized === '0');
        }

        $this->prop('mode', 'searchbox');
        $this->prop('searchMode', strtolower(trim((string) ($this->getProp('searchMode') ?? 'select'))) === 'list' ? 'list' : 'select');
        $this->prop('autocomplete', is_bool($autocomplete) ? $autocomplete : true);
        $this->prop('limit', max(1, (int) ($this->getProp('limit') ?? config('upsoftware.form.select_options.limit', 20))));
        $this->prop('minLetter', max(0, (int) ($this->getProp('minLetter') ?? 2)));
        $this->prop('valueKey', trim((string) ($this->getProp('valueKey') ?? 'id')) ?: 'id');
        $this->prop('labelKey', trim((string) ($this->getProp('labelKey') ?? 'name')) ?: 'name');
        $this->prop('unique', (bool) ($this->getProp('unique') ?? true));
        $this->prop('placeholder', trim((string) ($this->getProp('placeholder') ?? __('Search'))));

        return parent::toArray();
    }

    protected function normalizeOptions(array $options): array
    {
        if ($options === []) {
            return [];
        }

        $normalized = [];

        if (array_is_list($options)) {
            foreach ($options as $option) {
                if (is_array($option) && isset($option['items']) && is_array($option['items'])) {
                    $normalized[] = [
                        'label' => (string) ($option['label'] ?? ''),
                        'items' => $this->normalizeOptions($option['items']),
                    ];
                    continue;
                }

                if (is_array($option)) {
                    $value = $option['value'] ?? $option['id'] ?? $option['key'] ?? null;
                    $label = $option['label'] ?? $option['name'] ?? $option['title'] ?? $value;

                    if ($value === null) {
                        continue;
                    }

                    $normalized[] = [
                        ...$option,
                        'value' => $value,
                        'label' => is_scalar($label) ? (string) $label : (string) $value,
                    ];
                    continue;
                }

                if (is_scalar($option)) {
                    $normalized[] = [
                        'value' => $option,
                        'label' => (string) $option,
                    ];
                }
            }

            return $normalized;
        }

        foreach ($options as $value => $label) {
            if (is_array($label) && isset($label['items']) && is_array($label['items'])) {
                $normalized[] = [
                    'label' => (string) ($label['label'] ?? $value),
                    'items' => $this->normalizeOptions($label['items']),
                ];
                continue;
            }

            if (is_array($label)) {
                $itemValue = $label['value'] ?? $value;
                $itemLabel = $label['label'] ?? $label['name'] ?? $itemValue;

                $normalized[] = [
                    ...$label,
                    'value' => $itemValue,
                    'label' => is_scalar($itemLabel) ? (string) $itemLabel : (string) $itemValue,
                ];
                continue;
            }

            $normalized[] = [
                'value' => $value,
                'label' => is_scalar($label) ? (string) $label : (string) $value,
            ];
        }

        return $normalized;
    }

    protected function resolveModelOptionsEndpoint(): string
    {
        $configured = trim((string) config('upsoftware.form.select_options.path', 'svarium/form/options/model'), '/');
        if ($configured === '') {
            return '/svarium/form/options/model';
        }

        return '/'.$configured;
    }

    protected function appendDependency(
        string $field,
        ?string $optionField,
        bool $clearOnChange,
        bool $showWhenEmpty,
        bool $includeNull,
    ): static {
        $field = trim($field);
        if ($field === '') {
            return $this;
        }

        $resolvedOptionField = trim((string) ($optionField ?? ''));
        if ($resolvedOptionField === '') {
            $resolvedOptionField = $field;
        }

        $dependency = [
            'field' => $field,
            'optionField' => $resolvedOptionField,
            'clearOnChange' => $clearOnChange,
            'showWhenEmpty' => $showWhenEmpty,
            'includeNull' => $includeNull,
        ];

        $existing = $this->getProp('dependsOn');
        $dependencies = [];

        if (is_string($existing) && trim($existing) !== '') {
            $dependencies[] = [
                'field' => trim($existing),
                'optionField' => trim($existing),
                'clearOnChange' => true,
                'showWhenEmpty' => false,
                'includeNull' => false,
            ];
        } elseif (is_array($existing)) {
            if (array_is_list($existing)) {
                foreach ($existing as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $existingField = trim((string) ($item['field'] ?? ''));
                    if ($existingField === '') {
                        continue;
                    }

                    $dependencies[] = [
                        'field' => $existingField,
                        'optionField' => trim((string) ($item['optionField'] ?? $existingField)),
                        'clearOnChange' => isset($item['clearOnChange']) ? (bool) $item['clearOnChange'] : true,
                        'showWhenEmpty' => isset($item['showWhenEmpty']) ? (bool) $item['showWhenEmpty'] : false,
                        'includeNull' => isset($item['includeNull']) ? (bool) $item['includeNull'] : false,
                    ];
                }
            } else {
                $existingField = trim((string) ($existing['field'] ?? ''));
                if ($existingField !== '') {
                    $dependencies[] = [
                        'field' => $existingField,
                        'optionField' => trim((string) ($existing['optionField'] ?? $existingField)),
                        'clearOnChange' => isset($existing['clearOnChange']) ? (bool) $existing['clearOnChange'] : true,
                        'showWhenEmpty' => isset($existing['showWhenEmpty']) ? (bool) $existing['showWhenEmpty'] : false,
                        'includeNull' => isset($existing['includeNull']) ? (bool) $existing['includeNull'] : false,
                    ];
                }
            }
        }

        $dependencies[] = $dependency;
        $dependencies = array_values($dependencies);

        $this->prop('dependsOn', count($dependencies) === 1 ? $dependencies[0] : $dependencies);

        if (is_array($this->getProp('optionsModel'))) {
            $this->prop('optionsRemote', true);
            $this->prop('options', []);
        }

        return $this;
    }
}
