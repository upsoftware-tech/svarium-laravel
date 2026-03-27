<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;
use Throwable;
use Upsoftware\Svarium\Auth\AuthManager;
use Upsoftware\Svarium\Services\Auth\AuthLoginService;

class ApiAuthLoginController extends Controller
{
    public function __invoke(
        Request $request,
        AuthLoginService $authLoginService,
        AuthManager $authManager
    ): JsonResponse {
        $data = $this->validated($request);

        $result = $authLoginService->attempt(
            $request,
            (string) $data['email'],
            (string) $data['password']
        );

        if (($result['status'] ?? null) === AuthLoginService::STATUS_INVALID) {
            return response()->json([
                'status' => 'invalid',
                'message' => __('The given data was invalid.'),
                'errors' => [
                    'email' => [__('Nieprawidłowe dane logowania')],
                ],
            ], 422);
        }

        if (($result['status'] ?? null) === AuthLoginService::STATUS_OTP_REQUIRED) {
            $otpToken = trim((string) ($result['otp_token'] ?? ''));
            $otpResendUrl = $otpToken !== '' ? $this->buildApiOtpUrl($otpToken) : null;
            $otpResendAfter = $otpToken !== '' ? $this->resolveOtpResendAfterSeconds($otpToken) : 0;

            return response()->json([
                'status' => AuthLoginService::STATUS_OTP_REQUIRED,
                'token' => null,
                'requires_otp' => true,
                'otp_token' => $otpToken !== '' ? $otpToken : null,
                'otp_url' => $otpResendUrl,
                'otp_resend_url' => $otpResendUrl,
                'otp_resend_after' => $otpResendAfter,
                'otp_verify_url' => $otpToken !== '' ? $this->buildApiOtpVerifyUrl($otpToken) : null,
                'otp_methods' => $this->buildOtpVerificationMethods($authLoginService, $result['user'] ?? null),
            ], 200);
        }

        $user = $result['user'] ?? null;
        if (! is_object($user)) {
            throw new RuntimeException('Unable to resolve authenticated user.');
        }

        $deviceUuid = $this->resolveDeviceUuid($data['device_uuid'] ?? null);
        $deviceName = $this->resolveDeviceName($data['device_name'] ?? null, $deviceUuid);

        $this->persistDevice($request, $user, $deviceUuid, $deviceName);

        $token = $this->createApiToken(
            $authManager,
            $user,
            $deviceName,
            $data['tenant_id'] ?? null
        );

        return response()->json([
            'status' => 'authenticated',
            'token' => $token,
            'requires_otp' => false,
            'user' => $this->serializeUser($user),
        ], 200);
    }

    /**
     * @return array{
     *   email:string,
     *   password:string,
     *   device_name?:string|null,
     *   device_uuid?:string|null,
     *   tenant_id?:string|null
     * }
     */
    protected function validated(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:191'],
            'device_uuid' => ['nullable', 'uuid'],
            'tenant_id' => ['nullable', 'string', 'max:191'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var array{
         *   email:string,
         *   password:string,
         *   device_name?:string|null,
         *   device_uuid?:string|null,
         *   tenant_id?:string|null
         * } $data
         */
        $data = $validator->validated();

        return $data;
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

            if (is_string($tenantClass) && class_exists($tenantClass)) {
                $tenantModel = new $tenantClass;
                if (is_string($connection) && trim($connection) !== '' && method_exists($tenantModel, 'setConnection')) {
                    $tenantModel->setConnection($connection);
                }

                $query = $tenantModel->newQuery();

                if (\Schema::connection((string) $connection)->hasColumn('tenants', 'status')) {
                    $query->where('status', true);
                }

                return $query
                    ->pluck('id')
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

        return [
            'id' => $user->getKey(),
            'name' => (string) ($user->getAttribute('name') ?? ''),
            'email' => (string) ($user->getAttribute('email') ?? ''),
            'email_verified_at' => $this->toIsoString($user->getAttribute('email_verified_at') ?? null),
            'roles' => $this->serializeRoles($user),
            'institutions' => $this->serializeInstitutions($user),
        ];
    }

    protected function serializeRoles(mixed $user): array
    {
        try {
            if (! method_exists($user, 'roles')) {
                return [];
            }

            $roles = method_exists($user, 'relationLoaded') && $user->relationLoaded('roles')
                ? $user->getRelation('roles')
                : $user->roles()->get();

            $locale = app()->getLocale();

            return $roles->map(static function ($role) use ($locale): array {
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
        } catch (Throwable) {
            return [];
        }
    }

    protected function serializeInstitutions(mixed $user): array
    {
        try {
            $tenantClass = get_model('tenant');
            $connection = function_exists('central_connection') ? central_connection() : null;

            if (! is_string($tenantClass) || ! class_exists($tenantClass)) {
                return [];
            }

            $tenantIds = $this->resolveAssignedTenantIds($user);

            if ($tenantIds->isEmpty()) {
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

            $currentTenantId = null;
            if (function_exists('tenant')) {
                $tenant = tenant();
                if (is_object($tenant) && method_exists($tenant, 'getKey')) {
                    $currentTenantId = (string) $tenant->getKey();
                }
            }

            $list = [];
            foreach ($tenantIds as $tenantId) {
                $tenant = $tenants->get((string) $tenantId);
                if (! is_object($tenant)) {
                    continue;
                }

                $profilePayload = [];
                if (method_exists($tenant, 'profile')) {
                    $profile = $tenant->profile;
                    $payload = is_object($profile) ? $profile->getAttribute('payload') : null;
                    if (is_array($payload)) {
                        $profilePayload = $payload;
                    }
                }

                $hash = data_get($tenant, 'hash');
                if (! is_string($hash) || trim($hash) === '') {
                    $hash = data_get($profilePayload, 'hash');
                }
                if ((! is_string($hash) || trim($hash) === '') && method_exists($tenant, 'getHash')) {
                    $hash = (string) $tenant->getHash($tenant->getKey());
                }

                $list[] = [
                    'id' => $tenant->getKey(),
                    'hash' => is_string($hash) ? trim($hash) : null,
                    'short_name' => (string) (
                        data_get($tenant, 'short_name')
                        ?? data_get($profilePayload, 'short_name')
                        ?? ''
                    ),
                    'name' => (string) (
                        data_get($tenant, 'name')
                        ?? data_get($profilePayload, 'name')
                        ?? ''
                    ),
                    'sio' => data_get($tenant, 'sio') ?? data_get($profilePayload, 'sio'),
                    'default' => $currentTenantId !== null
                        ? ((string) $tenant->getKey() === $currentTenantId)
                        : false,
                ];
            }

            if ($list !== [] && ! collect($list)->contains(static fn (array $item): bool => (bool) ($item['default'] ?? false))) {
                $list[0]['default'] = true;
            }

            return $list;
        } catch (Throwable) {
            return [];
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

    protected function buildOtpVerificationMethods(AuthLoginService $authLoginService, mixed $user): array
    {
        if (! is_object($user) || ! $authLoginService->isOtpGloballyEnabled()) {
            return [];
        }

        $definitions = $this->otpMethodDefinitions();
        $methods = [];

        $methodsToShow = $authLoginService->showAllOtpMethods()
            ? $authLoginService->supportedOtpMethods()
            : $authLoginService->allowedOtpMethods();

        foreach ($methodsToShow as $method) {
            if (! isset($definitions[$method])) {
                continue;
            }

            $isAllowed = $authLoginService->isOtpMethodAllowed($method);
            $isAvailable = $authLoginService->isOtpMethodAvailableForUser($user, $method);
            $isDisabled = ! $isAllowed || ! $isAvailable;

            if (! $authLoginService->showAllOtpMethods() && $isDisabled) {
                continue;
            }

            $methods[] = [
                'id' => $method,
                'disabled' => $isDisabled,
                'label' => $definitions[$method]['label'],
                'description' => $definitions[$method]['description'],
            ];
        }

        return $methods;
    }

    /**
     * @return array<string, array{label:string, description:string}>
     */
    protected function otpMethodDefinitions(): array
    {
        return [
            'app' => [
                'label' => __('svarium::messages.Google Authenticator App'),
                'description' => __('svarium::messages.The Google Authenticator app is available on all platforms, including iOS and Android'),
            ],
            'sms' => [
                'label' => __('svarium::messages.SMS message'),
                'description' => __('svarium::messages.SMS message to the registered phone number'),
            ],
            'email' => [
                'label' => __('svarium::messages.Email message'),
                'description' => __('svarium::messages.Email message to the registered email address'),
            ],
        ];
    }

    protected function buildApiOtpUrl(string $otpToken): ?string
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

    protected function buildApiOtpVerifyUrl(string $otpToken): ?string
    {
        $normalized = trim($otpToken);

        if ($normalized === '') {
            return null;
        }

        try {
            return route('svarium.api.auth.otp.verify', [
                'userAuth' => $normalized,
            ]);
        } catch (Throwable) {
            $prefix = trim((string) config('upsoftware.api.prefix', 'api/v1'), '/');
            $path = trim(implode('/', array_filter([$prefix, 'auth/otp/'.$normalized.'/verify'])), '/');

            return '/'.$path;
        }
    }

    protected function resolveOtpResendAfterSeconds(string $otpToken): int
    {
        $normalized = trim($otpToken);
        if ($normalized === '') {
            return 0;
        }

        $userAuthModel = get_model('user_auth');
        if (! is_string($userAuthModel) || ! class_exists($userAuthModel)) {
            return 0;
        }

        try {
            $userAuth = $userAuthModel::byHash($normalized);
            if (! $userAuth || ! method_exists($userAuth, 'code')) {
                return 0;
            }

            $resendSeconds = max(0, (int) config('upsoftware.auth.otp.resend_seconds', 60));
            if ($resendSeconds <= 0) {
                return 0;
            }

            $latestCreatedAt = $userAuth->code()
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
