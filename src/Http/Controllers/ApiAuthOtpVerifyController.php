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

            $tenant = $tenantModel->newQuery()
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

                return $modelHasTenant->newQuery()
                    ->where('model_type', svarium_model_type($user))
                    ->where('model_id', (string) $user->getKey())
                    ->pluck('tenant_id')
                    ->map(static fn (mixed $id): string => trim((string) $id))
                    ->filter(static fn (string $id): bool => $id !== '')
                    ->unique()
                    ->values();
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

                return $tenantModel->newQuery()
                    ->join('tenant_users', 'tenant_users.tenant_id', '=', 'tenants.id')
                    ->where('tenant_users.user_id', (string) $user->getKey())
                    ->pluck('tenants.id')
                    ->map(static fn (mixed $id): string => trim((string) $id))
                    ->filter(static fn (string $id): bool => $id !== '')
                    ->unique()
                    ->values();
            }

        } catch (Throwable) {
            return collect();
        }

        return collect();
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
            'tenant' => $this->serializeTenantDirectory($user),
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

                $profilePayload = [];
                if (method_exists($tenant, 'profile')) {
                    $profile = $tenant->profile;
                    $profileData = is_object($profile) ? $profile->getAttribute('payload') : null;
                    if (is_array($profileData)) {
                        $profilePayload = $profileData;
                    }
                }

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
}
