<?php

namespace Upsoftware\Svarium\UI\Components\Form;

use Upsoftware\Svarium\UI\Concerns\Props\HasVariant;
use Upsoftware\Svarium\UI\Components\FieldComponent;

class Input extends FieldComponent
{
    use HasVariant;

    public function toArray(): array
    {
        $array = parent::toArray();

        if ((bool) ($array['props']['language'] ?? false)) {
            $array['type'] = 'InputLanguage';
        }

        return $array;
    }
}
