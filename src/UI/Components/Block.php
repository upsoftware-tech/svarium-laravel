<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class Block extends Component
{
    use HasChildren;

    public static function make(?string $name = null): static
    {
        return parent::make($name);
    }

    public function class(string $class): static
    {
        return $this->appearance([
            'class' => trim($class),
        ]);
    }

    public function style(array $style): static
    {
        $currentAppearance = $this->getProp('appearance', []);
        $currentStyle = is_array($currentAppearance)
            ? ($currentAppearance['style'] ?? [])
            : [];

        return $this->appearance([
            'style' => [
                ...$currentStyle,
                ...$style,
            ],
        ]);
    }
}
