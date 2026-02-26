<?php

namespace Upsoftware\Svarium\Resources\Components;

class Button extends Component
{
    protected string $component = 'button';
    protected string $label;

    public function __construct(string $label)
    {
        parent::__construct();
        $this->label = $label;
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'props' => $this->mergeOptions([
                'label' => $this->label,
            ]),
        ];
    }
}
