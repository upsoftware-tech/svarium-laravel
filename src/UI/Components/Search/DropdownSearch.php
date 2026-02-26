<?php

namespace Upsoftware\Svarium\UI\Components\Search;

use Illuminate\Database\Eloquent\Builder;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasBorderStyle;

class DropdownSearch extends Search
{
    use HasBorderStyle;

    protected ?string $column = null;

    protected ?array $items = null;

    protected ?string $source = null;

    protected ?array $staticOptions = null;

    protected $mapCallback = null;

    protected ?string $relationName = null;

    protected ?string $relationLabelColumn = null;

    public static function make(?string $name = ''): static
    {
        $instance = new static;

        if ($name !== null && method_exists($instance, 'name')) {
            $instance->label(empty($name) ? __('View') : $name);
        }

        return $instance;
    }

    /*
    |--------------------------------------------------------------------------
    | Source strategies
    |--------------------------------------------------------------------------
    */

    public function column(string $column): static
    {
        $this->column = $column;

        return $this;
    }

    public function items(array $items): static
    {
        $this->items = $items;

        return $this;
    }

    public function source(string $url): static
    {
        $this->source = $url;

        return $this;
    }

    public function mapUsing(callable $callback): static
    {
        $this->mapCallback = $callback;

        return $this;
    }

    public function relation(string $relation, string $labelColumn): static
    {
        $this->relationName = $relation;
        $this->relationLabelColumn = $labelColumn;

        return $this;
    }

    public function options(array $options): static
    {
        $normalized = [];

        foreach ($options as $value => $option) {

            $item = [
                'value' => $value,
            ];

            if (is_string($option)) {
                $item['label'] = $option;
            } elseif (is_array($option)) {

                $item = array_merge($item, $option);
                if (isset($item['icon'])) {

                    if ($item['icon'] instanceof Component) {
                        $item['icon'] = $item['icon']->toArray();
                    }
                }
            }

            $normalized[$value] = $item;
        }

        $this->staticOptions = $normalized;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve items for column strategy
    |--------------------------------------------------------------------------
    */

    public function resolveFromQuery(Builder $query): void
    {
        if (! $this->column) {
            return;
        }

        $grouped = (clone $query)
            ->selectRaw("{$this->column}, COUNT(*) as aggregate")
            ->groupBy($this->column)
            ->get();

        $this->items = $grouped
            ->filter(fn ($row) => $row->{$this->column} !== null)
            ->map(function ($row) use ($query) {

                $value = $row->{$this->column};
                $count = (int) $row->aggregate;

                $item = [
                    'value' => $value,
                    'count' => $count,
                ];

                if ($this->staticOptions !== null) {
                    if (isset($this->staticOptions[$value])) {
                        $item = array_merge($item, $this->staticOptions[$value]);
                    } else {
                        $item['label'] = $value;
                    }
                } elseif ($this->relationName) {

                    $modelClass = $query->getModel()::class;
                    $relation = $this->relationName;
                    $labelColumn = $this->relationLabelColumn;

                    $relatedModel = (new $modelClass)->$relation()->getRelated();

                    $map = $relatedModel
                        ->newQuery()
                        ->pluck($labelColumn, $relatedModel->getKeyName())
                        ->toArray();

                    $item['label'] = $map[$value] ?? $value;
                } elseif ($this->mapCallback) {
                    $item['label'] = call_user_func($this->mapCallback, $value);
                } else {
                    $item['label'] = $value;
                }

                return $item;

            })
            ->values()
            ->toArray();
    }

    public function toArray(): array
    {
        $parent = parent::toArray();
        $props = $parent['props'] ?? [];
        if ($this->borderStyle) {
            $props[] = $this->borderStyle;
        }
        if (! $this->label) {
            $props['label'] = $this->column;
        }
        $props['items'] = $this->items ?? [];

        return array_merge($parent, ['props' => $props]);
    }
}
