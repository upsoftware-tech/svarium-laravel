<?php

namespace Upsoftware\Svarium\UI\Layouts;

use Upsoftware\Svarium\UI\Component;

class PanelLayout extends Component
{
    public function __construct()
    {
        $this->prop('layout', 'panel');
    }

    public function body(Component|array|string|\Closure|null $c): static
    {
        return $this->slot('body', $c);
    }

    public function content(Component|array|string|\Closure|null $c): static
    {
        return $this->slot('content', $c);
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
}
