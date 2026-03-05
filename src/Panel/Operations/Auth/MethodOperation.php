<?php

namespace Upsoftware\Svarium\Panel\Operations\Auth;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Layouts\AuthLayout;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\UI\Components\Badge;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\Button;
use Upsoftware\Svarium\UI\Components\Flex;
use Upsoftware\Svarium\UI\Components\RadioGroup;
use Upsoftware\Svarium\UI\Components\RadioItem;
use Upsoftware\Svarium\UI\Components\Text;

class MethodOperation extends Operation
{
    public static string|array $panels = '*';

    public static ?string $layout = AuthLayout::class;

    protected const ALLOWED_TYPES = ['login', 'register', 'reset'];

    public static function uri(): string
    {
        return 'auth/{type}/method/{userAuth}';
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
        return [
            'method' => ['required', 'string', Rule::in(['app', 'sms', 'email'])],
        ];
    }

    public function schema(PanelContext $context): array
    {
        $userAuth = $this->resolveUserAuth($context);
        $availableMethods = array_values($this->getAvailableMethods($userAuth->user));
        $options = array_map(
            static fn (array $item): array => [
                'value' => (string) ($item['id'] ?? ''),
                'label' => (string) ($item['label'] ?? ''),
                'disabled' => (bool) ($item['disabled'] ?? false),
                'description' => (string) ($item['description'] ?? ''),
            ],
            $availableMethods
        );
        $activeOptions = array_values(array_filter(
            $options,
            static fn (array $item): bool => ($item['disabled'] ?? true) === false
        ));
        $defaultMethod = null;

        if (count($activeOptions) === 1) {
            $defaultMethod = (string) ($activeOptions[0]['value'] ?? '');
            if ($defaultMethod === '') {
                $defaultMethod = null;
            }
        }

        return [
            RadioGroup::make('method')
                ->options($options)
                ->defaultValue($defaultMethod)
                ->template(function (array $option) {
                    return Flex::make()
                        ->appearance('border p-6 rounded-md gap-2')
                        ->children([
                            RadioItem::make()
                                ->value($option['value'])
                                ->disabled((bool) ($option['disabled'] ?? false)),
                            Block::make()
                                ->width('full')
                                ->children([
                                    Flex::make()
                                        ->flex(1)
                                        ->margin('b-2')
                                        ->justify('between')
                                        ->children([
                                            Text::make($option['label'])
                                                ->fontWeight('semibold'),
                                            Badge::make('Niedostępne')
                                                ->if($option['disabled'])
                                                ->variant('destructive'),
                                        ]),
                                    Text::make($option['description']),
                                ]),
                        ]);
                }),
            Text::make(__('Selected verification method is not available.'))
                ->if($options === []),
            Button::make(__('Continue'))
                ->submit()
                ->width('full')
                ->if($options !== []),
        ];
    }

    protected function hasSubmit(): bool
    {
        return false;
    }

    public function defineTitle(): string
    {
        return __('Select verification method');
    }

    public function defineSubtitle(): string
    {
        return __('Select one of the following verification methods to proceed');
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
        $userAuth = $this->resolveUserAuth($context);
        $type = $this->resolveType($context);
        $method = strtolower(trim((string) ($context->validated()['method'] ?? '')));
        $verificationUrl = route('panel.auth.verification', [
            'type' => $type,
            'userAuth' => $userAuth->hash,
        ]);

        $cooldownSeconds = $this->resendCooldownSecondsFromLastCode($userAuth);
        if ($cooldownSeconds > 0) {
            return RedirectResult::to($verificationUrl)
                ->warning(__('Too many resend requests. Try again in :seconds seconds.', ['seconds' => $cooldownSeconds]));
        }

        $limitSeconds = $this->resendRateLimitSeconds($context, $userAuth);
        if ($limitSeconds > 0) {
            return RedirectResult::to($verificationUrl)
                ->warning(__('Too many resend requests. Try again in :seconds seconds.', ['seconds' => $limitSeconds]));
        }

        $availableMethods = array_values(array_map(
            static fn (array $item): string => (string) ($item['id'] ?? ''),
            array_filter(
                $this->getAvailableMethods($userAuth->user),
                static fn (array $item): bool => ($item['disabled'] ?? true) === false
            )
        ));

        if ($method === '' || ! in_array($method, $availableMethods, true)) {
            throw ValidationException::withMessages([
                'method' => [__('svarium::messages.Invalid verification method')],
            ]);
        }

        $methodName = 'send'.ucfirst($method);
        if (method_exists($userAuth, $methodName)) {
            try {
                $userAuth->{$methodName}($type);
            } catch (Throwable) {
                throw ValidationException::withMessages([
                    'method' => [__('Selected verification method is not available.')],
                ]);
            }
        }

        $this->hitResendRateLimit($context, $userAuth);

        return RedirectResult::to(route('panel.auth.verification', [
            'type' => $type,
            'userAuth' => $userAuth->hash,
        ]));
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

        if (! $userAuth || ! $userAuth->user || $this->isUserAuthTokenExpired($userAuth)) {
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

    protected function getAvailableMethods(mixed $user): array
    {
        $authLoginService = app(\Upsoftware\Svarium\Services\Auth\AuthLoginService::class);

        if (! $authLoginService->isOtpGloballyEnabled()) {
            return [];
        }

        $definitions = [
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

    protected function resendSeconds(): int
    {
        $value = (int) config('upsoftware.auth.otp.resend_seconds', 60);

        return max(0, $value);
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

        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return 0;
        }

        return max(1, (int) RateLimiter::availableIn($key));
    }

    protected function hitResendRateLimit(PanelContext $context, mixed $userAuth): void
    {
        $maxAttempts = $this->resendRateLimitMaxAttempts();
        $decayMinutes = $this->resendRateLimitDecayMinutes();

        if ($maxAttempts <= 0 || $decayMinutes <= 0) {
            return;
        }

        RateLimiter::hit(
            $this->resendRateLimitKey($context, $userAuth),
            max(1, $decayMinutes) * 60
        );
    }

    protected function normalizeRateLimitIp(?string $ip): string
    {
        $value = trim((string) $ip);

        if ($value === '') {
            return 'unknown';
        }

        return str_replace([':', '.'], '-', $value);
    }
}
