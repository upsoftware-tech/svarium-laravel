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
        $ttlMinutes = max(1, (int) config('upsoftware.auth.otp.code_ttl_minutes', 10));
        $code = $this->generateOtpCode();
        $invalidatePrevious = (bool) config('upsoftware.auth.otp.invalidate_previous_codes', true);

        if ($invalidatePrevious) {
            // Only newest generated code should be active.
            $this->code()
                ->where(function ($query) {
                    $query->whereNull('is_used')
                        ->orWhere('is_used', false);
                })
                ->update([
                    'is_used' => true,
                ]);
        }

        return $this->code()->create([
            'code' => $code,
            'method' => $method,
            'expired_at' => now()->addMinutes($ttlMinutes),
        ]);
    }

    public function verifyCode($code)
    {
        $normalizedCode = $this->normalizeOtpCode($code);

        if ($normalizedCode === '') {
            return false;
        }

        $authCode = $this->code()
            ->where('code', $normalizedCode)
            ->where(function ($query) {
                $query->whereNull('is_used')
                    ->orWhere('is_used', false);
            })
            ->where('expired_at', '>=', now())
            ->latest('id')
            ->first();

        if (! $authCode) {
            return false;
        }

        $authCode->is_used = true;
        $authCode->save();

        return true;
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

    protected function otpCodeLength(): int
    {
        return max(1, min(64, (int) config('upsoftware.auth.otp.code_length', 8)));
    }

    protected function otpCodePattern(): string
    {
        $pattern = strtolower(trim((string) config('upsoftware.auth.otp.code_pattern', 'digits')));

        if (! in_array($pattern, ['digits', 'chars', 'digits_and_chars'], true)) {
            return 'digits';
        }

        return $pattern;
    }

    protected function generateOtpCode(): string
    {
        $length = $this->otpCodeLength();
        $pattern = $this->otpCodePattern();

        if ($pattern === 'chars') {
            return $this->randomFromAlphabet('ABCDEFGHIJKLMNOPQRSTUVWXYZ', $length);
        }

        if ($pattern === 'digits_and_chars') {
            return $this->randomFromAlphabet('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', $length);
        }

        return $this->randomFromAlphabet('0123456789', $length);
    }

    protected function randomFromAlphabet(string $alphabet, int $length): string
    {
        $maxIndex = strlen($alphabet) - 1;
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $alphabet[random_int(0, $maxIndex)];
        }

        return $result;
    }

    protected function normalizeOtpCode(mixed $code): string
    {
        $normalized = strtoupper(trim((string) $code));

        if ($normalized === '') {
            return '';
        }

        return $normalized;
    }
}
