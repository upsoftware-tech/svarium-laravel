<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class Dropdown extends Component
{
    use HasChildren;

    protected Component|string|null $trigger = null;

    public function trigger(Component|string $component): static
    {
        $this->trigger = $component;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'type' => 'Dropdown',
            'props' => array_merge(
                $this->props,
                [
                    'trigger' => $this->trigger
                        ? [$this->trigger->toArray()]
                        : [],
                ]
            ),
            'children' => array_map(
                fn ($child) => method_exists($child, 'toArray')
                    ? $child->toArray()
                    : $child,
                $this->children ?? []),
        ];
    }
}
