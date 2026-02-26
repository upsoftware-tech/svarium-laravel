<?php

namespace Upsoftware\Svarium\Panel\Table;

class Table
{
    public static function make(string $tableClass): TableBuilder
    {
        if (! class_exists($tableClass)) {
            throw new \InvalidArgumentException("Table config class [{$tableClass}] does not exist.");
        }

        if (! method_exists($tableClass, 'make')) {
            throw new \InvalidArgumentException("Table config class [{$tableClass}] must define static make().");
        }

        try {
            $builder = $tableClass::make();
        } catch (\ArgumentCountError $e) {
            throw new \InvalidArgumentException(
                "Table config class [{$tableClass}] must define static make() without required arguments.",
                previous: $e
            );
        }

        if (! $builder instanceof TableBuilder) {
            throw new \InvalidArgumentException("Table config class [{$tableClass}]::make() must return TableBuilder.");
        }

        return $builder;
    }
}
