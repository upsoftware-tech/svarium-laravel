<?php

namespace Upsoftware\Svarium\Resources;

use \Upsoftware\Svarium\Contracts\Layout as LayoutContract;
use Upsoftware\Svarium\Support\ComponentRenderer;

abstract class Layout implements LayoutContract
{
    public bool $enabled = false;

    public function renderComponent($children) {
        return ComponentRenderer::render($children);
    }
}
