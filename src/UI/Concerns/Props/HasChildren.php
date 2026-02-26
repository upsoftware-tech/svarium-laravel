<?php

namespace Upsoftware\Svarium\UI\Concerns\Props;

use Upsoftware\Svarium\UI\Component;

trait HasChildren
{
    protected array $children = [];

    public function child(Component $component): static
    {
        $this->children[] = $component;
        return $this;
    }

    public function children(array $components): static
    {
        foreach ($components as $component) {
            $this->child($component);
        }

        return $this;
    }

    protected function serializeChildren(): array
    {
        return array_map(
            fn ($child) => $child->toArray(),
            $this->children
        );
    }
}
