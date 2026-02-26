<?php

namespace Upsoftware\Svarium\Resources\Components;

class UserTile extends Component
{
    protected string $component = 'sidebar-user';

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
        ];
    }
}
