<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasHref;
use Upsoftware\Svarium\UI\Concerns\Props\HasIcon;

class DropdownItem extends Component
{
    use HasHref, HasIcon;

    public function toArray(): array
    {
        return array_merge(
            parent::toArray(), ['props' => $this->props]
        );
    }
}
