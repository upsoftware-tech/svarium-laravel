<?php

namespace Upsoftware\Svarium\Panel\Operations\Auth;

use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Layouts\AuthLayout;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Services\Auth\AuthLoginService;
use Upsoftware\Svarium\UI\Components\Button;
use Upsoftware\Svarium\UI\Components\Countdown;
use Upsoftware\Svarium\UI\Components\Flex;
use Upsoftware\Svarium\UI\Components\Pin;
use Upsoftware\Svarium\UI\Components\Text;
use Upsoftware\Svarium\UI\Components\Toggle;

class VerificationOperation extends Operation
{
    public static string|array $panels = '*';

    public static ?string $layout = AuthLayout::class;

    protected const ALLOWED_TYPES = ['login', 'register', 'reset'];

    public static function uri(): string
    {
        return 'auth/{type}/verification/{userAuth}';
    }

    public static function methods(): array
    {
        return ['GET', 'POST'];
    }

    public function execution(): ExecutionMode
    {
        return ExecutionMode::FORM;
    }

    public function rules(): array
    {
        $codeLength = $this->otpCodeLength();

        return [
            'code' => ['required', 'string', "size:{$codeLength}", "regex:{$this->otpCodeRegexRule()}"],
            'type' => ['nullable', Rule::in(self::ALLOWED_TYPES)],
        ];
    }

    public function defineTitle(): string
    {
        return __('Verification code');
    }

    public function defineSubtitle(): string
    {
        return __('Enter the verification code you received');
    }

    public function schema(PanelContext $context): array
    {
        $type = $this->resolveType($context);
        $userAuth = $this->resolveUserAuth($context);

        return [
            Pin::make('code')
                ->label(__('Verification code'))
                ->pattern($this->otpCodePattern())
                ->maxlength($this->otpCodeLength()),
            Countdown::make()
                ->appearance('mb-4 text-sm')
                ->seconds($this->countdownSeconds($context, $userAuth))
                ->template(
                    Flex::make()
                        ->justify('center')
                        ->appearance('text-xs')
                        ->children([
                            Text::make(__('Code did not arrive?'))
                                ->fontWeight('semibold')
                                ->padding('r-2'),
                            Text::make(__('You can generate a new code in {minutes}:{seconds}'))
                                ->textColor('slate-500'),
                        ])
                )
                ->afterText(__('Generate new code'))
                ->afterUrl($this->resendUrl($type, $userAuth)),
            Toggle::make('remember')
                ->if($type === 'login')
                ->label(__('Remember browser'))
                ->hint(__('Subsequent logins on the same browser will not require an additional code')),
            Button::make(__('Continue'))
                ->type('submit')
                ->width('full'),
        ];
    }

    protected function hasSubmit(): bool
    {
        return false;
    }

    protected function layoutProps(PanelContext $context, ...$args): array
    {
        return [
            'title' => $this->defineTitle(),
            'subtitle' => $this->defineSubtitle(),
        ];
    }

    protected function save(PanelContext $context): RedirectResult
    {
        $type = $this->resolveType($context);
        $userAuth = $this->resolveUserAuth($context);
        $validated = $context->validated();
        $code = trim((string) ($validated['code'] ?? ''));
        $lockSeconds = $this->verificationLockSeconds($context, $userAuth);

        if ($lockSeconds > 0) {
            throw ValidationException::withMessages([
                'code' => [__('Too many invalid attempts. Try again in :seconds seconds.', ['seconds' => $lockSeconds])],
            ]);
        }

        if ($code === '' || ! $userAuth->verifyCode($code)) {
            $this->registerFailedVerificationAttempt($context, $userAuth);
            $lockSeconds = $this->verificationLockSeconds($context, $userAuth);

            $message = $lockSeconds > 0
                ? __('Too many invalid attempts. Try again in :seconds seconds.', ['seconds' => $lockSeconds])
                : __('svarium::messages.Invalid verification code');

            throw ValidationException::withMessages([
                'code' => [$message],
            ]);
        }

        $this->clearFailedVerificationAttempts($context, $userAuth);

        if ($type === 'reset') {
            return RedirectResult::to(route('panel.auth.reset.password', [
                'userAuth' => $userAuth->hash,
            ]));
        }

        if ($type === 'register') {
            $this->markEmailAsVerified($userAuth->user);
        }

        $remember = $type === 'login' && $this->toBool($context->request()->input('remember', false));
        $redirectUrl = app(AuthLoginService::class)
            ->loginUser($context->request(), $userAuth->user, $remember);

        return RedirectResult::to($redirectUrl);
    }

    protected function resolveType(PanelContext $context): string
    {
        $type = strtolower(trim((string) ($context->params['type'] ?? '')));

        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            abort(404);
        }

        return $type;
    }

    protected function resolveUserAuth(PanelContext $context): mixed
    {
        $hash = trim((string) ($context->params['userAuth'] ?? ''));
        $userAuth = $hash !== ''
            ? get_model('user_auth')::byHash($hash)
            : null;

        if (
            ! $userAuth
            || ! $userAuth->user
            || $this->isUserAuthTokenExpired($userAuth)
            || ! $this->hasIssuedVerificationCode($userAuth)
        ) {
            abort(404);
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

    protected function hasIssuedVerificationCode(mixed $userAuth): bool
    {
        return $userAuth->code()
            ->where(function ($query) {
                $query->whereNull('is_used')
                    ->orWhere('is_used', false);
            })
            ->exists();
    }

    protected function resendSeconds(): int
    {
        $value = (int) config('upsoftware.auth.otp.resend_seconds', 60);

        return max(0, $value);
    }

    protected function countdownSeconds(PanelContext $context, mixed $userAuth): int
    {
        return max(
            $this->resendCooldownSecondsFromLastCode($userAuth),
            $this->resendRateLimitSeconds($context, $userAuth)
        );
    }

    protected function resendCooldownSecondsFromLastCode(mixed $userAuth): int
    {
        $cooldown = $this->resendSeconds();

        if ($cooldown <= 0) {
            return 0;
        }

        $latestCreatedAt = $userAuth->code()
            ->latest('id')
            ->value('created_at');

        if (! $latestCreatedAt) {
            return 0;
        }

        try {
            $createdAt = Carbon::parse((string) $latestCreatedAt);
        } catch (Throwable) {
            return 0;
        }

        $availableAt = $createdAt->copy()->addSeconds($cooldown);

        if (now()->greaterThanOrEqualTo($availableAt)) {
            return 0;
        }

        return max(1, (int) now()->diffInSeconds($availableAt));
    }

    protected function resendUrl(string $type, mixed $userAuth): string
    {
        return route('panel.auth.verification.resend', [
            'type' => $type,
            'userAuth' => $userAuth->hash,
        ]);
    }

    protected function verificationUrl(string $type, mixed $userAuth): string
    {
        return route('panel.auth.verification', [
            'type' => $type,
            'userAuth' => $userAuth->hash,
        ]);
    }

    protected function methodUrl(string $type, mixed $userAuth): string
    {
        return route('panel.auth.method', [
            'type' => $type,
            'userAuth' => $userAuth->hash,
        ]);
    }

    protected function resendVerificationCode(mixed $userAuth, string $type): bool
    {
        $method = $this->resolveResendMethod($userAuth);

        if ($method === null) {
            return false;
        }

        try {
            return $this->dispatchVerificationCode($userAuth, $type, $method);
        } catch (Throwable) {
            return false;
        }
    }

    protected function resolveResendMethod(mixed $userAuth): ?string
    {
        $lastMethod = strtolower(trim((string) $userAuth->code()->latest('id')->value('method')));

        if ($lastMethod !== '') {
            return $lastMethod;
        }

        $authLoginService = app(AuthLoginService::class);

        foreach ($authLoginService->allowedOtpMethods() as $candidate) {
            if (! $authLoginService->isOtpMethodAvailableForUser($userAuth->user, $candidate)) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    protected function dispatchVerificationCode(mixed $userAuth, string $type, string $method): bool
    {
        $method = strtolower(trim($method));

        if ($method === '') {
            return false;
        }

        $methodName = 'send'.ucfirst($method);

        if (! method_exists($userAuth, $methodName)) {
            return false;
        }

        if ($method === 'email') {
            $userAuth->{$methodName}($type);

            return true;
        }

        $userAuth->{$methodName}();

        return true;
    }

    protected function verificationRateLimitKey(PanelContext $context, mixed $userAuth): string
    {
        return sprintf(
            'svarium:otp:verify:%s:%s',
            (string) ($userAuth->id ?? $userAuth->hash ?? 'unknown'),
            $this->normalizeRateLimitIp($context->request()->ip())
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

    protected function verificationLockSeconds(PanelContext $context, mixed $userAuth): int
    {
        $maxAttempts = $this->verificationMaxFailedAttempts();

        if ($maxAttempts <= 0) {
            return 0;
        }

        $key = $this->verificationRateLimitKey($context, $userAuth);

        if (! $this->rateLimiterTooManyAttempts($key, $maxAttempts)) {
            return 0;
        }

        return max(1, $this->rateLimiterAvailableIn($key));
    }

    protected function registerFailedVerificationAttempt(PanelContext $context, mixed $userAuth): void
    {
        $maxAttempts = $this->verificationMaxFailedAttempts();
        $lockMinutes = $this->verificationLockMinutes();

        if ($maxAttempts <= 0 || $lockMinutes <= 0) {
            return;
        }

        $this->rateLimiterHit(
            $this->verificationRateLimitKey($context, $userAuth),
            max(1, $lockMinutes) * 60
        );
    }

    protected function clearFailedVerificationAttempts(PanelContext $context, mixed $userAuth): void
    {
        $this->rateLimiterClear($this->verificationRateLimitKey($context, $userAuth));
    }

    protected function resendRateLimitKey(PanelContext $context, mixed $userAuth): string
    {
        return sprintf(
            'svarium:otp:resend:%s:%s',
            (string) ($userAuth->id ?? $userAuth->hash ?? 'unknown'),
            $this->normalizeRateLimitIp($context->request()->ip())
        );
    }

    protected function resendRateLimitMaxAttempts(): int
    {
        return max(0, (int) config('upsoftware.auth.otp.resend_limit.max_attempts', 5));
    }

    protected function resendRateLimitDecayMinutes(): int
    {
        return max(0, (int) config('upsoftware.auth.otp.resend_limit.decay_minutes', 15));
    }

    protected function resendRateLimitSeconds(PanelContext $context, mixed $userAuth): int
    {
        $maxAttempts = $this->resendRateLimitMaxAttempts();

        if ($maxAttempts <= 0) {
            return 0;
        }

        $key = $this->resendRateLimitKey($context, $userAuth);

        if (! $this->rateLimiterTooManyAttempts($key, $maxAttempts)) {
            return 0;
        }

        return max(1, $this->rateLimiterAvailableIn($key));
    }

    protected function hitResendRateLimit(PanelContext $context, mixed $userAuth): void
    {
        $maxAttempts = $this->resendRateLimitMaxAttempts();
        $decayMinutes = $this->resendRateLimitDecayMinutes();

        if ($maxAttempts <= 0 || $decayMinutes <= 0) {
            return;
        }

        $this->rateLimiterHit(
            $this->resendRateLimitKey($context, $userAuth),
            max(1, $decayMinutes) * 60
        );
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
        if (! $user || ! method_exists($user, 'forceFill') || ! method_exists($user, 'save')) {
            return;
        }

        try {
            if (property_exists($user, 'email_verified_at') || isset($user->email_verified_at)) {
                $user->forceFill(['email_verified_at' => now()]);
                $user->save();
            }
        } catch (Throwable) {
            // Ignore verification timestamp errors.
        }
    }

    protected function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
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

    protected function otpCodeRegexRule(): string
    {
        return match ($this->otpCodePattern()) {
            'chars' => '/^[a-zA-Z]+$/',
            'digits_and_chars' => '/^[a-zA-Z0-9]+$/',
            default => '/^\d+$/',
        };
    }
}
