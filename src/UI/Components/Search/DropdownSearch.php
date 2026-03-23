<?php

namespace Upsoftware\Svarium\UI\Components\Search;

use Illuminate\Database\Eloquent\Builder;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasBorderStyle;

class DropdownSearch extends Search
{
    use HasBorderStyle;

    protected ?string $name = null;

    protected ?string $column = null;

    protected ?array $items = null;

    protected ?string $source = null;

    protected ?array $staticOptions = null;

    protected bool $showOnlyActive = false;

    protected bool $showOnlyDefined = false;

    protected bool $searchable = false;

    protected string $searchPlaceholder = 'Search...';

    protected int $searchMinItems = 0;
    
    protected int $visibleItems = 2;

    protected string|array|null $color = null;

    protected string|array|null $iconColor = null;

    protected string $iconPosition = 'left';

    protected bool $counter = true;

    protected string $counterPosition = 'right';

    protected ?string $triggerIcon = 'lucide:plus';

    protected bool $showTriggerIcon = true;

    protected $mapCallback = null;

    protected ?string $relationName = null;

    protected ?string $relationLabelColumn = null;

    public static function make(?string $name = ''): static
    {
        $instance = new static;

        if (is_string($name) && trim($name) !== '') {
            $normalizedName = trim($name);
            $instance->name = $normalizedName;
            $instance->prop('name', $normalizedName);
            $instance->label($normalizedName);
        } elseif ($name !== null && method_exists($instance, 'name')) {
            $instance->label(__('View'));
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
        $this->prop('column', $column);

        return $this;
    }

    public function name(string $name): static
    {
        $normalized = trim($name);
        $this->name = $normalized !== '' ? $normalized : null;
        $this->prop('name', $this->name);

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

    public function showOnlyActive(bool $state = true): static
    {
        $this->showOnlyActive = $state;

        return $this;
    }

    public function showOnlyDefined(bool $state = true): static
    {
        $this->showOnlyDefined = $state;

        return $this;
    }

    public function searchable(string|bool|null $placeholder = null, ?int $minItems = null): static
    {
        if (is_bool($placeholder)) {
            $this->searchable = $placeholder;

            if ($minItems !== null) {
                $this->searchMinItems = max(0, $minItems);
            }

            return $this;
        }

        $this->searchable = true;

        if (is_string($placeholder) && trim($placeholder) !== '') {
            $this->searchPlaceholder = trim($placeholder);
        }

        if ($minItems !== null) {
            $this->searchMinItems = max(0, $minItems);
        }

        return $this;
    }

    public function visibleItems(?int $count = 2): static
    {
        $this->visibleItems = max(1, (int) ($count ?? 2));

        return $this;
    }

    public function color(string|array|null $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function iconColor(string|array|null $color): static
    {
        $this->iconColor = $color;

        return $this;
    }

    public function iconPosition(string $position = 'left'): static
    {
        $normalized = strtolower(trim($position));
        if (! in_array($normalized, ['left', 'right', 'end'], true)) {
            $normalized = 'left';
        }

        $this->iconPosition = $normalized;

        return $this;
    }

    public function counter(bool $enabled = true): static
    {
        $this->counter = $enabled;

        return $this;
    }

    public function counterPosition(string $position = 'right'): static
    {
        $normalized = strtolower(trim($position));
        if (! in_array($normalized, ['left', 'right', 'end'], true)) {
            $normalized = 'right';
        }

        $this->counterPosition = $normalized;

        return $this;
    }

    public function counterPosution(string $position = 'right'): static
    {
        return $this->counterPosition($position);
    }

    public function triggerIcon(?string $icon = 'lucide:plus'): static
    {
        $normalized = trim((string) ($icon ?? ''));
        $this->triggerIcon = $normalized !== '' ? $normalized : null;

        return $this;
    }

    public function showTriggerIcon(bool $enabled = true): static
    {
        $this->showTriggerIcon = $enabled;

        return $this;
    }

    public function hideTriggerIcon(bool $hidden = true): static
    {
        return $this->showTriggerIcon(! $hidden);
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
            ->reorder()
            ->selectRaw("{$this->column}, COUNT(*) as aggregate")
            ->groupBy($this->column)
            ->get();

        $activeByKey = [];
        $activeOrder = [];

        foreach ($grouped as $row) {
            $value = $row->{$this->column};

            if ($value === null) {
                continue;
            }

            $key = $this->normalizeItemKey($value);

            $activeByKey[$key] = [
                'value' => $value,
                'count' => (int) $row->aggregate,
            ];

            if (! in_array($key, $activeOrder, true)) {
                $activeOrder[] = $key;
            }
        }

        $relationMap = $this->resolveRelationMap($query);
        $items = [];

        if ($this->staticOptions === null) {
            foreach ($activeOrder as $key) {
                $active = $activeByKey[$key] ?? null;
                if (! is_array($active)) {
                    continue;
                }

                $items[] = $this->buildItem(
                    $active['value'],
                    (int) ($active['count'] ?? 0),
                    null,
                    $relationMap
                );
            }

            $this->items = array_values($items);

            return;
        }

        $definedByKey = [];
        $definedOrder = [];

        foreach ($this->staticOptions as $option) {
            if (! is_array($option) || ! array_key_exists('value', $option)) {
                continue;
            }

            $key = $this->normalizeItemKey($option['value']);
            $definedByKey[$key] = $option;

            if (! in_array($key, $definedOrder, true)) {
                $definedOrder[] = $key;
            }
        }

        if (! $this->showOnlyActive) {
            foreach ($definedOrder as $key) {
                $option = $definedByKey[$key] ?? null;
                if (! is_array($option) || ! array_key_exists('value', $option)) {
                    continue;
                }

                $active = $activeByKey[$key] ?? null;
                $value = is_array($active) ? $active['value'] : $option['value'];
                $count = is_array($active) ? (int) ($active['count'] ?? 0) : 0;

                $items[] = $this->buildItem($value, $count, $option, $relationMap);
            }

            if (! $this->showOnlyDefined) {
                foreach ($activeOrder as $key) {
                    if (array_key_exists($key, $definedByKey)) {
                        continue;
                    }

                    $active = $activeByKey[$key] ?? null;
                    if (! is_array($active)) {
                        continue;
                    }

                    $items[] = $this->buildItem(
                        $active['value'],
                        (int) ($active['count'] ?? 0),
                        null,
                        $relationMap
                    );
                }
            }
        } else {
            foreach ($activeOrder as $key) {
                $active = $activeByKey[$key] ?? null;
                if (! is_array($active)) {
                    continue;
                }

                $option = $definedByKey[$key] ?? null;

                if ($this->showOnlyDefined && ! is_array($option)) {
                    continue;
                }

                $items[] = $this->buildItem(
                    $active['value'],
                    (int) ($active['count'] ?? 0),
                    is_array($option) ? $option : null,
                    $relationMap
                );
            }
        }

        $this->items = array_values($items);
    }

    protected function normalizeItemKey(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return 'null';
        }

        return (string) $value;
    }

    protected function resolveRelationMap(Builder $query): array
    {
        if (! $this->relationName || ! $this->relationLabelColumn) {
            return [];
        }

        $modelClass = $query->getModel()::class;
        $relation = $this->relationName;
        $labelColumn = $this->relationLabelColumn;

        $relatedModel = (new $modelClass)->$relation()->getRelated();

        return $relatedModel
            ->newQuery()
            ->pluck($labelColumn, $relatedModel->getKeyName())
            ->toArray();
    }

    protected function buildItem(
        mixed $value,
        int $count,
        ?array $definedOption,
        array $relationMap
    ): array {
        $item = [
            'value' => $value,
            'count' => $count,
        ];

        if (is_array($definedOption)) {
            $item = array_merge($item, $definedOption);
        }

        if (! array_key_exists('label', $item) || trim((string) $item['label']) === '') {
            if ($relationMap !== []) {
                $item['label'] = $relationMap[$value] ?? $value;
            } elseif ($this->mapCallback) {
                $item['label'] = call_user_func($this->mapCallback, $value);
            } else {
                $item['label'] = $value;
            }
        }

        return $item;
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

        if (! array_key_exists('name', $props) || trim((string) ($props['name'] ?? '')) === '') {
            $props['name'] = $this->name ?? $this->column;
        }

        if (! array_key_exists('column', $props) || trim((string) ($props['column'] ?? '')) === '') {
            $props['column'] = $this->column;
        }

        $props['items'] = $this->items ?? [];
        $props['searchable'] = [
            'enabled' => $this->searchable,
            'placeholder' => $this->searchPlaceholder,
            'minItems' => $this->searchMinItems,
        ];
        $props['visibleItems'] = $this->visibleItems;
        $props['color'] = $this->color;
        $props['iconColor'] = $this->iconColor;
        $props['iconPosition'] = $this->iconPosition;
        $props['counter'] = $this->counter;
        $props['counterPosition'] = $this->counterPosition;
        $props['triggerIcon'] = $this->triggerIcon;
        $props['showTriggerIcon'] = $this->showTriggerIcon;

        return array_merge($parent, ['props' => $props]);
    }
}
