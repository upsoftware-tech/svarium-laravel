<?php

namespace Upsoftware\Svarium\Panel\Form;

use Illuminate\Database\Eloquent\Model;

class Form
{
    public static function make(string $formClass, ?Model $record = null): array
    {
        if (! class_exists($formClass)) {
            throw new \InvalidArgumentException("Form config class [{$formClass}] does not exist.");
        }

        if (! method_exists($formClass, 'make')) {
            throw new \InvalidArgumentException("Form config class [{$formClass}] must define static make().");
        }

        try {
            $schema = $formClass::make($record);
        } catch (\ArgumentCountError) {
            $schema = $formClass::make();
        }

        if (! is_array($schema)) {
            throw new \InvalidArgumentException("Form config class [{$formClass}]::make() must return array.");
        }

        return $schema;
    }
}
