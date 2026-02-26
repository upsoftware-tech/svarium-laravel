<?php

namespace Upsoftware\Svarium\Resources\Schemas;

abstract class Form extends Schema
{
    public function getValidationRules(): array
    {
        $rules = [];
        foreach ($this->schema() as $component) {
            if ($component->getRules()) {
                $rules[$component->getName()] = $component->getRules();
            }
        }
        return $rules;
    }
}
