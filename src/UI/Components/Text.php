<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class Text extends Component
{
    public static function make(?string $text = null): static
    {
        $instance = parent::make();

        if ($text !== null) {
            $instance->text($text);
        }

        return $instance;
    }

    public function text(string $text): static
    {
        return $this->prop('text', $text);
    }

    public function tag(string $tag): static
    {
        return $this->prop('tag', $tag);
    }

    public function class(string $class): static
    {
        return $this->prop('class', $class);
    }

    public function html(bool $enabled = true): static
    {
        return $this->prop('html', $enabled);
    }

    public function variant(string $variant): static
    {
        return $this->prop('variant', $variant);
    }
}
