<?php

namespace Upsoftware\Svarium\UI\Components\Form;

use Illuminate\Contracts\Support\Arrayable;
use Upsoftware\Svarium\Support\ModelOptionsBuilder;
use Upsoftware\Svarium\UI\Concerns\Props\HasVariant;
use Upsoftware\Svarium\UI\Components\FieldComponent;

class Select extends FieldComponent
{
    use HasVariant;

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

    public function multiple(bool $enabled = true): static
    {
        return $this->prop('multiple', $enabled);
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

        $this->prop('optionsModel', [
            'model' => $modelClass,
            'value' => trim($value) !== '' ? $value : 'id',
            'label' => trim($label) !== '' ? $label : 'name',
            'orders' => $orders,
            'endpoint' => $this->resolveModelOptionsEndpoint(),
            'limit' => (int) config('upsoftware.form.select_options.limit', 200),
        ]);

        $useRemote = (bool) $this->getProp('optionsRemote', false) || is_array($this->getProp('dependsOn'));
        $this->prop('optionsRemote', $useRemote);

        if ($useRemote) {
            return $this->prop('options', []);
        }

        return $this->options($builder);
    }

    public function optionsRemote(bool $enabled = true): static
    {
        $this->prop('optionsRemote', $enabled);

        if ($enabled && is_array($this->getProp('optionsModel'))) {
            $this->prop('options', []);
        }

        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        return $this->prop('placeholder', $placeholder);
    }

    public function clear(bool $enabled = true): static
    {
        return $this->prop('clear', $enabled);
    }

    public function searchable(bool $enabled = true): static
    {
        return $this->prop('searchable', $enabled);
    }

    public function topSelect(array|Arrayable|ModelOptionsBuilder $options): static
    {
        if ($options instanceof ModelOptionsBuilder || $options instanceof Arrayable) {
            $options = $options->toArray();
        }

        return $this->prop('topSelectOptions', $this->normalizeOptions($options));
    }

    public function topSelectValue(mixed $value): static
    {
        return $this->prop('topSelectValue', $value);
    }

    public function topSelectPlaceholder(?string $placeholder): static
    {
        return $this->prop('topSelectPlaceholder', $placeholder);
    }

    public function topSelectWidth(string|int|float|null $width = 240): static
    {
        return $this->prop('topSelectWidth', $width);
    }

    public function topSelectName(?string $name): static
    {
        return $this->prop('topSelectName', $name);
    }

    public function languageSelector(bool $enabled = true): static
    {
        return $this->prop('languageSelector', $enabled);
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

            return array_values($normalized);
        }

        foreach ($options as $value => $option) {
            if (is_array($option) && isset($option['items']) && is_array($option['items'])) {
                $normalized[] = [
                    'label' => (string) ($option['label'] ?? (string) $value),
                    'items' => $this->normalizeOptions($option['items']),
                ];
                continue;
            }

            if (is_array($option)) {
                $label = $option['label'] ?? $option['name'] ?? $option['title'] ?? $value;

                $normalized[] = [
                    ...$option,
                    'value' => $option['value'] ?? $value,
                    'label' => is_scalar($label) ? (string) $label : (string) $value,
                ];
                continue;
            }

            $normalized[] = [
                'value' => $value,
                'label' => is_scalar($option) ? (string) $option : (string) $value,
            ];
        }

        return array_values($normalized);
    }

    protected function resolveModelOptionsEndpoint(): string
    {
        try {
            if (\Illuminate\Support\Facades\Route::has('svarium.form.select-options.model')) {
                return route('svarium.form.select-options.model');
            }
        } catch (\Throwable) {
            // Ignore and return fallback path.
        }

        return '/svarium/form/options/model';
    }
}
