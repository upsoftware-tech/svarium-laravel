<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class Container extends Block
{
    public static function make(Component|array|string|int|float|bool|null $content = null): static
    {
        /** @var static $instance */
        $instance = parent::make($content);

        return $instance
            ->appearance('container')
            ->position('center')
            ->fluid(false);
    }

    public function position(string $position = 'center'): static
    {
        $position = strtolower(trim($position));

        if (! in_array($position, ['left', 'right', 'center'], true)) {
            $position = 'center';
        }

        return $this->prop('position', $position);
    }

    public function left(): static
    {
        return $this->position('left');
    }

    public function right(): static
    {
        return $this->position('right');
    }

    public function center(): static
    {
        return $this->position('center');
    }

    public function fluid(bool $enabled = true): static
    {
        return $this->prop('fluid', $enabled);
    }
}
