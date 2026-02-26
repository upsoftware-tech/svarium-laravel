<?php

namespace Upsoftware\Svarium\Models;

use Spatie\Permission\Models\Role as BaseRole;
use Spatie\Translatable\HasTranslations;
use Upsoftware\Svarium\Traits\UsesConnection;

class Role extends BaseRole
{
    use UsesConnection, HasTranslations;

    public array $translatable = ['name'];
}
