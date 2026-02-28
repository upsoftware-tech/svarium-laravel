<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class Title extends Component
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
        if (function_exists('set_title')) {
            set_title($title);
        }

        return $this;
    }
}
