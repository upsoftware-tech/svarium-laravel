<?php

namespace Upsoftware\Svarium\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;
use Upsoftware\Svarium\Facades\DeviceTracker;
use Upsoftware\Svarium\Notifications\LoginFromNewDeviceNotify;

class AuthLoginService
{
    protected const OTP_SUPPORTED_METHODS = ['email', 'sms', 'app'];

    public const STATUS_INVALID = 'invalid';
    public const STATUS_OTP_REQUIRED = 'otp_required';
    public const STATUS_AUTHENTICATED = 'authenticated';

    /**
     * @return array{
     *   status: string,
     *   user: mixed,
     *   requires_otp: bool,
     *   otp_disabled_by_user: bool,
     *   otp_url: string|null,
     *   redirect_url: string|null
     * }
     */
    public function attempt(Request $request, string $email, string $password): array
    {
        $user = $this->findUserByEmail($email);

        if (! $user || ! $this->hasRoleInTenant($user) || ! Hash::check($password, (string) $user->password)) {
            return $this->invalidResult();
        }

        $remember = $this->shouldRemember($request);
        $requiresOtp = $this->requiresOtp($user);
        $otpDisabledByUser = $this->isOtpDisabledByUser($user);

        if ($this->isRememberedBrowser($request, $user)) {
            $redirectUrl = $this->loginUser($request, $user, $remember);

            return [
                'status' => self::STATUS_AUTHENTICATED,
                'user' => $user,
                'requires_otp' => false,
                'otp_disabled_by_user' => $otpDisabledByUser,
                'otp_url' => null,
                'redirect_url' => $redirectUrl,
            ];
        }

        if ($requiresOtp) {
            $userAuth = get_model('user_auth')::setToken($user, 'login');

            return [
                'status' => self::STATUS_OTP_REQUIRED,
                'user' => $user,
                'requires_otp' => true,
                'otp_disabled_by_user' => false,
                'otp_url' => route_panel('method', [
                    'type' => 'login',
                    'userAuth' => $userAuth->hash,
                ]),
                'redirect_url' => null,
            ];
        }

        $redirectUrl = $this->loginUser($request, $user, $remember);

        return [
            'status' => self::STATUS_AUTHENTICATED,
            'user' => $user,
            'requires_otp' => false,
            'otp_disabled_by_user' => $otpDisabledByUser,
            'otp_url' => null,
            'redirect_url' => $redirectUrl,
        ];
    }

    public function requiresOtp(mixed $user): bool
    {
        if (! $this->isOtpGloballyEnabled()) {
            return false;
        }

        if (! $this->hasAvailableOtpMethodForUser($user)) {
            return false;
        }

        if (! $this->canUserDisableOtp()) {
            return true;
        }

        $connection = svarium_tenancy_database_mode() ? central_connection() : null;

        if (! is_object($user) || ! method_exists($user, 'getSetting')) {
            return $this->otpDefaultEnabled();
        }

        return $user->getSetting('otp_status', $this->otpDefaultEnabled(), $connection) === true;
    }

    public function isOtpDisabledByUser(mixed $user): bool
    {
        if (! $this->isOtpGloballyEnabled()) {
            return false;
        }

        if (! $this->canUserDisableOtp()) {
            return false;
        }

        return ! $this->requiresOtp($user);
    }

    public function isOtpGloballyEnabled(): bool
    {
        return (bool) config('upsoftware.auth.otp.enabled', true);
    }

    public function canUserDisableOtp(): bool
    {
        return (bool) config('upsoftware.auth.otp.allow_user_disable', true);
    }

    public function otpDefaultEnabled(): bool
    {
        return (bool) config('upsoftware.auth.otp.default_enabled', true);
    }

    public function showAllOtpMethods(): bool
    {
        return (bool) config('upsoftware.auth.otp.show_all_methods', false);
    }

    public function allowedOtpMethods(): array
    {
        $configured = config('upsoftware.auth.otp.methods', self::OTP_SUPPORTED_METHODS);
        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }

        if (! is_array($configured)) {
            $configured = self::OTP_SUPPORTED_METHODS;
        }

        $normalized = [];

        foreach ($configured as $method) {
            $value = strtolower(trim((string) $method));
            if ($value === '' || ! in_array($value, self::OTP_SUPPORTED_METHODS, true)) {
                continue;
            }
            $normalized[$value] = $value;
        }

        return array_values($normalized);
    }

    public function supportedOtpMethods(): array
    {
        return self::OTP_SUPPORTED_METHODS;
    }

    public function isOtpMethodAllowed(string $method): bool
    {
        return in_array(strtolower(trim($method)), $this->allowedOtpMethods(), true);
    }

    public function hasAvailableOtpMethodForUser(mixed $user): bool
    {
        foreach ($this->allowedOtpMethods() as $method) {
            if ($this->isOtpMethodAvailableForUser($user, $method)) {
                return true;
            }
        }

        return false;
    }

    public function isOtpMethodAvailableForUser(mixed $user, string $method): bool
    {
        $method = strtolower(trim($method));

        if (! $this->isOtpMethodAllowed($method)) {
            return false;
        }

        return match ($method) {
            'email' => $this->otpDispatchMethodExists('sendEmail') && $this->userHasAnyValue($user, ['email']),
            'sms' => $this->otpDispatchMethodExists('sendSms') && $this->userHasAnyValue($user, ['phone', 'phone_number', 'mobile', 'mobile_phone']),
            'app' => $this->otpDispatchMethodExists('sendApp') && $this->userHasAnyValue($user, ['google2fa_secret', 'otp_app_secret', 'two_factor_secret']),
            default => false,
        };
    }

    public function setUserOtpEnabled(mixed $user, bool $enabled): bool
    {
        if (! $enabled && ! $this->canUserDisableOtp()) {
            return false;
        }

        if (! is_object($user) || ! method_exists($user, 'setSetting')) {
            return false;
        }

        $connection = svarium_tenancy_database_mode() ? central_connection() : null;

        try {
            $user->setSetting('otp_status', $enabled, $connection);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    protected function findUserByEmail(string $email): mixed
    {
        $userModel = get_model('user');

        return $userModel::query()->where('email', $email)->first();
    }

    protected function hasRoleInTenant(mixed $user): bool
    {
        $tenantId = tenant()?->id;
        $modelHasRole = get_model('model_has_role');
        $modelType = svarium_model_type($user);

        $query = $modelHasRole::query()
            ->where('model_id', $user->id)
            ->where('model_type', $modelType)
            ->where('status', 1);

        if ($this->hasTenantBypassRole((clone $query), $user, $tenantId, $modelHasRole)) {
            return true;
        }

        if (! svarium_tenancy_column_mode()) {
            return $query->exists();
        }

        if ($tenantId === null || $tenantId === '') {
            return false;
        }

        if (! $this->roleTableHasTenantColumn($modelHasRole)) {
            return $query->exists()
                && $this->userBelongsToTenant($user, $tenantId);
        }

        $tenantRoleExists = (clone $query)
            ->where('tenant_id', $tenantId)
            ->exists();

        if ($tenantRoleExists) {
            return true;
        }

        $globalRoleExists = (clone $query)
            ->where(function ($builder): void {
                $builder->whereNull('tenant_id')->orWhere('tenant_id', '');
            })
            ->exists();

        if (! $globalRoleExists) {
            return false;
        }

        return $this->userBelongsToTenant($user, $tenantId);
    }

    protected function hasTenantBypassRole(
        mixed $query,
        mixed $user,
        mixed $tenantId,
        mixed $modelHasRole
    ): bool
    {
        $keys = config('upsoftware.auth.tenant_bypass_role_keys', ['superadmin']);
        if (is_string($keys)) {
            $keys = explode(',', $keys);
        }

        if (! is_array($keys)) {
            return false;
        }

        $keys = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            $keys
        ))));

        if ($keys === []) {
            return false;
        }

        $scope = $this->tenantBypassScope();

        if ($scope === 'tenant' && svarium_tenancy_column_mode() && ($tenantId === null || $tenantId === '')) {
            return false;
        }

        $roleModel = get_model('role');
        if (! is_string($roleModel) || ! class_exists($roleModel)) {
            return false;
        }

        try {
            $model = new $roleModel();
            $table = (string) $model->getTable();
            $connection = $model->getConnectionName();

            if ($table === '') {
                return false;
            }

            $hasRoleKey = is_string($connection) && $connection !== ''
                ? Schema::connection($connection)->hasColumn($table, 'role_key')
                : Schema::hasColumn($table, 'role_key');

            if (! $hasRoleKey) {
                return false;
            }

            $bypassQuery = $query
                ->whereHas('role', function ($builder) use ($keys): void {
                    $builder->whereIn('role_key', $keys);
                });

            if (! svarium_tenancy_column_mode() || $scope === 'all_tenants') {
                return $bypassQuery->exists();
            }

            if ($this->roleTableHasTenantColumn($modelHasRole)) {
                return $bypassQuery
                    ->where(function ($builder) use ($tenantId): void {
                        $builder->where('tenant_id', $tenantId)
                            ->orWhereNull('tenant_id')
                            ->orWhere('tenant_id', '');
                    })
                    ->exists();
            }

            return $bypassQuery->exists()
                && $this->userBelongsToTenant($user, $tenantId);
        } catch (Throwable) {
            return false;
        }
    }

    protected function tenantBypassScope(): string
    {
        $scope = strtolower(trim((string) config('upsoftware.auth.tenant_bypass_scope', 'all_tenants')));

        return in_array($scope, ['all_tenants', 'tenant'], true)
            ? $scope
            : 'all_tenants';
    }

    protected function roleTableHasTenantColumn(mixed $modelHasRole): bool
    {
        if (! is_string($modelHasRole) || ! class_exists($modelHasRole)) {
            return false;
        }

        try {
            $model = new $modelHasRole();
            $table = (string) $model->getTable();
            $connection = $model->getConnectionName();

            if ($table === '') {
                return false;
            }

            return is_string($connection) && $connection !== ''
                ? Schema::connection($connection)->hasColumn($table, 'tenant_id')
                : Schema::hasColumn($table, 'tenant_id');
        } catch (Throwable) {
            return false;
        }
    }

    protected function userBelongsToTenant(mixed $user, mixed $tenantId): bool
    {
        $modelHasTenant = get_model('model_has_tenant');

        if (! is_string($modelHasTenant) || ! class_exists($modelHasTenant)) {
            return false;
        }

        $modelType = svarium_model_type($user);

        try {
            return $modelHasTenant::query()
                ->where('model_id', $user->id)
                ->where('model_type', $modelType)
                ->where('tenant_id', $tenantId)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    protected function shouldRemember(Request $request): bool
    {
        return $request->has('remember')
            && ($request->remember === true || $request->remember === 'true');
    }

    protected function getBrowserCookieName(): string
    {
        return Str::of((string) env('APP_NAME'))->slug('_').'_browser_id';
    }

    protected function isRememberedBrowser(Request $request, mixed $user): bool
    {
        $cookieName = $this->getBrowserCookieName();
        $browserId = $request->cookie($cookieName);

        if (! is_string($browserId) || $browserId === '') {
            return false;
        }

        $savedBrowsers = $user->getSetting('remembered_browsers', [], central_connection());

        return is_array($savedBrowsers) && isset($savedBrowsers[$browserId]);
    }

    public function loginUser(Request $request, mixed $user, ?bool $remember = null): string
    {
        if ($remember === null) {
            $remember = $this->shouldRemember($request);
        }

        Auth::login($user);

        $savedBrowser = [];
        $browserId = null;
        $cookieName = $this->getBrowserCookieName();

        try {
            $device = DeviceTracker::detectFindAndUpdate();

            if ($device) {
                DeviceTracker::flagCurrentAsVerified();

                if ($device->wasRecentlyCreated) {
                    $user->notify(new LoginFromNewDeviceNotify(device()));
                }

                if ($remember) {
                    $browserUuid = Str::uuid()->toString();
                    $savedBrowser = $user->getSetting('remembered_browsers', [], central_connection());

                    if (! isset($savedBrowser[$browserUuid])) {
                        $savedBrowser[$browserUuid] = device();
                        $user->setSetting(['remembered_browsers' => $savedBrowser]);
                    }

                    Cookie::queue(
                        $cookieName,
                        $browserUuid,
                        60 * 24 * 365 * 5,
                        null,
                        null,
                        true,
                        true
                    );
                }
            }

            if ($request->cookie($cookieName)) {
                $browserId = (string) $request->cookie($cookieName);
                if (! isset($savedBrowser[$browserId])) {
                    $browserId = null;
                }
            }
        } catch (Throwable) {
            // Brak DB/setting/device tracker nie powinien przerywać logowania.
        }

        try {
            activity('login')
                ->causedBy($user)
                ->withProperties(array_merge([
                    'tenant_id' => tenant() ? tenant()->id : null,
                    'role_id' => null,
                    'browser_id' => $browserId,
                ], device()))
                ->log('login');
        } catch (Throwable) {
            // Ignorujemy niedostępność DB/logów.
        }

        return $this->resolvePostLoginRedirectUrl($request);
    }

    protected function invalidResult(): array
    {
        return [
            'status' => self::STATUS_INVALID,
            'user' => null,
            'requires_otp' => false,
            'otp_disabled_by_user' => false,
            'otp_url' => null,
            'redirect_url' => null,
        ];
    }

    protected function otpDispatchMethodExists(string $methodName): bool
    {
        $userAuthModel = get_model('user_auth');

        return is_string($userAuthModel)
            && class_exists($userAuthModel)
            && method_exists($userAuthModel, $methodName);
    }

    protected function resolvePostLoginRedirectUrl(Request $request): string
    {
        $panelName = $this->resolvePanelNameFromRequest($request);
        $defaultUrl = panel_href('', $panelName);
        $intendedUrl = redirect()->intended($defaultUrl)->getTargetUrl();

        if ($this->shouldIgnoreIntendedUrl($intendedUrl, $request, $panelName)) {
            return $defaultUrl;
        }

        return $intendedUrl;
    }

    protected function shouldIgnoreIntendedUrl(string $url, Request $request, ?string $panelName = null): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || trim($path, '/') === '') {
            return false;
        }

        $normalizedPath = '/'.trim($path, '/');
        $currentPath = '/'.trim($request->path(), '/');

        if ($normalizedPath === $currentPath) {
            return true;
        }

        $syntheticRequest = Request::create($normalizedPath, 'GET');

        return svarium_is_public_auth_request($syntheticRequest, $panelName);
    }

    protected function resolvePanelNameFromRequest(Request $request): ?string
    {
        $panel = $request->attributes->get('panel');

        return is_string($panel) && trim($panel) !== ''
            ? trim($panel)
            : null;
    }

    protected function userHasAnyValue(mixed $user, array $attributes): bool
    {
        if (! is_object($user)) {
            return false;
        }

        foreach ($attributes as $attribute) {
            if (! is_string($attribute) || $attribute === '') {
                continue;
            }

            $value = null;

            if (isset($user->{$attribute})) {
                $value = $user->{$attribute};
            } elseif (method_exists($user, 'getAttribute')) {
                $value = $user->getAttribute($attribute);
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }
}
