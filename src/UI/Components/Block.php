<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Appearance;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class Block extends Component
{
    use HasChildren;

    public static function make(Component|array|string|int|float|bool|null $content = null): static
    {
        $instance = parent::make();

        if ($content === null) {
            return $instance;
        }

        if ($content instanceof Component || is_array($content)) {
            $instance->children($content);

            return $instance;
        }

        $instance->child(Text::make((string) $content));

        return $instance;
    }

    public function class(string $class): static
    {
        return $this->appearance([
            'class' => trim($class),
        ]);
    }

    public function flex(string|int|float|bool $flex = true): static
    {
        if (is_bool($flex)) {
            if (! $flex) {
                return $this;
            }

            return $this->appearance('flex');
        }

        $value = is_string($flex) ? trim($flex) : $flex;

        if ($value === '') {
            return $this;
        }

        return $this->appearance(
            Appearance::make()->flex($value)
        );
    }

    public function grid(bool $enabled = true): static
    {
        if (! $enabled) {
            return $this;
        }

        return $this->appearance('grid');
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

    public function header(
        Component|array|string|\Closure|null $content,
        string $position = 'after'
    ): static
    {
        if (strtolower(trim($position)) === 'before') {
            return $this->slot('header_before', $content, 'header', 'before');
        }

        return $this->slot('header', $content);
    }

    public function body(Component|array|string|\Closure|null $content): static
    {
        return $this->slot('body', $content);
    }

    public function footer(
        Component|array|string|\Closure|null $content,
        string $position = 'after'
    ): static
    {
        if (strtolower(trim($position)) === 'before') {
            return $this->slot('footer_before', $content, 'footer', 'before');
        }

        return $this->slot('footer', $content);
    }

    public function top(
        Component|array|string|\Closure|null $content,
        string $position = 'after'
    ): static
    {
        return $this->slot('top', $content, 'header', $position);
    }

    public function bottom(
        Component|array|string|\Closure|null $content,
        string $position = 'before'
    ): static
    {
        return $this->slot('bottom', $content, 'footer', $position);
    }
}
