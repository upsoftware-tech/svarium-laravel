<?php

namespace Upsoftware\Svarium\UI\Components;

use Illuminate\Contracts\Support\Arrayable;
use Upsoftware\Svarium\Support\ModelOptionsBuilder;

class Sortable extends FieldComponent
{
    public function autosave(bool $enabled = true, int $delayMs = 350): static
    {
        $this->prop('autosave', $enabled);

        if ($delayMs > 0) {
            $this->prop('autosaveDelay', $delayMs);
        }

        return $this;
    }

    public function items(array|Arrayable $items): static
    {
        if ($items instanceof Arrayable) {
            $items = $items->toArray();
        }

        return $this->prop('items', $items);
    }

    public function values(array|Arrayable $values): static
    {
        return $this->items($values);
    }

    public function options(array|Arrayable|ModelOptionsBuilder $options): static
    {
        if ($options instanceof ModelOptionsBuilder || $options instanceof Arrayable) {
            $options = $options->toArray();
        }

        $this->items($options);

        return parent::options($options);
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
        ]);

        return $this->refreshItemsFromModelOptions();
    }

    public function parentId(int|string|null $parentId, string $column = 'parent_id'): static
    {
        $column = trim($column);
        if ($column === '') {
            $column = 'parent_id';
        }

        $this->prop('modelParentFilter', [
            'column' => $column,
            'value' => $parentId,
        ]);

        return $this->refreshItemsFromModelOptions();
    }

    public function depth(int $from = 0, string $column = 'depth'): static
    {
        $column = trim($column);
        if ($column === '') {
            $column = 'depth';
        }

        $this->prop('modelDepthFilter', [
            'column' => $column,
            'from' => $from,
        ]);

        return $this->refreshItemsFromModelOptions();
    }

    public function level(int $from = 0, string $column = 'level'): static
    {
        return $this->depth($from, $column);
    }

    public function emptyLabel(string $label): static
    {
        return $this->prop('emptyLabel', trim($label));
    }

    public function handleIcon(string $icon): static
    {
        return $this->prop('handleIcon', trim($icon));
    }

    protected function refreshItemsFromModelOptions(): static
    {
        $config = $this->getProp('optionsModel');
        if (! is_array($config)) {
            return $this;
        }

        $modelClass = trim((string) ($config['model'] ?? ''));
        if ($modelClass === '') {
            return $this;
        }

        $value = trim((string) ($config['value'] ?? 'id'));
        $label = trim((string) ($config['label'] ?? 'name'));
        $orders = is_array($config['orders'] ?? null) ? $config['orders'] : [];

        $builder = new ModelOptionsBuilder(
            $modelClass,
            $value !== '' ? $value : 'id',
            $label !== '' ? $label : 'name',
        );

        foreach ($orders as $order) {
            if (! is_array($order)) {
                continue;
            }

            $column = trim((string) ($order['column'] ?? ''));
            if ($column === '') {
                continue;
            }

            $direction = (string) ($order['direction'] ?? 'asc');
            $builder->orderBy($column, $direction);
        }

        $parentFilter = $this->getProp('modelParentFilter');
        if (is_array($parentFilter)) {
            $parentColumn = trim((string) ($parentFilter['column'] ?? 'parent_id'));
            $parentValue = $parentFilter['value'] ?? null;
            if ($parentColumn !== '') {
                $builder->where($parentColumn, $parentValue);
            }
        }

        $depthFilter = $this->getProp('modelDepthFilter');
        if (is_array($depthFilter)) {
            $depthColumn = trim((string) ($depthFilter['column'] ?? 'depth'));
            $depthFrom = (int) ($depthFilter['from'] ?? 0);
            if ($depthColumn !== '') {
                $builder->where($depthColumn, $depthFrom, '>=');
            }
        }

        return $this->options($builder->toArray());
    }
}
