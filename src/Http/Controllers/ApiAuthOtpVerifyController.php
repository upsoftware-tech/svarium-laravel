<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
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
            $otpToken = (string) ($userAuthModel->hash ?? '');

            return response()->json([
                'status' => 'otp_locked',
                'requires_otp' => true,
                'otp_token' => $otpToken !== '' ? $otpToken : null,
                'otp_resend_url' => $otpToken !== '' ? $this->buildApiOtpSendUrl($otpToken) : null,
                'otp_resend_after' => $otpToken !== '' ? $this->resolveOtpResendAfterSeconds($otpToken) : 0,
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
            $otpToken = (string) ($userAuthModel->hash ?? '');

            return response()->json([
                'status' => 'invalid',
                'requires_otp' => true,
                'otp_token' => $otpToken !== '' ? $otpToken : null,
                'otp_resend_url' => $otpToken !== '' ? $this->buildApiOtpSendUrl($otpToken) : null,
                'otp_resend_after' => $otpToken !== '' ? $this->resolveOtpResendAfterSeconds($otpToken) : 0,
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

        $token = $this->createApiToken(
            $authManager,
            $userAuthModel->user,
            $deviceName,
            $data['tenant_id'] ?? null
        );

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
     *   device_uuid?:string|null,
     *   tenant_id?:string|null
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
            'tenant_id' => ['nullable', 'string', 'max:191'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var array{
         *   type:string,
         *   code:string,
         *   remember?:bool,
         *   device_name?:string|null,
         *   device_uuid?:string|null,
         *   tenant_id?:string|null
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

    protected function createApiToken(
        AuthManager $authManager,
        mixed $user,
        string $deviceName,
        mixed $requestedTenantId = null
    ): string
    {
        $handler = $authManager->resolveHandler();
        if (! is_object($handler) || ! method_exists($handler, 'createToken')) {
            throw new RuntimeException('Configured API auth handler does not support token creation.');
        }

        $token = (string) $handler->createToken($user, $deviceName);

        if (trim($token) === '') {
            throw new RuntimeException('Empty API token returned by auth handler.');
        }

        $this->attachTenantToToken(
            $token,
            $this->resolveApiTenantId($user, $requestedTenantId)
        );

        return $token;
    }

    protected function attachTenantToToken(string $plainTextToken, ?string $tenantId): void
    {
        if ($tenantId === null || trim($tenantId) === '') {
            return;
        }

        $connection = function_exists('central_connection') ? central_connection() : null;

        if (! \Schema::connection((string) $connection)->hasColumn('personal_access_tokens', 'tenant_id')) {
            return;
        }

        $tokenId = trim((string) Str::before($plainTextToken, '|'));
        if ($tokenId === '') {
            return;
        }

        $tokenQuery = new PersonalAccessToken;
        if (is_string($connection) && trim($connection) !== '') {
            $tokenQuery->setConnection($connection);
        }

        $tokenQuery->newQuery()
            ->whereKey($tokenId)
            ->update(['tenant_id' => $tenantId]);
    }

    protected function resolveApiTenantId(mixed $user, mixed $requestedTenantId = null): ?string
    {
        $resolvedRequestedTenantId = $this->normalizeRequestedTenantId($requestedTenantId);
        if ($resolvedRequestedTenantId !== null) {
            $assignedTenantIds = $this->resolveAssignedTenantIds($user);

            if ($assignedTenantIds->isEmpty() || $assignedTenantIds->contains($resolvedRequestedTenantId)) {
                return $resolvedRequestedTenantId;
            }
        }

        if (function_exists('tenant')) {
            $currentTenant = tenant();
            if (is_object($currentTenant) && method_exists($currentTenant, 'getKey')) {
                $currentTenantId = trim((string) $currentTenant->getKey());
                if ($currentTenantId !== '') {
                    return $currentTenantId;
                }
            }
        }

        $assignedTenantIds = $this->resolveAssignedTenantIds($user);
        if ($assignedTenantIds->count() === 1) {
            return $assignedTenantIds->first();
        }

        return null;
    }

    protected function normalizeRequestedTenantId(mixed $requestedTenantId): ?string
    {
        $value = trim((string) $requestedTenantId);
        if ($value === '') {
            return null;
        }

        $tenantClass = get_model('tenant');
        if (! is_string($tenantClass) || ! class_exists($tenantClass)) {
            return $value;
        }

        if (ctype_digit($value)) {
            return $value;
        }

        $connection = function_exists('central_connection') ? central_connection() : null;

        try {
            $tenantModel = new $tenantClass;
            if (is_string($connection) && trim($connection) !== '' && method_exists($tenantModel, 'setConnection')) {
                $tenantModel->setConnection($connection);
            }

            $tenantQuery = $tenantModel->newQuery();
            $tenantQuery = $this->applyTenantEnvironmentScope($tenantQuery, $tenantModel, $connection);

            $tenant = $tenantQuery
                ->byHash($value)
                ->first();

            if (is_object($tenant) && method_exists($tenant, 'getKey')) {
                return trim((string) $tenant->getKey()) ?: null;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @return Collection<int, string>
     */
    protected function resolveAssignedTenantIds(mixed $user): Collection
    {
        $modelHasTenantClass = get_model('model_has_tenant');
        $tenantClass = get_model('tenant');
        $connection = function_exists('central_connection') ? central_connection() : null;

        if (! is_object($user)) {
            return collect();
        }

        if ($this->shouldBypassTenantScope($user)) {
            return $this->resolveAllTenantIds();
        }

        try {
            if (
                is_string($modelHasTenantClass)
                && class_exists($modelHasTenantClass)
                && \Schema::connection((string) $connection)->hasTable('model_has_tenants')
            ) {
                $modelHasTenant = new $modelHasTenantClass;
                if (is_string($connection) && trim($connection) !== '' && method_exists($modelHasTenant, 'setConnection')) {
                    $modelHasTenant->setConnection($connection);
                }

                $tenantIds = $modelHasTenant->newQuery()
                    ->where('model_type', svarium_model_type($user))
                    ->where('model_id', (string) $user->getKey())
                    ->pluck('tenant_id')
                    ->map(static fn (mixed $id): string => trim((string) $id))
                    ->filter(static fn (string $id): bool => $id !== '')
                    ->unique()
                    ->values();

                return $this->filterTenantIdsByEnvironment($tenantIds, $tenantClass, $connection);
            }

            if (
                is_string($tenantClass)
                && class_exists($tenantClass)
                && \Schema::connection((string) $connection)->hasTable('tenant_users')
            ) {
                $tenantModel = new $tenantClass;
                if (is_string($connection) && trim($connection) !== '' && method_exists($tenantModel, 'setConnection')) {
                    $tenantModel->setConnection($connection);
                }

                $tenantQuery = $tenantModel->newQuery();
                $tenantQuery = $this->applyTenantEnvironmentScope($tenantQuery, $tenantModel, $connection);

                $tenantIds = $tenantQuery
                    ->join('tenant_users', 'tenant_users.tenant_id', '=', 'tenants.id')
                    ->where('tenant_users.user_id', (string) $user->getKey())
                    ->pluck('tenants.id')
                    ->map(static fn (mixed $id): string => trim((string) $id))
                    ->filter(static fn (string $id): bool => $id !== '')
                    ->unique()
                    ->values();

                return $this->filterTenantIdsByEnvironment($tenantIds, $tenantClass, $connection);
            }

        } catch (Throwable) {
            return collect();
        }

        return collect();
    }

    protected function shouldBypassTenantScope(mixed $user): bool
    {
        if (! is_object($user) || ! function_exists('svarium_tenancy_enabled') || ! svarium_tenancy_enabled()) {
            return false;
        }

        $keys = config('upsoftware.auth.tenant_bypass_role_keys', ['superadmin']);
        if (is_string($keys)) {
            $keys = explode(',', $keys);
        }

        if (! is_array($keys)) {
            return false;
        }

        $bypassKeys = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            $keys
        ))));

        $aliasMap = [
            'superadmin' => ['super_admin', 'superadministrator'],
            'admin' => ['administrator'],
        ];

        $expandedKeys = [];
        foreach ($bypassKeys as $key) {
            $expandedKeys[$key] = true;

            foreach (($aliasMap[$key] ?? []) as $alias) {
                $expandedKeys[strtolower(trim((string) $alias))] = true;
            }
        }

        $bypassKeys = array_values(array_filter(array_keys($expandedKeys)));

        if ($bypassKeys === []) {
            return false;
        }

        $matchesBypassKey = static function (array $tokens) use ($bypassKeys): bool {
            foreach ($tokens as $token) {
                $normalized = strtolower(trim((string) $token));
                if ($normalized !== '' && in_array($normalized, $bypassKeys, true)) {
                    return true;
                }
            }

            return false;
        };

        if (function_exists('get_roles')) {
            $roles = get_roles($user);
            foreach ($roles as $role) {
                if (! is_array($role)) {
                    continue;
                }

                $roleId = trim((string) ($role['id'] ?? ''));
                $tokens = [
                    $role['role_key'] ?? '',
                    $role['name'] ?? '',
                    $role['name_locale'] ?? '',
                    $roleId,
                    $roleId !== '' ? 'id:'.$roleId : '',
                ];

                if ($matchesBypassKey($tokens)) {
                    return true;
                }
            }
        }

        try {
            if (method_exists($user, 'hasRole')) {
                foreach ($bypassKeys as $bypassKey) {
                    try {
                        if ($user->hasRole($bypassKey)) {
                            return true;
                        }
                    } catch (Throwable) {
                        // continue with broader fallback matching below
                    }
                }
            }

            if (method_exists($user, 'roles')) {
                $roles = method_exists($user, 'relationLoaded') && $user->relationLoaded('roles')
                    ? $user->getRelation('roles')
                    : $user->roles()->get();

                return $roles->contains(static function ($role) use ($matchesBypassKey): bool {
                    if (! is_object($role) || ! method_exists($role, 'getAttribute')) {
                        return false;
                    }

                    $roleName = $role->getAttribute('name');
                    $roleNameLocale = trim((string) ($role->getAttribute('name_locale') ?? ''));
                    $roleId = trim((string) ($role->getAttribute('id') ?? ''));

                    $resolvedRoleName = '';
                    if (is_array($roleName)) {
                        $locale = app()->getLocale();
                        $fallbackLocale = config('app.fallback_locale', 'en');
                        $resolvedRoleName = trim((string) ($roleName[$locale] ?? $roleName[$fallbackLocale] ?? reset($roleName) ?? ''));
                    } elseif (is_string($roleName)) {
                        $decoded = json_decode($roleName, true);
                        if (is_array($decoded)) {
                            $locale = app()->getLocale();
                            $fallbackLocale = config('app.fallback_locale', 'en');
                            $resolvedRoleName = trim((string) ($decoded[$locale] ?? $decoded[$fallbackLocale] ?? reset($decoded) ?? ''));
                        } else {
                            $resolvedRoleName = trim($roleName);
                        }
                    }

                    $tokens = [
                        $role->getAttribute('role_key'),
                        $roleNameLocale,
                        $resolvedRoleName,
                        $roleId,
                        $roleId !== '' ? 'id:'.$roleId : '',
                    ];

                    return $matchesBypassKey($tokens);
                });
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    /**
     * @return Collection<int, string>
     */
    protected function resolveAllTenantIds(): Collection
    {
        $tenantClass = get_model('tenant');
        $connection = function_exists('central_connection') ? central_connection() : null;

        if (! is_string($tenantClass) || ! class_exists($tenantClass)) {
            return collect();
        }

        try {
            $tenantModel = new $tenantClass;
            if (is_string($connection) && trim($connection) !== '' && method_exists($tenantModel, 'setConnection')) {
                $tenantModel->setConnection($connection);
            }

            $tenantQuery = $tenantModel->newQuery();
            $tenantQuery = $this->applyTenantEnvironmentScope($tenantQuery, $tenantModel, $connection);

            return $tenantQuery
                ->pluck('id')
                ->map(static fn (mixed $id): string => trim((string) $id))
                ->filter(static fn (string $id): bool => $id !== '')
                ->unique()
                ->values();
        } catch (Throwable) {
            return collect();
        }
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
                });

                $seen = [];
                $unique = [];

                foreach ($roles as $role) {
                    if (! is_array($role)) {
                        continue;
                    }

                    $id = trim((string) ($role['id'] ?? ''));
                    $guard = trim((string) ($role['guard_name'] ?? ''));
                    $name = trim((string) ($role['name'] ?? ''));
                    $key = $id.'|'.$guard.'|'.$name;

                    if ($key === '||' || isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = true;
                    $unique[] = $role;
                }

                $roles = array_values($unique);
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
            'tenant' => $this->serializeTenantDirectory($user),
            'active_tenant_id' => $this->resolveActiveTokenTenantId($user),
            'tenant_select_url' => $this->buildApiTenantSelectUrl(),
        ];
    }

    protected function serializeTenantDirectory(mixed $user): array
    {
        try {
            $tenantIds = $this->resolveAssignedTenantIds($user);
            if ($tenantIds->isEmpty()) {
                return [];
            }

            $tenantClass = get_model('tenant');
            $domainClass = get_model('domain');
            $connection = function_exists('central_connection') ? central_connection() : null;

            if (! is_string($tenantClass) || ! class_exists($tenantClass)) {
                return [];
            }

            $tenantModel = new $tenantClass;
            if (is_string($connection) && trim($connection) !== '' && method_exists($tenantModel, 'setConnection')) {
                $tenantModel->setConnection($connection);
            }

            $tenants = $tenantModel->newQuery()
                ->whereIn('id', $tenantIds->all())
                ->get()
                ->keyBy(static fn ($tenant): string => (string) $tenant->getKey());

            $domainsByTenant = collect();

            if (is_string($domainClass) && class_exists($domainClass)) {
                try {
                    $domainModel = new $domainClass;
                    if (is_string($connection) && trim($connection) !== '' && method_exists($domainModel, 'setConnection')) {
                        $domainModel->setConnection($connection);
                    }

                    $domainQuery = $domainModel->newQuery()
                        ->whereIn('tenant_id', $tenantIds->all());

                    if (\Schema::connection((string) $connection)->hasColumn($domainModel->getTable(), 'status')) {
                        $domainQuery->where('status', true);
                    }

                    $domainsByTenant = $domainQuery
                        ->get()
                        ->groupBy(static fn ($domain): string => (string) $domain->tenant_id);
                } catch (Throwable) {
                    $domainsByTenant = collect();
                }
            }

            $rolesByTenant = $this->resolveTenantRoles($user, $tenantIds);

            $payload = [];
            foreach ($tenantIds as $tenantId) {
                $tenant = $tenants->get((string) $tenantId);
                if (! is_object($tenant)) {
                    continue;
                }

                $profilePayload = $this->resolveTenantProfilePayload($tenant, $connection);

                $tenantName = trim((string) (
                    data_get($tenant, 'name')
                    ?? data_get($profilePayload, 'name')
                    ?? data_get($tenant, 'short_name')
                    ?? data_get($profilePayload, 'short_name')
                    ?? $tenant->getKey()
                ));

                $hash = data_get($tenant, 'hash');
                if ((! is_string($hash) || trim($hash) === '') && method_exists($tenant, 'getHash')) {
                    $hash = (string) $tenant->getHash($tenant->getKey());
                }

                $domains = $domainsByTenant->get((string) $tenantId, collect());
                if ($domains instanceof Collection && $domains->isNotEmpty()) {
                    foreach ($domains as $domain) {
                        $domainName = trim((string) ($domain->domain ?? ''));
                        $key = $tenantName.($domainName !== '' ? ':'.$domainName : '');

                        $payload[$key] = [
                            'id' => (string) $tenant->getKey(),
                            'hash' => is_string($hash) ? trim($hash) : null,
                            'short_name' => (string) (
                                data_get($tenant, 'short_name')
                                ?? data_get($profilePayload, 'short_name')
                                ?? ''
                            ),
                            'name' => $tenantName,
                            'domain' => $domainName !== '' ? $domainName : null,
                            'roles' => $rolesByTenant->get((string) $tenantId, []),
                        ];
                    }

                    continue;
                }

                $payload[$tenantName] = [
                    'id' => (string) $tenant->getKey(),
                    'hash' => is_string($hash) ? trim($hash) : null,
                    'short_name' => (string) (
                        data_get($tenant, 'short_name')
                        ?? data_get($profilePayload, 'short_name')
                        ?? ''
                    ),
                    'name' => $tenantName,
                    'domain' => null,
                    'roles' => $rolesByTenant->get((string) $tenantId, []),
                ];
            }

            return $payload;
        } catch (Throwable) {
            return [];
        }
    }

    protected function resolveTenantRoles(mixed $user, Collection $tenantIds): Collection
    {
        $modelHasRoleClass = get_model('model_has_role');
        $connection = function_exists('central_connection') ? central_connection() : null;

        if (! is_string($modelHasRoleClass) || ! class_exists($modelHasRoleClass) || ! is_object($user)) {
            return collect();
        }

        try {
            $modelHasRole = new $modelHasRoleClass;
            if (is_string($connection) && trim($connection) !== '' && method_exists($modelHasRole, 'setConnection')) {
                $modelHasRole->setConnection($connection);
            }

            $query = $modelHasRole->newQuery()
                ->with('role')
                ->where('model_id', (string) $user->getKey())
                ->where('model_type', svarium_model_type($user));

            if (\Schema::connection((string) $connection)->hasColumn($modelHasRole->getTable(), 'status')) {
                $query->where('status', 1);
            }

            if (\Schema::connection((string) $connection)->hasColumn($modelHasRole->getTable(), 'tenant_id')) {
                $query->whereIn('tenant_id', $tenantIds->all());
            } else {
                return collect();
            }

            return $query->get()
                ->groupBy(static fn ($row): string => trim((string) ($row->tenant_id ?? '')))
                ->map(function ($rows): array {
                    return $rows->map(static function ($row): array {
                        $role = $row->role;
                        if (! is_object($role)) {
                            return [];
                        }

                        return [
                            'id' => $role->getKey(),
                            'name' => trim((string) ($role->name_locale ?? $role->name ?? '')),
                            'guard_name' => (string) ($role->guard_name ?? ''),
                        ];
                    })
                        ->filter(static fn (array $role): bool => $role !== [])
                        ->values()
                        ->all();
                });
        } catch (Throwable) {
            return collect();
        }
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

    protected function buildApiOtpSendUrl(string $otpToken): ?string
    {
        $normalized = trim($otpToken);
        if ($normalized === '') {
            return null;
        }

        try {
            return route('svarium.api.auth.otp.send', [
                'userAuth' => $normalized,
            ]);
        } catch (Throwable) {
            $prefix = trim((string) config('upsoftware.api.prefix', 'api/v1'), '/');
            $path = trim(implode('/', array_filter([$prefix, 'auth/otp/'.$normalized.'/send'])), '/');

            return '/'.$path;
        }
    }

    protected function resolveActiveTokenTenantId(mixed $user): ?string
    {
        if (! is_object($user) || ! method_exists($user, 'currentAccessToken')) {
            return null;
        }

        try {
            $token = $user->currentAccessToken();
            if (! is_object($token) || ! method_exists($token, 'getAttribute')) {
                return null;
            }

            $tenantId = trim((string) ($token->getAttribute('tenant_id') ?? ''));

            return $tenantId !== '' ? $tenantId : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function buildApiTenantSelectUrl(): ?string
    {
        try {
            return route('svarium.api.auth.tenant.select');
        } catch (Throwable) {
            try {
                return route('svarium.api.auth.tenant');
            } catch (Throwable) {
                $prefix = trim((string) config('upsoftware.api.prefix', 'api/v1'), '/');
                $path = trim(implode('/', array_filter([$prefix, 'auth/tenant/select'])), '/');

                return '/'.$path;
            }
        }
    }

    protected function resolveOtpResendAfterSeconds(string $otpToken): int
    {
        $normalized = trim($otpToken);
        if ($normalized === '') {
            return 0;
        }

        $userAuthModelClass = get_model('user_auth');
        if (! is_string($userAuthModelClass) || ! class_exists($userAuthModelClass)) {
            return 0;
        }

        try {
            $userAuthModel = $userAuthModelClass::byHash($normalized);
            if (! $userAuthModel || ! method_exists($userAuthModel, 'code')) {
                return 0;
            }

            $resendSeconds = max(0, (int) config('upsoftware.auth.otp.resend_seconds', 60));
            if ($resendSeconds <= 0) {
                return 0;
            }

            $latestCreatedAt = $userAuthModel->code()
                ->latest('id')
                ->value('created_at');

            if (! $latestCreatedAt) {
                return 0;
            }

            $createdAt = Carbon::parse((string) $latestCreatedAt);
            $availableAt = $createdAt->copy()->addSeconds($resendSeconds);

            if (now()->greaterThanOrEqualTo($availableAt)) {
                return 0;
            }

            return max(1, (int) now()->diffInSeconds($availableAt));
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveTenantProfilePayload(mixed $tenant, ?string $connection = null): array
    {
        if (! is_object($tenant) || ! method_exists($tenant, 'profile')) {
            return [];
        }

        if (! (bool) config('upsoftware.tenancy.profile.enabled', true)) {
            return [];
        }

        if (! $this->tenantProfileTableExists($connection)) {
            return [];
        }

        try {
            $profile = $tenant->profile;
            $payload = is_object($profile) ? $profile->getAttribute('payload') : null;

            return is_array($payload) ? $payload : [];
        } catch (Throwable) {
            return [];
        }
    }

    protected function tenantProfileTableExists(?string $connection = null): bool
    {
        $table = trim((string) config('upsoftware.tenancy.profile.table', 'tenant_profiles'));
        if ($table === '') {
            $table = 'tenant_profiles';
        }

        try {
            if (is_string($connection) && trim($connection) !== '') {
                return \Schema::connection($connection)->hasTable($table);
            }

            return \Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    protected function applyTenantEnvironmentScope(mixed $query, mixed $tenantModel, ?string $connection = null): mixed
    {
        $appEnv = $this->resolveAppEnvironment();
        if ($appEnv === null) {
            return $query;
        }

        $table = is_object($tenantModel) && method_exists($tenantModel, 'getTable')
            ? (string) $tenantModel->getTable()
            : 'tenants';

        try {
            $hasEnvColumn = is_string($connection) && trim($connection) !== ''
                ? \Schema::connection($connection)->hasColumn($table, 'env')
                : \Schema::hasColumn($table, 'env');

            if (! $hasEnvColumn || ! is_object($query) || ! method_exists($query, 'where')) {
                return $query;
            }

            return $query->where($table.'.env', $appEnv);
        } catch (Throwable) {
            return $query;
        }
    }

    protected function resolveAppEnvironment(): ?string
    {
        $env = trim((string) env('APP_ENV', app()->environment()));

        return $env !== '' ? $env : null;
    }

    /**
     * @param Collection<int, string> $tenantIds
     * @return Collection<int, string>
     */
    protected function filterTenantIdsByEnvironment(Collection $tenantIds, mixed $tenantClass, ?string $connection = null): Collection
    {
        if ($tenantIds->isEmpty() || ! is_string($tenantClass) || ! class_exists($tenantClass)) {
            return $tenantIds;
        }

        $appEnv = $this->resolveAppEnvironment();
        if ($appEnv === null) {
            return $tenantIds;
        }

        try {
            $tenantModel = new $tenantClass;
            if (is_string($connection) && trim($connection) !== '' && method_exists($tenantModel, 'setConnection')) {
                $tenantModel->setConnection($connection);
            }

            $query = $tenantModel->newQuery()->whereIn('id', $tenantIds->all());
            $query = $this->applyTenantEnvironmentScope($query, $tenantModel, $connection);

            return $query
                ->pluck('id')
                ->map(static fn (mixed $id): string => trim((string) $id))
                ->filter(static fn (string $id): bool => $id !== '')
                ->unique()
                ->values();
        } catch (Throwable) {
            return $tenantIds;
        }
    }
}
