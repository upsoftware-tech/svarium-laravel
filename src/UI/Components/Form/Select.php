<?php

namespace Upsoftware\Svarium\UI\Components\Form;

use Illuminate\Contracts\Support\Arrayable;
use Upsoftware\Svarium\Support\ModelOptionsBuilder;
use Upsoftware\Svarium\UI\Concerns\Props\HasVariant;
use Upsoftware\Svarium\UI\Components\FieldComponent;

class Select extends FieldComponent
{
    use HasVariant;

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

        if (is_string($orderBy) && trim($orderBy) !== '') {
            $builder->orderBy(trim($orderBy));
        }

        if (is_array($orderBy)) {
            if (array_is_list($orderBy) && isset($orderBy[0]) && is_string($orderBy[0])) {
                $column = trim((string) $orderBy[0]);
                $direction = isset($orderBy[1]) ? (string) $orderBy[1] : 'asc';

                if ($column !== '') {
                    $builder->orderBy($column, $direction);
                }
            } else {
                foreach ($orderBy as $column => $direction) {
                    if (is_int($column)) {
                        if (is_string($direction) && trim($direction) !== '') {
                            $builder->orderBy(trim($direction));
                        }
                        continue;
                    }

                    $normalizedColumn = trim((string) $column);
                    if ($normalizedColumn === '') {
                        continue;
                    }

                    $builder->orderBy($normalizedColumn, (string) $direction);
                }
            }
        }

        return $this->options($builder);
    }

    public function placeholder(string $placeholder): static
    {
        return $this->prop('placeholder', $placeholder);
    }

    public function clear(bool $enabled = true): static
    {
        return $this->prop('clear', $enabled);
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
}
