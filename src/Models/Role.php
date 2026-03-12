<?php

namespace Upsoftware\Svarium\Models;

use Spatie\Permission\Models\Role as BaseRole;
use Spatie\Translatable\HasTranslations;
use Upsoftware\Svarium\Traits\HasSetting;
use Upsoftware\Svarium\Traits\UsesConnection;

class Role extends BaseRole
{
    use UsesConnection, HasTranslations, HasSetting;

    public array $translatable = ['name'];
}
