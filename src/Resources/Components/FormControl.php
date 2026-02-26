<?php

namespace Upsoftware\Svarium\Resources\Components;

abstract class FormControl extends Component
{
    protected ?string $name = null;
    protected ?string $label = null;

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->label = $name;
    }

    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'label'     => $this->label,
            'required'  => false,
        ];
    }
}
