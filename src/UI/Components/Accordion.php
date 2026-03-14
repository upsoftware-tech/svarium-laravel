<?php

namespace Upsoftware\Svarium\UI\Components;

use Closure;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class Accordion extends Component
{
    use HasChildren;

    public static function make(array|string|null $name = null): static
    {
        return new static($name);
    }

    public function items(array $items): static
    {
        foreach ($items as $item) {
            if ($item instanceof AccordionItem) {
                $this->child($item);
            }
        }

        return $this;
    }

    public function item(
        string $title,
        Component|array|string|Closure|null $content = null,
        string|int|null $value = null
    ): static {
        $item = AccordionItem::make($title);

        if ($value !== null) {
            $item->value($value);
        }

        if ($content instanceof Closure) {
            $content = $content();
        }

        if ($content instanceof Component || is_array($content)) {
            $item->children($content);
        } elseif ($content !== null) {
            $item->child(Text::make((string) $content));
        }

        return $this->child($item);
    }

    public function type(string $type): static
    {
        $normalized = strtolower(trim($type));
        if (! in_array($normalized, ['single', 'multiple'], true)) {
            $normalized = 'single';
        }

        return $this->prop('type', $normalized);
    }

    public function single(): static
    {
        return $this->type('single');
    }

    public function multiple(): static
    {
        return $this->type('multiple');
    }

    public function collapsible(bool $collapsible = true): static
    {
        return $this->prop('collapsible', $collapsible);
    }

    public function defaultOpen(string|int|array|null $value): static
    {
        return $this->prop('defaultOpen', $value);
    }

    public function defaultValue(string|int|array|null $value): static
    {
        return $this->prop('defaultValue', $value);
    }
}
