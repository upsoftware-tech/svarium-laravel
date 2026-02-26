<?php

namespace Upsoftware\Svarium\Traits;

use Illuminate\Database\Eloquent\Builder;
use Upsoftware\Svarium\Services\ApiResponseBuilder;

trait HasApiBuilder
{
    public static function fields(array $fields): ApiResponseBuilder
    {
        return new ApiResponseBuilder(self::query(), $fields);
    }

    public function scopeFields(Builder $query, array $fields): ApiResponseBuilder
    {
        return new ApiResponseBuilder($query, $fields);
    }
}
