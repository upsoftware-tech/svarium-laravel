<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Upsoftware\Svarium\Traits\HasHash;
use Upsoftware\Svarium\Traits\UsesConnection;
use Throwable;

class UserAuth extends Model
{
    use HasHash, UsesConnection;

    public $guarded = [];

    public function code()
    {
        return $this->hasMany(UserAuthCode::class);
    }

    public static function setToken(mixed $user, mixed $type)
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

        $payload = [
            'code' => $code,
            'method' => $method,
            'expired_at' => now()->addMinutes($ttlMinutes),
        ];

        $payload = array_merge($payload, $this->resolveCodeContextPayload('sent'));

        return $this->code()->create($payload);
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

        $updatePayload = ['is_used' => true];
        $updatePayload = array_merge($updatePayload, $this->resolveCodeContextPayload('used'));
        $authCode->fill($updatePayload);
        $authCode->save();
        $this->logOtpCodeUsed($authCode);

        return true;
    }

    public function user()
    {
        $userModel = get_model('user');

        return $this->hasOne($userModel, 'id', 'user_id');
    }

    public function sendSms(?string $type = null)
    {
        $code = $this->generateCode('sms');
        $this->user->notify(new SendCodeNotification($code->code, $code->expired_at));
        $this->save();
        $this->logOtpCodeDispatched('sms', $code, $type);
    }

    public function sendEmail(?string $type = null)
    {
        $code = $this->generateCode('email');
        $class = 'Upsoftware\\Svarium\\Notifications\\SendCodeNotificationEmail'.ucfirst($type);

        if (! class_exists($class)) {
            throw new \Exception("Notification class {$class} does not exist.");
        }

        $this->user->notify(
            new $class($code->code, $code->expired_at)
        );
        $this->logOtpCodeDispatched('email', $code, $type);
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

    protected function resolveCodeContextPayload(string $prefix): array
    {
        $prefix = strtolower(trim($prefix));
        if (! in_array($prefix, ['sent', 'used'], true)) {
            return [];
        }

        $columns = $this->codeLogColumns();
        if ($columns === []) {
            return [];
        }

        $request = null;
        try {
            $request = request();
        } catch (Throwable) {
            $request = null;
        }

        $ip = trim((string) ($request?->ip() ?? ''));
        $userAgent = trim((string) ($request?->userAgent() ?? ''));

        $device = [];
        if (function_exists('device')) {
            try {
                $resolved = device();
                if (is_array($resolved)) {
                    $device = $resolved;
                }
            } catch (Throwable) {
                $device = [];
            }
        }

        $payload = [];
        $mapping = [
            "{$prefix}_ip" => $ip !== '' ? $ip : null,
            "{$prefix}_user_agent" => $userAgent !== '' ? $userAgent : null,
            "{$prefix}_device_type" => $this->trimOrNull($device['deviceType'] ?? null),
            "{$prefix}_platform" => $this->trimOrNull($device['platform'] ?? null),
            "{$prefix}_platform_ver" => $this->trimOrNull($device['platformVer'] ?? null),
            "{$prefix}_browser" => $this->trimOrNull($device['browser'] ?? null),
            "{$prefix}_browser_ver" => $this->trimOrNull($device['browserVer'] ?? null),
        ];

        foreach ($mapping as $column => $value) {
            if (! in_array($column, $columns, true)) {
                continue;
            }

            $payload[$column] = $value;
        }

        if ($prefix === 'used' && in_array('used_at', $columns, true)) {
            $payload['used_at'] = now();
        }

        return $payload;
    }

    /**
     * @return array<int, string>
     */
    protected function codeLogColumns(): array
    {
        static $cache = [];

        $codeRelation = $this->code()->getRelated();
        $table = (string) $codeRelation->getTable();
        $connection = $codeRelation->getConnectionName();
        $cacheKey = ($connection ?: 'default').':'.$table;

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        if ($table === '') {
            return $cache[$cacheKey] = [];
        }

        $columns = [
            'used_at',
            'sent_ip',
            'sent_user_agent',
            'sent_device_type',
            'sent_platform',
            'sent_platform_ver',
            'sent_browser',
            'sent_browser_ver',
            'used_ip',
            'used_user_agent',
            'used_device_type',
            'used_platform',
            'used_platform_ver',
            'used_browser',
            'used_browser_ver',
        ];

        $available = [];

        foreach ($columns as $column) {
            try {
                $hasColumn = is_string($connection) && $connection !== ''
                    ? Schema::connection($connection)->hasColumn($table, $column)
                    : Schema::hasColumn($table, $column);
            } catch (Throwable) {
                $hasColumn = false;
            }

            if ($hasColumn) {
                $available[] = $column;
            }
        }

        return $cache[$cacheKey] = $available;
    }

    protected function trimOrNull(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    protected function logOtpCodeDispatched(string $method, mixed $code, ?string $authType = null): void
    {
        try {
            Log::info('svarium.otp.code_sent', [
                'user_auth_id' => $this->getKey(),
                'user_id' => $this->user_id,
                'type' => $authType !== null ? trim($authType) : null,
                'method' => trim($method),
                'code_id' => is_object($code) ? data_get($code, 'id') : null,
                'expires_at' => is_object($code) ? data_get($code, 'expired_at') : null,
                'sent_ip' => is_object($code) ? data_get($code, 'sent_ip') : null,
                'sent_device_type' => is_object($code) ? data_get($code, 'sent_device_type') : null,
                'sent_platform' => is_object($code) ? data_get($code, 'sent_platform') : null,
                'sent_platform_ver' => is_object($code) ? data_get($code, 'sent_platform_ver') : null,
                'sent_browser' => is_object($code) ? data_get($code, 'sent_browser') : null,
                'sent_browser_ver' => is_object($code) ? data_get($code, 'sent_browser_ver') : null,
            ]);
        } catch (Throwable) {
            // Logging must never break OTP flow.
        }
    }

    protected function logOtpCodeUsed(mixed $code): void
    {
        try {
            Log::info('svarium.otp.code_used', [
                'user_auth_id' => $this->getKey(),
                'user_id' => $this->user_id,
                'code_id' => is_object($code) ? data_get($code, 'id') : null,
                'method' => is_object($code) ? data_get($code, 'method') : null,
                'used_at' => is_object($code) ? data_get($code, 'used_at') : null,
                'used_ip' => is_object($code) ? data_get($code, 'used_ip') : null,
                'used_device_type' => is_object($code) ? data_get($code, 'used_device_type') : null,
                'used_platform' => is_object($code) ? data_get($code, 'used_platform') : null,
                'used_platform_ver' => is_object($code) ? data_get($code, 'used_platform_ver') : null,
                'used_browser' => is_object($code) ? data_get($code, 'used_browser') : null,
                'used_browser_ver' => is_object($code) ? data_get($code, 'used_browser_ver') : null,
            ]);
        } catch (Throwable) {
            // Logging must never break OTP flow.
        }
    }
}
