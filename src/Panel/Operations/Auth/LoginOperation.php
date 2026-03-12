<?php

namespace Upsoftware\Svarium\Panel\Operations\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Throwable;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\ComponentResult;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Layouts\AuthLayout;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Services\Auth\AuthLoginService;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\Button;
use Upsoftware\Svarium\UI\Components\ButtonGroupSocials;
use Upsoftware\Svarium\UI\Components\Flex;
use Upsoftware\Svarium\UI\Components\Form\Input;
use Upsoftware\Svarium\UI\Components\Link;
use Upsoftware\Svarium\UI\Components\Separator;
use Upsoftware\Svarium\UI\Components\Text;
use Upsoftware\Svarium\UI\Components\Toggle;

class LoginOperation extends Operation
{
    public static string|array $panels = '*';

    public static ?string $layout = AuthLayout::class;

    protected mixed $settingModel;
    protected AuthLoginService $authLoginService;

    protected ?array $socialSettingsCache = null;

    public function __construct()
    {
        $this->settingModel = config('svarium.models.setting', \Upsoftware\Svarium\Models\Setting::class);
        $this->authLoginService = app(AuthLoginService::class);
    }

    public static function uri(): string
    {
        return 'auth/login';
    }

    public static function methods(): array
    {
        return ['GET', 'POST'];
    }

    public function execution(): ExecutionMode
    {
        return ExecutionMode::FORM;
    }

    public function defineTitle(): string
    {
        return __('Welcome back!');
    }

    public function defineSubtitle(): string
    {
        return __('Enter your email address and password');
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8'],
        ];
    }

    public function settingSocials(): array
    {
        if (is_array($this->socialSettingsCache)) {
            return $this->socialSettingsCache;
        }

        $settings = $this->settingModel::getSettingGlobal('login.config', [
            'title' => $this->defineTitle(),
            'subtitle' => $this->defineSubtitle(),
            'socials' => [],
            'minimal' => false,
            'cols' => 2,
            'onlySocialName' => false,
            'orLabel' => 'or',
        ]);

        if (! is_array($settings)) {
            $settings = [];
        }

        $settings['socials'] = is_array($settings['socials'] ?? null)
            ? array_values($settings['socials'])
            : [];

        $settings['title'] = trim((string) ($settings['title'] ?? $this->defineTitle()));
        if (in_array($settings['title'], ['Witaj ponownie!'], true)) {
            $settings['title'] = $this->defineTitle();
        }
        if ($settings['title'] === '') {
            $settings['title'] = $this->defineTitle();
        }

        $settings['subtitle'] = trim((string) ($settings['subtitle'] ?? $this->defineSubtitle()));
        if (in_array($settings['subtitle'], ['Wprowadź adres e-mail oraz hasło', 'Wprowadź swój adres e-mail i hasło'], true)) {
            $settings['subtitle'] = $this->defineSubtitle();
        }
        if ($settings['subtitle'] === '') {
            $settings['subtitle'] = $this->defineSubtitle();
        }

        $settings['minimal'] = (bool) ($settings['minimal'] ?? false);
        $settings['cols'] = max(1, min(3, (int) ($settings['cols'] ?? 2)));
        $settings['onlySocialName'] = (bool) ($settings['onlySocialName'] ?? false);
        $settings['orLabel'] = (string) ($settings['orLabel'] ?? 'or');

        $this->socialSettingsCache = $settings;

        return $this->socialSettingsCache;
    }

    protected function hasSocials(array $setting): bool
    {
        return ! empty($setting['socials']) && is_array($setting['socials']);
    }

    protected function canShowRegisterLink(array $setting): bool
    {
        $registerEnabled = (bool) config('upsoftware.auth.register.enabled', true);
        $showRegisterLink = (bool) ($setting['showRegisterLink'] ?? true);

        return $registerEnabled && $showRegisterLink;
    }

    public function buttonGroupSocials(?array $setting = null, ?string $panel = null): ButtonGroupSocials
    {
        $setting ??= $this->settingSocials();

        return ButtonGroupSocials::make()
            ->socials($setting['socials'])
            ->minimal($setting['minimal'])
            ->cols($setting['cols'])
            ->onlySocialName($setting['onlySocialName'])
            ->redirectRoute(panel_route_name('redirect', $panel));
    }

    public function schema(PanelContext $context): array
    {
        $setting = $this->settingSocials();
        $hasSocials = $this->hasSocials($setting);
        $showRegisterBlock = $this->canShowRegisterLink($setting);
        $panel = $context->panel()->name;
        $registerHref = route_panel('register', [], true, $panel);

        if (isset($setting['registerLink']) && is_string($setting['registerLink']) && trim($setting['registerLink']) !== '') {
            $registerLink = trim($setting['registerLink']);
            if (str_contains($registerLink, '.')) {
                $isAuthRouteReference =
                    str_starts_with($registerLink, 'auth.')
                    || str_starts_with($registerLink, 'panel.')
                    || preg_match('/^[a-z0-9_-]+\.auth\./i', $registerLink) === 1;

                $registerHref = route(
                    $isAuthRouteReference ? panel_route_name($registerLink, $panel) : $registerLink
                );
            } else {
                $registerHref = panel_href($registerLink, $panel);
            }
        }

        return [
            Input::make('email')->label(__('Email address')),
            Input::make('password')->type('password')->label(__('Password')),
            Flex::make()
                ->textAlign('right')
                ->margin('y-2')
                ->justify('between')
                ->items('center')
                ->children([
                    Block::make()->children(Toggle::make('remeber')->label(__('Remember me')))->padding('t-1'),
                    Link::make(__('Password recovery'))->panelHref('auth/reset')->fontWeight('semibold'),
                ]),
            Button::make(__('Log in with your email address'))
                ->type('submit')
                ->width('full'),
            Separator::make(__($setting['orLabel']))->margin('t-2')->if($hasSocials),
            $this->buttonGroupSocials($setting, $panel)->if($hasSocials),
            Block::make()
                ->if($showRegisterBlock)
                ->appearance('border-t border-border py-6 text-center flex justify-center gap-2 mt-2')
                ->children([
                    Text::make(__($setting['registerLabel'] ?? 'If you don’t have an account')),
                    Link::make(__($setting['registerLinkLabel'] ?? 'sign up here'))
                        ->href($registerHref)
                        ->textColor('primary')
                        ->fontWeight('semibold')
                        ->textDecoration('underline'),
                ]),
        ];
    }

    protected function hasSubmit(): bool
    {
        return false;
    }

    protected function render(PanelContext $context, ...$args): ComponentResult
    {
        $result = parent::render($context, ...$args);

        $data = $this->safe(
            fn () => $this->settingSocials(),
            []
        );

        if (! is_array($data)) {
            $data = [];
        }

        foreach ($data as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            $result->prop($key, $value);
        }

        return $result;
    }

    protected function layoutProps(PanelContext $context, ...$args): array
    {
        $settings = $this->settingSocials();

        return [
            'title' => $this->defineTitle(),
            'subtitle' => $this->defineSubtitle(),
        ];
    }

    protected function save(PanelContext $context): RedirectResult
    {
        $request = $context->request();
        $validated = $context->validated();

        try {
            $result = $this->authLoginService->attempt(
                $request,
                (string) $validated['email'],
                (string) $validated['password']
            );

            if (($result['status'] ?? null) === AuthLoginService::STATUS_INVALID) {
                throw ValidationException::withMessages([
                    'email' => [__('Invalid email address or password')],
                ]);
            }

            if (($result['status'] ?? null) === AuthLoginService::STATUS_OTP_REQUIRED && ! empty($result['otp_url'])) {
                return RedirectResult::to((string) $result['otp_url']);
            }

            if (($result['status'] ?? null) === AuthLoginService::STATUS_AUTHENTICATED && ! empty($result['redirect_url'])) {
                return RedirectResult::to((string) $result['redirect_url']);
            }

            throw ValidationException::withMessages([
                'email' => [__('Invalid email address or password')],
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'email' => [__('Invalid email address or password')],
            ]);
        }
    }

    public function loginUser($request, $user): RedirectResponse
    {
        $redirectUrl = $this->authLoginService->loginUser($request, $user);

        return redirect()->to($redirectUrl);
    }

    protected function safe(callable $callback, mixed $fallback = null): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return $fallback;
        }
    }
}
