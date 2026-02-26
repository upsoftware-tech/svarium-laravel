<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasHref;
use Upsoftware\Svarium\UI\Concerns\Props\HasIcon;

class Button extends Component
{
    use HasHref, HasIcon;

    protected ?string $type = 'button';

    protected ?string $name = null;

    protected ?string $value = 'save_and_back';
    protected ?string $size = null;

    protected string $componentButtonType = 'Button';

    public static function make(...$args): static
    {
        $instance = new static;

        if (isset($args[0])) {
            $instance->label($args[0]);
        }

        return $instance;
    }

    public function variant(string $variant): static
    {
        return $this->prop('variant', $variant);
    }

    public function size(string $size): static
    {
        $this->size = $size;
        $this->prop('size', $size);
        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;
        $this->prop('label', $label);
        return $this;
    }

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function value(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function submit(): static
    {
        $this->type('submit');

        return $this;
    }

    public function type(string $type): static
    {
        $this->componentButtonType = $type === 'submit' ? 'ButtonSubmit' : 'Button';
        $this->type = $type;

        return $this;
    }

    public function toArray(): array
    {
        $parent = parent::toArray();
        $props = $parent['props'] ?? [];

        if ($this->name) {
            $props['name'] = $this->name;
        }

        if ($this->value) {
            $props['value'] = $this->value;
        }

        return [
            'type' => $this->componentButtonType,
            'props' => $props,
            'children' => [],
            'slots' => [],
        ];
    }
}
