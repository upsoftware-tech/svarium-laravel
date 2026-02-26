<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Traits\UsesConnection;

class UserAuthCode extends Model
{
    use UsesConnection;
    public $guarded = [];
}
