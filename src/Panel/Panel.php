<?php

namespace Upsoftware\Svarium\Panel;

use Upsoftware\Svarium\Enums\TableActionDisplay;

class Panel
{
    public function __construct(
        public string $name,
        public ?string $prefix = null,
    ) {}

    public ?string $layout = null;
    public ?\Closure $layoutBuilder = null;
    protected array $layoutSlots = [];
    protected array $middleware = [];
    protected TableActionDisplay|string|null $tableActionDisplay = null;

    public function layout(string $class): static
    {
        $this->layout = $class;
        return $this;
    }

    public function middleware(array $middleware): static
    {
        $this->middleware = $middleware;
        return $this;
    }

    public function layoutUsing(\Closure $builder): static
    {
        $this->layoutBuilder = $builder;
        return $this;
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function prefix(string $prefix): static
    {
        $this->prefix = trim($prefix, '/');
        return $this;
    }

    public function prefixName(): string
    {
        return trim($this->name, '/');
    }

    public function noPrefix(): static
    {
        $this->prefix = null;
        return $this;
    }

    public function header($content): static
    {
        $this->layoutSlots['header'] = $content;
        return $this;
    }

    public function sidebar($content): static
    {
        $this->layoutSlots['sidebar'] = $content;
        return $this;
    }

    public function content($content): static
    {
        $this->layoutSlots['body'] = $content;
        return $this;
    }

    public function aside($content): static
    {
        $this->layoutSlots['aside'] = $content;
        return $this;
    }

    public function footer($content): static
    {
        $this->layoutSlots['footer'] = $content;
        return $this;
    }

    public function tableActionDisplay(): TableActionDisplay|string|null
    {
        return $this->tableActionDisplay;
    }

    public function getLayoutSlots(): array
    {
        return $this->layoutSlots;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}
