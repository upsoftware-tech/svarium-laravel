<?php

namespace Upsoftware\Svarium\UI\Components\Table;

use Upsoftware\Svarium\UI\Appearance;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class TableHeader extends Component
{
    use HasChildren;

    public static function make(?string $name = null): static
    {
        return new static;
    }

    public function class(string $class): static
    {
        return $this->appearance([
            'class' => $class,
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

    public function toArray(): array
    {
        return [
            'type' => 'TableHeader',
            'props' => $this->props,
            'children' => $this->serializeChildren(),
            'slots' => [],
        ];
    }
}
