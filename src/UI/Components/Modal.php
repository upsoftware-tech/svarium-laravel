<?php

namespace Upsoftware\Svarium\UI\Components;

use Closure;
use Upsoftware\Svarium\UI\Component;

class Modal extends Component
{
    public static function make(?string $name = null): static
    {
        return (new static)
            ->mode('form')
            ->maxWidth(640)
            ->modal(true)
            ->defaultOpen(false);
    }

    public static function form(?string $name = null): static
    {
        return static::make($name)
            ->mode('form')
            ->maxWidth(720);
    }

    public static function preview(?string $name = null): static
    {
        return static::make($name)
            ->mode('preview')
            ->maxWidth(960);
    }

    public static function confirm(?string $name = null): static
    {
        return static::make($name)
            ->mode('confirm')
            ->maxWidth(480);
    }

    public function mode(string $mode): static
    {
        return $this->prop('mode', $mode);
    }

    public function title(?string $title): static
    {
        return $this->prop('title', $title);
    }

    public function description(?string $description): static
    {
        return $this->prop('description', $description);
    }

    public function maxWidth(int|string $width): static
    {
        return $this->prop('maxWidth', $width);
    }

    public function modal(bool $enabled = true): static
    {
        return $this->prop('modal', $enabled);
    }

    public function defaultOpen(bool $enabled = true): static
    {
        return $this->prop('defaultOpen', $enabled);
    }

    public function trigger(Component|array|string|Closure|null $content): static
    {
        return $this->slot('trigger', $content);
    }

    public function content(Component|array|string|Closure|null $content): static
    {
        return $this->slot('content', $content);
    }

    public function body(Component|array|string|Closure|null $content): static
    {
        return $this->content($content);
    }

    public function footer(Component|array|string|Closure|null $content): static
    {
        return $this->slot('footer', $content);
    }
}
