<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

            return response()->json([
                'status' => AuthLoginService::STATUS_OTP_REQUIRED,
                'token' => null,
                'requires_otp' => true,
                'otp_token' => $otpToken !== '' ? $otpToken : null,
                'otp_url' => $otpToken !== '' ? $this->buildApiOtpUrl($otpToken) : null,
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

        $token = $this->createApiToken($authManager, $user, $deviceName);

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
     *   device_uuid?:string|null
     * }
     */
    protected function validated(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:191'],
            'device_uuid' => ['nullable', 'uuid'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var array{
         *   email:string,
         *   password:string,
         *   device_name?:string|null,
         *   device_uuid?:string|null
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
            $modelHasTenantClass = get_model('model_has_tenant');
            $tenantClass = get_model('tenant');

            if (! is_string($modelHasTenantClass) || ! class_exists($modelHasTenantClass)) {
                return [];
            }

            if (! is_string($tenantClass) || ! class_exists($tenantClass)) {
                return [];
            }

            $tenantIds = $modelHasTenantClass::query()
                ->where('model_type', svarium_model_type($user))
                ->where('model_id', (string) $user->getKey())
                ->pluck('tenant_id')
                ->filter(static fn (mixed $id): bool => $id !== null && $id !== '')
                ->unique()
                ->values();

            if ($tenantIds->isEmpty()) {
                return [];
            }

            $tenants = $tenantClass::query()
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
}
