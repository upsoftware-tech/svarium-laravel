<?php

namespace Upsoftware\Svarium\UI\Components;

use Closure;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class Card extends Component
{
    use HasChildren;

    public static function make(?string $title = null): static
    {
        $instance = parent::make();

        if (is_string($title) && trim($title) !== '') {
            $instance->title($title);
        }

        return $instance;
    }

    public function title(string $title): static
    {
        return $this->prop('title', $title);
    }

    public function description(string $description): static
    {
        return $this->prop('description', $description);
    }

    public function icon(string $icon): static
    {
        return $this->prop('icon', trim($icon));
    }

    public function backUrl(string $url): static
    {
        return $this->prop('backUrl', trim($url));
    }

    public function variant(string $variant): static
    {
        $normalized = strtolower(trim($variant));
        if ($normalized === '') {
            $normalized = 'default';
        }

        return $this->prop('variant', $normalized);
    }

    public function contentPadding(string|int|float $padding): static
    {
        return $this->prop('contentPadding', $padding);
    }

    public function contentWidth(string|int|float $width): static
    {
        return $this->prop('contentWidth', $width);
    }

    public function headerComponents(Component|array|string|Closure|null $content): static
    {
        return $this->slot('headerComponents', $content);
    }

    public function action(Component|array|string|Closure|null $content): static
    {
        return $this->headerComponents($content);
    }
}
