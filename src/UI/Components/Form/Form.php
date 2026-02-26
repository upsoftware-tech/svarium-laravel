<?php

namespace Upsoftware\Svarium\UI\Components\Form;

use Upsoftware\Svarium\UI\Component;

class Form extends Component
{
    protected array $footer = [];

    public function footer(array $buttons): static
    {
        $this->slots['footer'] = $buttons;
        return $this;
    }

    public function method(string $method): static
    {
        return $this->prop('method', strtoupper($method));
    }

    public function action(string $action): static
    {
        return $this->prop('action', $action);
    }

    public function submitLabel(string $label): static
    {
        return $this->prop('submitLabel', $label);
    }
}
