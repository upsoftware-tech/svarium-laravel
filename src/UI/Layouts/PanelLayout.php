<?php

namespace Upsoftware\Svarium\UI\Layouts;

use Upsoftware\Svarium\UI\Component;

class PanelLayout extends Component
{
    public function __construct()
    {
        $this->prop('layout', 'panel');
        $this->prop('containerEnabled', (bool) config('upsoftware.panel.container.enabled', true));
        $this->prop('containerFluid', (bool) config('upsoftware.panel.container.fluid', false));
        $this->prop('containerPosition', (string) config('upsoftware.panel.container.position', 'center'));
        $this->define();
    }

    /**
     * Hook for declarative layout definitions without overriding constructor.
     */
    protected function define(): void {}

    public function body(Component|array|string|\Closure|null $c): static
    {
        return $this->slot('body', $c);
    }

    public function content(Component|array|string|\Closure|null $c): static
    {
        return $this->slot('content', $c);
    }

    public function contentHeader(Component|array|string|\Closure|null $c): static
    {
        return $this->slot('contentHeader', $c);
    }

    public function contentFooter(Component|array|string|\Closure|null $c): static
    {
        return $this->slot('contentFooter', $c);
    }

    public function header(Component|array|string|\Closure|null $c): static
    {
        return $this->slot('header', $c);
    }

    public function sidebar(Component|array|string|\Closure|null $c): static
    {
        return $this->slot('sidebar', $c);
    }

    public function footer(Component|array|string|\Closure|null $c): static
    {
        return $this->slot('footer', $c);
    }

    public function aside(Component|array|string|\Closure|null $c): static
    {
        return $this->slot('aside', $c);
    }

    public function container(bool $enabled = true): static
    {
        return $this->prop('containerEnabled', $enabled);
    }

    public function containerFluid(bool $enabled = true): static
    {
        return $this->prop('containerFluid', $enabled);
    }

    public function containerPosition(string $position = 'center'): static
    {
        $position = strtolower(trim($position));

        if (! in_array($position, ['left', 'center', 'right'], true)) {
            $position = 'center';
        }

        return $this->prop('containerPosition', $position);
    }
}
