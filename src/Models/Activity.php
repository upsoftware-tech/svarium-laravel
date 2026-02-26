<?php

namespace Upsoftware\Svarium\Models;

use \Spatie\Activitylog\Models\Activity as ActivityBase;
use Upsoftware\Svarium\Traits\UsesConnection;

class Activity extends ActivityBase
{
    use UsesConnection;
}
