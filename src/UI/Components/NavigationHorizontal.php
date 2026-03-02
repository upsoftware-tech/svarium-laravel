<?php

namespace Upsoftware\Svarium\UI\Components;

class NavigationHorizontal extends NavigationVertical
{
    public function toArray(): array
    {
        $data = parent::toArray();
        $data['type'] = 'NavigationHorizontal';

        return $data;
    }
}
