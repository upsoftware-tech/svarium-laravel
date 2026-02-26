<?php

namespace Upsoftware\Svarium\Support;

use Upsoftware\Svarium\Resources\Components\Component;

class ComponentRenderer
{
    public static function render(mixed $content): mixed
    {
        if ($content instanceof Component) {
            return $content->toArray();
        }

        if (is_array($content)) {
            return array_map([self::class, 'render'], $content);
        }

        return $content;
    }
}
