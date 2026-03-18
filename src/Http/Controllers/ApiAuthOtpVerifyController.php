<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use Upsoftware\Svarium\Auth\AuthManager;
use Upsoftware\Svarium\Services\Auth\AuthLoginService;

class ApiAuthOtpVerifyController extends Controller
{
    protected const ALLOWED_TYPES = ['login', 'register', 'reset'];

    public function __invoke(
        Request $request,
        AuthLoginService $authLoginService,
        AuthManager $authManager,
        string $userAuth
    ): JsonResponse {
        $data = $this->validated($request);
        $type = strtolower(trim((string) ($data['type'] ?? '')));
        $code = trim((string) ($data['code'] ?? ''));

        $userAuthModel = $this->resolveUserAuth($userAuth, $type);

        $lockSeconds = $this->verificationLockSeconds($request, $userAuthModel);
        if ($lockSeconds > 0) {
            return response()->json([
                'status' => 'otp_locked',
                'requires_otp' => true,
                'otp_token' => $userAuthModel->hash ?? null,
                'retry_after' => $lockSeconds,
                'message' => __('Too many invalid attempts. Try again in :seconds seconds.', ['seconds' => $lockSeconds]),
                'errors' => [
                    'code' => [
                        __('Too many invalid attempts. Try again in :seconds seconds.', ['seconds' => $lockSeconds]),
                    ],
                ],
            ], 429);
        }

        if ($code === '' || ! $userAuthModel->verifyCode($code)) {
            $this->registerFailedVerificationAttempt($request, $userAuthModel);
            $lockSeconds = $this->verificationLockSeconds($request, $userAuthModel);

            $message = $lockSeconds > 0
                ? __('Too many invalid attempts. Try again in :seconds seconds.', ['seconds' => $lockSeconds])
                : __('svarium::messages.Invalid verification code');

            return response()->json([
                'status' => 'invalid',
                'requires_otp' => true,
                'otp_token' => $userAuthModel->hash ?? null,
                'message' => $message,
                'errors' => [
                    'code' => [$message],
                ],
            ], 422);
        }

        $this->clearFailedVerificationAttempts($request, $userAuthModel);

        if ($type === 'reset') {
            return response()->json([
                'status' => 'otp_verified',
                'requires_otp' => false,
                'otp_token' => $userAuthModel->hash ?? null,
                'message' => __('Verification code accepted.'),
            ], 200);
        }

        if ($type === 'register') {
            $this->markEmailAsVerified($userAuthModel->user);
        }

        $remember = $type === 'login' && (bool) ($data['remember'] ?? false);
        $authLoginService->loginUser($request, $userAuthModel->user, $remember);

        $deviceUuid = $this->resolveDeviceUuid($data['device_uuid'] ?? null);
        $deviceName = $this->resolveDeviceName($data['device_name'] ?? null, $deviceUuid);
        $this->persistDevice($request, $userAuthModel->user, $deviceUuid, $deviceName);

        $token = $this->createApiToken($authManager, $userAuthModel->user, $deviceName);

        return response()->json([
            'status' => 'authenticated',
            'token' => $token,
            'requires_otp' => false,
            'user' => $this->serializeUser($userAuthModel->user),
        ], 200);
    }

    /**
     * @return array{
     *   type:string,
     *   code:string,
     *   remember?:bool,
     *   device_name?:string|null,
     *   device_uuid?:string|null
     * }
     */
    protected function validated(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', Rule::in(self::ALLOWED_TYPES)],
            'code' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
            'device_name' => ['nullable', 'string', 'max:191'],
            'device_uuid' => ['nullable', 'uuid'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var array{
         *   type:string,
         *   code:string,
         *   remember?:bool,
         *   device_name?:string|null,
         *   device_uuid?:string|null
         * } $data
         */
        $data = $validator->validated();

        return $data;
    }

    protected function resolveUserAuth(string $hash, string $type): mixed
    {
        $normalizedHash = trim($hash);
        $userAuth = $normalizedHash !== ''
            ? get_model('user_auth')::byHash($normalizedHash)
            : null;

        if (! $userAuth || ! $userAuth->user || $this->isUserAuthTokenExpired($userAuth)) {
            throw ValidationException::withMessages([
                'token' => [__('Invalid OTP session token.')],
            ]);
        }

        $storedType = strtolower(trim((string) ($userAuth->type ?? '')));
        if ($storedType === '' || $storedType !== $type) {
            throw ValidationException::withMessages([
                'type' => [__('Invalid authentication type for OTP token.')],
            ]);
        }

        return $userAuth;
    }

    protected function isUserAuthTokenExpired(mixed $userAuth): bool
    {
        $ttlMinutes = max(1, (int) config('upsoftware.auth.otp.token_ttl_minutes', 10));
        $createdAtValue = $userAuth->created_at ?? null;

        if (! $createdAtValue) {
            return true;
        }

        try {
            $createdAt = Carbon::parse((string) $createdAtValue);
        } catch (Throwable) {
            return true;
        }

        return now()->greaterThan($createdAt->copy()->addMinutes($ttlMinutes));
    }

    protected function verificationRateLimitKey(Request $request, mixed $userAuth): string
    {
        return sprintf(
            'svarium:otp:verify:%s:%s',
            (string) ($userAuth->id ?? $userAuth->hash ?? 'unknown'),
            $this->normalizeRateLimitIp($request->ip())
        );
    }

    protected function verificationMaxFailedAttempts(): int
    {
        return max(0, (int) config('upsoftware.auth.otp.verification.max_failed_attempts', 5));
    }

    protected function verificationLockMinutes(): int
    {
        return max(0, (int) config('upsoftware.auth.otp.verification.lock_minutes', 15));
    }

    protected function verificationLockSeconds(Request $request, mixed $userAuth): int
    {
        $maxAttempts = $this->verificationMaxFailedAttempts();
        if ($maxAttempts <= 0) {
            return 0;
        }

        $key = $this->verificationRateLimitKey($request, $userAuth);
        if (! $this->rateLimiterTooManyAttempts($key, $maxAttempts)) {
            return 0;
        }

        return max(1, $this->rateLimiterAvailableIn($key));
    }

    protected function registerFailedVerificationAttempt(Request $request, mixed $userAuth): void
    {
        $maxAttempts = $this->verificationMaxFailedAttempts();
        $lockMinutes = $this->verificationLockMinutes();

        if ($maxAttempts <= 0 || $lockMinutes <= 0) {
            return;
        }

        $this->rateLimiterHit(
            $this->verificationRateLimitKey($request, $userAuth),
            max(1, $lockMinutes) * 60
        );
    }

    protected function clearFailedVerificationAttempts(Request $request, mixed $userAuth): void
    {
        $this->rateLimiterClear($this->verificationRateLimitKey($request, $userAuth));
    }

    protected function otpRateLimiter(): CacheRateLimiter
    {
        $store = trim((string) config('upsoftware.auth.otp.rate_limit_store', 'file'));

        if ($store !== '' && config("cache.stores.{$store}") !== null) {
            try {
                return new CacheRateLimiter(Cache::store($store));
            } catch (Throwable) {
                // fallback below
            }
        }

        try {
            return app(CacheRateLimiter::class);
        } catch (Throwable) {
            return new CacheRateLimiter(Cache::store('array'));
        }
    }

    protected function rateLimiterTooManyAttempts(string $key, int $maxAttempts): bool
    {
        try {
            return $this->otpRateLimiter()->tooManyAttempts($key, $maxAttempts);
        } catch (Throwable) {
            return false;
        }
    }

    protected function rateLimiterAvailableIn(string $key): int
    {
        try {
            return max(0, (int) $this->otpRateLimiter()->availableIn($key));
        } catch (Throwable) {
            return 0;
        }
    }

    protected function rateLimiterHit(string $key, int $decaySeconds): void
    {
        try {
            $this->otpRateLimiter()->hit($key, $decaySeconds);
        } catch (Throwable) {
            // Ignore cache store issues.
        }
    }

    protected function rateLimiterClear(string $key): void
    {
        try {
            $this->otpRateLimiter()->clear($key);
        } catch (Throwable) {
            // Ignore cache store issues.
        }
    }

    protected function normalizeRateLimitIp(?string $ip): string
    {
        $value = trim((string) $ip);
        if ($value === '') {
            return 'unknown';
        }

        return str_replace([':', '.'], '-', $value);
    }

    protected function markEmailAsVerified(mixed $user): void
    {
        if (! is_object($user)) {
            return;
        }

        if (
            method_exists($user, 'hasVerifiedEmail')
            && method_exists($user, 'markEmailAsVerified')
            && ! $user->hasVerifiedEmail()
        ) {
            $user->markEmailAsVerified();
            return;
        }

        if (method_exists($user, 'forceFill') && method_exists($user, 'save')) {
            try {
                $user->forceFill([
                    'email_verified_at' => now(),
                ])->save();
            } catch (Throwable) {
                // ignore verification timestamp errors
            }
        }
    }

    protected function resolveDeviceUuid(mixed $value): string
    {
        $uuid = trim((string) $value);

        if ($uuid !== '') {
            return $uuid;
        }

        return (string) Str::uuid();
    }

    protected function resolveDeviceName(mixed $value, string $deviceUuid): string
    {
        $name = trim((string) $value);

        if ($name !== '') {
            return $name;
        }

        return 'api_'.substr($deviceUuid, 0, 12);
    }

    protected function createApiToken(AuthManager $authManager, mixed $user, string $deviceName): string
    {
        $handler = $authManager->resolveHandler();
        if (! is_object($handler) || ! method_exists($handler, 'createToken')) {
            throw new RuntimeException('Configured API auth handler does not support token creation.');
        }

        $token = (string) $handler->createToken($user, $deviceName);

        if (trim($token) === '') {
            throw new RuntimeException('Empty API token returned by auth handler.');
        }

        return $token;
    }

    protected function persistDevice(Request $request, mixed $user, string $deviceUuid, string $deviceName): void
    {
        $deviceModelClass = get_model('device');
        $deviceUserModelClass = get_model('device_user');

        if (! is_string($deviceModelClass) || ! class_exists($deviceModelClass)) {
            throw new RuntimeException('Device model is not defined in configuration.');
        }

        if (! is_string($deviceUserModelClass) || ! class_exists($deviceUserModelClass)) {
            throw new RuntimeException('DeviceUser model is not defined in configuration.');
        }

        $device = $deviceModelClass::query()->firstOrNew([
            'device_uuid' => $deviceUuid,
        ]);

        $existingData = $device->getAttribute('data');
        if (! is_array($existingData)) {
            $existingData = [];
        }

        $device->setAttribute('device_type', 'api');
        $device->setAttribute('ip', (string) ($request->ip() ?? '0.0.0.0'));
        $device->setAttribute('data', array_merge($existingData, array_filter([
            'device_name' => $deviceName,
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'api' => true,
        ], static fn (mixed $value): bool => $value !== null && $value !== '')));
        $device->save();

        $deviceUserModelClass::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'device_id' => $device->getKey(),
            ],
            [
                'name' => $deviceName,
                'verified_at' => now(),
                'data' => [
                    'source' => 'api',
                    'last_login_at' => now()->toIso8601String(),
                ],
            ]
        );
    }

    protected function serializeUser(mixed $user): ?array
    {
        if (! is_object($user)) {
            return null;
        }

        $locale = app()->getLocale();
        $roles = [];

        try {
            if (method_exists($user, 'roles')) {
                $loadedRoles = method_exists($user, 'relationLoaded') && $user->relationLoaded('roles')
                    ? $user->getRelation('roles')
                    : $user->roles()->get();

                $roles = $loadedRoles->map(static function ($role) use ($locale): array {
                    $name = trim((string) ($role->getAttribute('name_locale') ?? ''));
                    if ($name === '') {
                        $raw = $role->getAttribute('name');

                        if (is_array($raw)) {
                            $candidate = $raw[$locale] ?? reset($raw);
                            $name = is_string($candidate) ? trim($candidate) : '';
                        } elseif (is_string($raw)) {
                            $decoded = json_decode($raw, true);
                            if (is_array($decoded)) {
                                $candidate = $decoded[$locale] ?? reset($decoded);
                                $name = is_string($candidate) ? trim($candidate) : '';
                            } else {
                                $name = trim($raw);
                            }
                        }
                    }

                    return [
                        'id' => $role->getKey(),
                        'name' => $name,
                        'guard_name' => (string) ($role->getAttribute('guard_name') ?? ''),
                    ];
                })->values()->all();
            }
        } catch (Throwable) {
            $roles = [];
        }

        return [
            'id' => $user->getKey(),
            'name' => (string) ($user->getAttribute('name') ?? ''),
            'email' => (string) ($user->getAttribute('email') ?? ''),
            'email_verified_at' => $this->toIsoString($user->getAttribute('email_verified_at') ?? null),
            'roles' => $roles,
        ];
    }

    protected function toIsoString(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value)->toIso8601String();
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }
}

