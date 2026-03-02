<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class Condition extends Component
{
    public static function make(?string $name = null): static
    {
        return parent::make($name);
    }

    public function if(mixed $condition, Component|array|string|\Closure|null $content = null): static
    {
        $this->prop('if', $condition);

        if (func_num_args() > 1) {
            $this->slot('if', $content);
        }

        return $this;
    }

    public function then(Component|array|string|\Closure|null $content): static
    {
        return $this->slot('if', $content);
    }

    public function elseIf(mixed $condition, Component|array|string|\Closure|null $content = null): static
    {
        $conditions = $this->getProp('elseIf', []);

        if (! is_array($conditions)) {
            $conditions = [];
        }

        $index = count($conditions);
        $conditions[] = $condition;

        $this->prop('elseIf', $conditions);

        if (func_num_args() > 1) {
            $this->slot("elseIf_{$index}", $content);
        }

        return $this;
    }

    public function else(Component|array|string|\Closure|null $content): static
    {
        return $this->slot('else', $content);
    }

    public function otherwise(Component|array|string|\Closure|null $content): static
    {
        return $this->else($content);
    }

    public function content(array|Component $children): static
    {
        return $this->slot('if', $children);
    }
}
