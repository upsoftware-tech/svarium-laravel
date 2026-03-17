<?php

namespace Upsoftware\Svarium\UI\Concerns\Props;

use Upsoftware\Svarium\UI\Component;

trait HasChildren
{
    protected array $children = [];

    public function child(Component|array $component): static
    {
        if ($component instanceof Component) {
            $this->children[] = $component;
            return $this;
        }

        if (isset($component['type']) && is_string($component['type'])) {
            $this->children[] = $component;
            return $this;
        }

        if (array_is_list($component)) {
            foreach ($component as $child) {
                if ($child instanceof Component || is_array($child)) {
                    $this->child($child);
                }
            }
        }

        return $this;
    }

    public function children(array|Component $components): static
    {
        if ($components instanceof Component) {
            $this->child($components);
            return $this;
        }

        foreach ($components as $component) {
            if ($component instanceof Component || is_array($component)) {
                $this->child($component);
            }
        }

        return $this;
    }

    protected function serializeChildren(): array
    {
        $serialized = [];

        foreach ($this->children as $child) {
            if ($child instanceof Component) {
                $serialized[] = $child->toArray();
                continue;
            }

            if (is_array($child) && isset($child['type']) && is_string($child['type'])) {
                $serialized[] = $child;
            }
        }

        return $serialized;
    }
}
