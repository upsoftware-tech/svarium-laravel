<?php

namespace Upsoftware\Svarium\UI\Components\Form;

use Upsoftware\Svarium\UI\Concerns\Props\HasVariant;
use Upsoftware\Svarium\UI\Components\FieldComponent;

class Input extends FieldComponent
{
    use HasVariant;

    public function textAlign(string $alignment): static
    {
        return $this->prop('textAlign', $alignment);
    }

    public function prepend(mixed $value): static
    {
        return $this->prop('prepend', $value);
    }

    public function append(mixed $value): static
    {
        return $this->prop('append', $value);
    }

    public function format(string $format): static
    {
        return $this->prop('format', $format);
    }

    public function calendarPosition(string $position): static
    {
        return $this->prop('calendarPosition', $position);
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        if ((bool) ($array['props']['language'] ?? false)) {
            $array['type'] = 'InputLanguage';
        }

        return $array;
    }
}
