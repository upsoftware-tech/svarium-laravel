<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class AccordionItem extends Component
{
    use HasChildren;

    public static function make(array|string|null $name = null): static
    {
        $instance = new static($name);

        if (is_string($name)) {
            $instance->title($name);
        }

        return $instance;
    }

    public function title(string $title): static
    {
        return $this->prop('name', $title);
    }

    public function value(string|int $value): static
    {
        return $this->prop('value', $value);
    }

    public function disabled(bool $state = true): static
    {
        return $this->prop('disabled', $state);
    }

    public function icon(string $icon): static
    {
        return $this->prop('icon', $icon);
    }

    public function badge(string|int $value): static
    {
        return $this->prop('badge', $value);
    }

    public function trigger(Component|array|string|\Closure|null $content): static
    {
        return $this->slot('trigger', $content);
    }

    public function body(Component|array|string|\Closure|null $content): static
    {
        return $this->slot('content', $content);
    }
}
