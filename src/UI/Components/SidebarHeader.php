<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class SidebarHeader extends Component
{
    public static function make(?string $title = null): static
    {
        $instance = parent::make();

        if ($title !== null) {
            $instance->title($title);
        }

        return $instance;
    }

    public function title(string $title): static
    {
        return $this->prop('title', $title);
    }

    public function showBorder(bool $showBorder = true): static
    {
        return $this->prop('showBorder', $showBorder);
    }
}
