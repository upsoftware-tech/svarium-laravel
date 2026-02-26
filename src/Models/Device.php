<?php

namespace Upsoftware\Svarium\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Upsoftware\Svarium\Traits\UsesConnection;

class Device extends Model
{
    use SoftDeletes, UsesConnection;

    protected static $class;

    protected $hidden = [
        'device_uuid',
        'admin_note',
        'data',
    ];
    protected $guarded = [];
    protected $casts = [
        'data' => 'array',
        'device_hijacked_at' => 'datetime',
    ];


    /**
     * @return string user class fqn
     */
    public static function getUserClass()
    {
        if (isset(static::$class)) {
            return static::$class;
        }

        $u = config('upsoftware.tracking.user_model');

        if (!$u) {
            if (class_exists("App\\Models\\User")) {
                $u = "App\\Models\\User";
            } else if (class_exists("App\\User")) {
                $u = "App\\User";
            }
        }

        if (!class_exists($u)) {
            throw new HttpException(500, "class $u not found");
        }

        if (!is_subclass_of($u, Model::class)) {
            throw new HttpException(500, "class $u is not  model");
        }

        static::$class = $u;

        return $u;
    }

    public function user()
    {
        return $this->belongsToMany(static::getUserClass(), 'device_user')
            ->using(DeviceUser::class)
            ->withPivot([
                'verified_at', 'name', 'reported_as_rogue_at', 'note', 'admin_note'
            ])->withTimestamps();
    }



    public function pivot()
    {
        return $this->hasMany(DeviceUser::class);
    }

    public function currentUserStatus()
    {
        return $this->hasOne(DeviceUser::class)
            ->where('user_id', '=', optional(Auth::user())->id);
    }

    public function isUsedBy($user_id)
    {
        return $this->user()
            ->where('device_user.user_id', $user_id)->exists();
    }

    public function isCurrentUserAttached()
    {
        $attached = !!$this->currentUserStatus;
        if (!$this->currentUserStatus) {
            $this->unsetRelation('currentUserStatus');
        }
        return $attached;
    }
}
