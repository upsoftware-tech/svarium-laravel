<?php

namespace Upsoftware\Svarium\UI\Components;

class Checklist extends FieldComponent
{
    public function options(array $options): static
    {
        return $this->prop('options', $options);
    }

    public function multiple(bool $multiple = true): static
    {
        return $this->prop('multiple', $multiple);
    }

    public function emptyLabel(string $label): static
    {
        return $this->prop('emptyLabel', $label);
    }
}
