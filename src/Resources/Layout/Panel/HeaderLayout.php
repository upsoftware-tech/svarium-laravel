<?php

namespace Upsoftware\Svarium\Resources\Layout\Panel;

use Upsoftware\Svarium\Resources\Components\Component;
use Upsoftware\Svarium\Resources\Layout;

abstract class HeaderLayout extends Layout
{
    public function content(): array
    {
        return [
            'left'    => $this->left(),
            'default' => $this->default(),
            'right'   => $this->right(),
        ];
    }
    public function getContent(): array|Component|null
    {
        return $this->enabled ? array_map([$this, 'renderComponent'], $this->content()) : null;
    }
}
