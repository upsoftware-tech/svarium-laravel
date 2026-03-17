<?php

namespace Upsoftware\Svarium\UI\Components;

use Illuminate\Support\Collection;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class Definitions extends Component
{
    use HasChildren;

    public static function make(array|Collection|null $items = null): static
    {
        $instance = parent::make();

        if ($items !== null) {
            $instance->items($items);
        }

        return $instance;
    }

    public function items(array|Collection $items): static
    {
        $normalized = [];
        $source = $items instanceof Collection ? $items->all() : $items;

        foreach ($source as $item) {
            $normalized[] = $this->normalizeItem($item);
        }

        return $this->prop('items', array_values($normalized));
    }

    public function item(mixed $label, mixed $value = null): static
    {
        $items = $this->getProp('items');
        if (! is_array($items)) {
            $items = [];
        }

        if ($value === null && is_array($label)) {
            $items[] = $this->normalizeItem($label);
        } else {
            $items[] = $this->normalizeItem([
                'label' => $label,
                'value' => $value,
            ]);
        }

        return $this->prop('items', array_values($items));
    }

    public function columns(int|string $columns): static
    {
        return $this->prop('columns', $columns);
    }

    public function cols(int|string $columns): static
    {
        return $this->columns($columns);
    }

    public function labelAlign(string $align): static
    {
        return $this->prop('labelAlign', $this->normalizeAlign($align));
    }

    public function valueAlign(string $align): static
    {
        return $this->prop('valueAlign', $this->normalizeAlign($align));
    }

    public function separator(bool $enabled = true): static
    {
        return $this->prop('separator', $enabled);
    }

    public function space(int|string|float $x, int|string|float|null $y = null): static
    {
        $this->spaceX($x);

        return $this->spaceY($y ?? $x);
    }

    public function spaceX(int|string|float $x): static
    {
        return $this->prop('spaceX', $x);
    }

    public function spaceY(int|string|float $y): static
    {
        return $this->prop('spaceY', $y);
    }

    protected function normalizeAlign(string $align): string
    {
        $value = strtolower(trim($align));

        return match ($value) {
            'left', 'start' => 'left',
            'right', 'end' => 'right',
            'center' => 'center',
            default => 'left',
        };
    }

    protected function normalizeItem(mixed $item): array
    {
        $label = null;
        $value = null;

        if ($item instanceof Collection) {
            $item = $item->all();
        }

        if (is_array($item)) {
            if (array_key_exists('label', $item) || array_key_exists('value', $item)) {
                $label = $item['label'] ?? null;
                if (array_key_exists('value', $item)) {
                    $value = $item['value'];
                } else {
                    $extra = $item;
                    unset($extra['label']);
                    $firstExtraKey = array_key_first($extra);
                    $value = $firstExtraKey !== null ? $extra[$firstExtraKey] : null;
                }
            } elseif (array_is_list($item)) {
                $label = $item[0] ?? null;
                $value = $item[1] ?? null;
            } elseif ($item !== []) {
                $firstKey = array_key_first($item);
                if ($firstKey !== null) {
                    $label = $firstKey;
                    $value = $item[$firstKey];
                }
            }
        } else {
            $label = $item;
            $value = null;
        }

        return [
            'label' => $this->normalizeItemValue($label),
            'value' => $this->normalizeItemValue($value),
        ];
    }

    protected function normalizeItemValue(mixed $value): mixed
    {
        if ($value instanceof Component) {
            if (method_exists($value, 'shouldRender') && ! $value->shouldRender()) {
                return null;
            }

            return $value->toArray();
        }

        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (is_array($value)) {
            if (array_key_exists('type', $value)) {
                return $value;
            }

            $normalized = [];
            foreach ($value as $entry) {
                $normalized[] = $this->normalizeItemValue($entry);
            }

            return $normalized;
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        return $value;
    }
}
