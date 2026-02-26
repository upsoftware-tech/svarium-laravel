<?php

namespace Upsoftware\Svarium\Resources\Components;

class Grid extends Block
{
    public string $component = 'grid';
    public array $cols = [];

    public function cols(int|string|array $value, ?int $amount = null): static
    {
        return $this->handleBreakpointProp('cols', $value, $amount);
    }
    public function props(): array
    {
        $props = parent::props();
        if (!empty($this->cols)) {
            $props['cols'] = $this->cols;
        }
        return $props;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), ['props' => $this->props()]);
    }
}
