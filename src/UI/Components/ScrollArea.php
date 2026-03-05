<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class ScrollArea extends Component
{
    use HasChildren;

    public static function make(?string $name = null): static
    {
        return parent::make($name);
    }

    public function direction(string $direction): static
    {
        $value = strtolower(trim($direction));

        if (! in_array($value, ['ltr', 'rtl'], true)) {
            return $this;
        }

        return $this->prop('dir', $value);
    }

    public function scrollHideDelay(int $milliseconds): static
    {
        return $this->prop('scrollHideDelay', max(0, $milliseconds));
    }
}

