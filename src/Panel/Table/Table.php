<?php

namespace Upsoftware\Svarium\Panel\Table;

use Illuminate\Database\Eloquent\Builder;
use Upsoftware\Svarium\Panel\Resource;

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

        $baseQuery = static::resolveCallerResourceQuery();
        $builder = null;

        if ($baseQuery instanceof Builder) {
            try {
                $builder = $tableClass::make($baseQuery);
            } catch (\ArgumentCountError) {
                // Backward compatibility: old make() signatures without query argument.
                $builder = null;
            }
        }

        if (! $builder instanceof TableBuilder) {
            try {
                $builder = $tableClass::make();
            } catch (\ArgumentCountError $e) {
                throw new \InvalidArgumentException(
                    "Table config class [{$tableClass}] must define static make() without required arguments.",
                    previous: $e
                );
            }
        }

        if (! $builder instanceof TableBuilder) {
            throw new \InvalidArgumentException("Table config class [{$tableClass}]::make() must return TableBuilder.");
        }

        return $builder;
    }

    protected static function resolveCallerResourceQuery(): ?Builder
    {
        try {
            $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 32);

            foreach ($trace as $frame) {
                $object = $frame['object'] ?? null;

                if (! $object instanceof Resource) {
                    continue;
                }

                $resourceClass = $object::class;

                if (! method_exists($resourceClass, 'query')) {
                    return null;
                }

                $query = $resourceClass::query();

                return $query instanceof Builder ? $query : null;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
