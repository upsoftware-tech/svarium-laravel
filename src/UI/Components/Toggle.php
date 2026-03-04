<?php

namespace Upsoftware\Svarium\UI\Components;

class Toggle extends FieldComponent
{
    public function hint(string $hint): static
    {
        return $this->prop('hint', $hint);
    }

    public function checked(bool $checked = true): static
    {
        return $this->prop('checked', $checked)
            ->value($checked);
    }

    public function toArray(): array
    {
        $array = parent::toArray();
        // Reuse existing Vue component: packages/svarium-npm/src/components/switch/Switch.vue
        $array['type'] = 'Switch';

        return $array;
    }
}

