<?php

namespace Upsoftware\Svarium\Resources\Components;

class Flex extends Block
{
    public string $component = 'flex';

    public function toArray(): array
    {
        return array_merge(parent::toArray(), ['props' => $this->props()]);
    }
}
