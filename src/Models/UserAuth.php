<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Traits\HasHash;
use App\Models\User;
use Upsoftware\Svarium\Traits\UsesConnection;

class UserAuth extends Model
{
    use HasHash, UsesConnection;

    public $guarded = [];

    public function code()
    {
        return $this->hasMany(UserAuthCode::class);
    }

    public static function setToken(User $user, $type)
    {
        return self::create([
            'type' => $type,
            'user_id' => $user->id,
        ]);
    }

    public function generateCode($method)
    {
        $code = rand(100000, 999999);

        return $this->code()->create([
            'code' => $code,
            'method' => $method,
            'expired_at' => now()->addMinute(30),
        ]);
    }

    public function verifyCode($code)
    {
        return $this->code()->where('code', $code)->first() ? true : false;
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function sendSms()
    {
        $code = $this->generateCode('sms');
        $this->user->notify(new SendCodeNotification($code->code, $code->expired_at));
        $this->save();
    }

    public function sendEmail($type)
    {
        $code = $this->generateCode('email');
        $class = 'Upsoftware\\Svarium\\Notifications\\SendCodeNotificationEmail'.ucfirst($type);

        if (! class_exists($class)) {
            throw new \Exception("Notification class {$class} does not exist.");
        }

        $this->user->notify(
            new $class($code->code, $code->expired_at)
        );
    }
}
