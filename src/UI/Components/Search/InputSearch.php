<?php

namespace Upsoftware\Svarium\UI\Components\Search;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasPlaceholder;
use Upsoftware\Svarium\UI\Concerns\Props\HasWidth;

class InputSearch extends Component
{
    use HasPlaceholder, HasWidth;

    public function toArray(): array
    {
        $parent = parent::toArray();
        $props = $parent['props'] ?? [];

        return array_merge($parent, ['props' => $props]);
    }
}
