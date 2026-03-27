<?php

namespace Upsoftware\Svarium\Panel\Operations;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use LaravelLang\Locales\Facades\Locales;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Support\QuotedEnvFile;
use Upsoftware\Svarium\UI\Components\Button;
use Upsoftware\Svarium\UI\Components\Flex;
use Upsoftware\Svarium\UI\Components\Form\Input;
use Upsoftware\Svarium\UI\Components\Form\Select;
use Upsoftware\Svarium\UI\Components\Form\SelectColor;
use Upsoftware\Svarium\UI\Components\Tab;
use Upsoftware\Svarium\UI\Components\TabItem;
use Upsoftware\Svarium\UI\Components\Text;
use Winter\LaravelConfigWriter\ArrayFile;

class SvariumConfigurationOperation extends Operation
{
    public static string|array $panels = '*';

    public static function methods(): array
    {
        return ['GET', 'POST'];
    }

    public static function uri(): string
    {
        return 'svarium/configuration';
    }

    public function execution(): ExecutionMode
    {
        return ExecutionMode::FORM;
    }

    protected function submitLabel(): string
    {
        return __('Save configuration');
    }

    public function title(PanelContext $context): string
    {
        return __('Svarium configuration');
    }

    public function schema(PanelContext $context): array
    {
        $panelPrefix = trim((string) config('upsoftware.panel.prefix', ''), '/');
        $installedLocales = $this->installedLocalesList();
        $languageOptions = $this->availableLocaleOptions();

        return [
            Text::make(__('Svarium configuration'))->headline('h2')->appearance('text-lg font-semibold'),
            Tab::make('configuration_tabs')
                ->prop('defaultValue', 'panels')
                ->children([
                    TabItem::make('Panels')
                        ->prop('value', 'panels')
                        ->content([
                            Input::make('panel_name')
                                ->label('Panel name')
                                ->required()
                                ->value((string) config('upsoftware.panel.name', 'admin')),
                            Input::make('panel_routing')
                                ->label('Panel routing (prefix|no_prefix)')
                                ->required()
                                ->value($panelPrefix === '' ? 'no_prefix' : 'prefix')
                                ->prop('placeholder', 'prefix'),
                            Input::make('panel_prefix')
                                ->label('Panel prefix')
                                ->value($panelPrefix)
                                ->prop('placeholder', 'admin'),
                        ]),
                    TabItem::make('Appearance')
                        ->prop('value', 'appearance')
                        ->content([
                            SelectColor::make('color'),
                        ]),
                    TabItem::make('Languages')
                        ->prop('value', 'languages')
                        ->content([
                            Input::make('installed_locales')
                                ->label('Installed locales')
                                ->value($installedLocales)
                                ->prop('readonly', true),
                            Input::make('app_locale')
                                ->label('App locale')
                                ->required()
                                ->value((string) config('app.locale', 'pl')),
                            Input::make('app_fallback_locale')
                                ->label('Fallback locale')
                                ->required()
                                ->value((string) config('app.fallback_locale', 'en')),
                            Flex::make()
                                ->items('end')
                                ->gap(2)
                                ->content([
                                    Select::make('language_to_add')
                                        ->label('Language to add')
                                        ->placeholder('Select language')
                                        ->options($languageOptions),
                                    Button::make('Add')
                                        ->type('submit')
                                        ->name('_action')
                                        ->value('add_language')
                                        ->variant('outline')
                                        ->size('sm'),
                                ]),
                        ]),
                    TabItem::make('API')
                        ->prop('value', 'api')
                        ->content([
                            Input::make('api_enabled')
                                ->label('API enabled (1|0)')
                                ->required()
                                ->value((bool) config('upsoftware.api.enabled', true) ? '1' : '0'),
                            Input::make('api_prefix')
                                ->label('API prefix')
                                ->required()
                                ->value((string) config('upsoftware.api.prefix', 'api/v1')),
                            Input::make('api_driver')
                                ->label('API driver')
                                ->required()
                                ->value((string) env('SVARIUM_API_DRIVER', 'sanctum')),
                            Input::make('api_guard')
                                ->label('API guard')
                                ->required()
                                ->value((string) config('upsoftware.api.auth.guard', 'sanctum')),
                        ]),
                    TabItem::make('Auth')
                        ->prop('value', 'auth')
                        ->content([
                            Input::make('auth_route_prefix')
                                ->label('Auth route prefix')
                                ->required()
                                ->value((string) config('upsoftware.panel.route_prefix', 'panel.auth')),
                            Input::make('register_enabled')
                                ->label('Register enabled (1|0)')
                                ->required()
                                ->value((bool) config('upsoftware.auth.register.enabled', true) ? '1' : '0'),
                            Input::make('register_auto_login')
                                ->label('Register auto login (1|0)')
                                ->required()
                                ->value((bool) config('upsoftware.auth.register.auto_login', true) ? '1' : '0'),
                            Input::make('register_activation_mode')
                                ->label('Register activation mode (none|email_code|email_link|custom)')
                                ->required()
                                ->value((string) config('upsoftware.auth.register.activation.mode', 'none')),
                            Input::make('otp_enabled')
                                ->label('OTP enabled (1|0)')
                                ->required()
                                ->value((bool) config('upsoftware.auth.otp.enabled', true) ? '1' : '0'),
                            Input::make('otp_methods')
                                ->label('OTP methods (comma separated: email,sms,app)')
                                ->required()
                                ->value(implode(',', (array) config('upsoftware.auth.otp.methods', ['email', 'sms', 'app']))),
                            Input::make('otp_show_all_methods')
                                ->label('OTP show all methods (1|0)')
                                ->required()
                                ->value((bool) config('upsoftware.auth.otp.show_all_methods', false) ? '1' : '0'),
                            Input::make('otp_allow_user_disable')
                                ->label('Allow user disable OTP (1|0)')
                                ->required()
                                ->value((bool) config('upsoftware.auth.otp.allow_user_disable', true) ? '1' : '0'),
                            Input::make('otp_default_enabled')
                                ->label('OTP default enabled for user (1|0)')
                                ->required()
                                ->value((bool) config('upsoftware.auth.otp.default_enabled', true) ? '1' : '0'),
                            Input::make('otp_resend_seconds')
                                ->label('OTP resend seconds')
                                ->required()
                                ->value((string) config('upsoftware.auth.otp.resend_seconds', 60)),
                            Input::make('otp_resend_max_attempts')
                                ->label('OTP resend max attempts')
                                ->required()
                                ->value((string) config('upsoftware.auth.otp.resend_limit.max_attempts', 5)),
                            Input::make('otp_resend_decay_minutes')
                                ->label('OTP resend decay minutes')
                                ->required()
                                ->value((string) config('upsoftware.auth.otp.resend_limit.decay_minutes', 15)),
                            Input::make('otp_token_ttl_minutes')
                                ->label('OTP token TTL minutes')
                                ->required()
                                ->value((string) config('upsoftware.auth.otp.token_ttl_minutes', 10)),
                            Input::make('otp_code_ttl_minutes')
                                ->label('OTP code TTL minutes')
                                ->required()
                                ->value((string) config('upsoftware.auth.otp.code_ttl_minutes', 10)),
                            Input::make('otp_code_length')
                                ->label('OTP code length')
                                ->required()
                                ->value((string) config('upsoftware.auth.otp.code_length', 8)),
                            Input::make('otp_code_pattern')
                                ->label('OTP code pattern (digits|chars|digits_and_chars)')
                                ->required()
                                ->value((string) config('upsoftware.auth.otp.code_pattern', 'digits')),
                            Input::make('otp_invalidate_previous_codes')
                                ->label('OTP invalidate previous active codes (1|0)')
                                ->required()
                                ->value((bool) config('upsoftware.auth.otp.invalidate_previous_codes', true) ? '1' : '0'),
                            Input::make('otp_max_failed_attempts')
                                ->label('OTP max failed attempts')
                                ->required()
                                ->value((string) config('upsoftware.auth.otp.verification.max_failed_attempts', 5)),
                            Input::make('otp_lock_minutes')
                                ->label('OTP lock minutes after failed attempts')
                                ->required()
                                ->value((string) config('upsoftware.auth.otp.verification.lock_minutes', 15)),
                        ]),
                    TabItem::make('Tenant')
                        ->prop('value', 'tenant')
                        ->content([
                            Input::make('tenancy_enabled')
                                ->label('Tenancy enabled (1|0)')
                                ->required()
                                ->value((bool) config('upsoftware.tenancy.enabled', false) ? '1' : '0'),
                            Input::make('tenancy_mode')
                                ->label('Tenancy mode (column|database)')
                                ->required()
                                ->value((string) config('upsoftware.tenancy.mode', 'column')),
                            Input::make('tenancy_domains_enabled')
                                ->label('Tenant domains enabled (1|0)')
                                ->required()
                                ->value((bool) config('upsoftware.tenancy.domains.enabled', true) ? '1' : '0'),
                            Input::make('tenancy_central_domains')
                                ->label('Central domains (comma separated)')
                                ->value(implode(',', (array) config('upsoftware.tenancy.domains.central_domains', []))),
                            Input::make('tenancy_owner_enabled')
                                ->label('Tenant owner binding enabled (1|0)')
                                ->required()
                                ->value((bool) config('upsoftware.tenancy.owner.enabled', false) ? '1' : '0'),
                            Input::make('tenancy_owner_type_column')
                                ->label('Tenant owner type column')
                                ->required()
                                ->value((string) config('upsoftware.tenancy.owner.type_column', 'owner_type')),
                            Input::make('tenancy_owner_id_column')
                                ->label('Tenant owner id column')
                                ->required()
                                ->value((string) config('upsoftware.tenancy.owner.id_column', 'owner_id')),
                            Input::make('tenancy_owner_map')
                                ->label('Tenant owner map (alias=Model, comma separated)')
                                ->value($this->stringifyOwnerMap((array) config('upsoftware.tenancy.owner.map', []))),
                            Input::make('tenancy_profile_enabled')
                                ->label('Tenant profile enabled (1|0)')
                                ->required()
                                ->value((bool) config('upsoftware.tenancy.profile.enabled', true) ? '1' : '0'),
                            Input::make('tenancy_profile_table')
                                ->label('Tenant profile table')
                                ->required()
                                ->value((string) config('upsoftware.tenancy.profile.table', 'tenant_profiles')),
                            Input::make('tenancy_profile_foreign_key')
                                ->label('Tenant profile foreign key')
                                ->required()
                                ->value((string) config('upsoftware.tenancy.profile.foreign_key', 'tenant_id')),
                            Input::make('tenancy_profile_model')
                                ->label('Tenant profile model (FQCN)')
                                ->required()
                                ->value((string) config('upsoftware.tenancy.profile.model', '\Upsoftware\Svarium\Models\TenantProfile')),
                            Input::make('install_tenant_connections')
                                ->label('Install tenant DB connections now (1|0)')
                                ->required()
                                ->value('0'),
                            Input::make('run_migrate')
                                ->label('Run migrate after save (1|0)')
                                ->required()
                                ->value('0'),
                            Input::make('run_native_install')
                                ->label('Run native:install after save (1|0)')
                                ->required()
                                ->value('0'),
                            Input::make('run_optimize_clear')
                                ->label('Run optimize:clear after save (1|0)')
                                ->required()
                                ->value('1'),
                        ]),
                ]),
        ];
    }

    public function rules(): array
    {
        return [
            'panel_name' => ['required', 'string', 'max:120'],
            'panel_routing' => ['required', 'in:prefix,no_prefix'],
            'panel_prefix' => ['nullable', 'string', 'max:120'],
            'auth_route_prefix' => ['required', 'string', 'max:200'],
            'app_locale' => ['required', 'string', 'max:50'],
            'app_fallback_locale' => ['required', 'string', 'max:50'],
            'language_to_add' => ['nullable', 'string', 'max:20'],
            'api_enabled' => ['required'],
            'api_prefix' => ['required', 'string', 'max:120'],
            'api_driver' => ['required', 'string', 'max:120'],
            'api_guard' => ['required', 'string', 'max:120'],
            'register_enabled' => ['required'],
            'register_auto_login' => ['required'],
            'register_activation_mode' => ['required', 'in:none,email_code,email_link,custom'],
            'otp_enabled' => ['required'],
            'otp_methods' => ['required', 'string'],
            'otp_show_all_methods' => ['required'],
            'otp_allow_user_disable' => ['required'],
            'otp_default_enabled' => ['required'],
            'otp_resend_seconds' => ['required', 'integer', 'min:0'],
            'otp_resend_max_attempts' => ['required', 'integer', 'min:0'],
            'otp_resend_decay_minutes' => ['required', 'integer', 'min:0'],
            'otp_token_ttl_minutes' => ['required', 'integer', 'min:1'],
            'otp_code_ttl_minutes' => ['required', 'integer', 'min:1'],
            'otp_code_length' => ['required', 'integer', 'min:1', 'max:64'],
            'otp_code_pattern' => ['required', 'in:digits,chars,digits_and_chars'],
            'otp_invalidate_previous_codes' => ['required'],
            'otp_max_failed_attempts' => ['required', 'integer', 'min:0'],
            'otp_lock_minutes' => ['required', 'integer', 'min:0'],
            'tenancy_enabled' => ['required'],
            'tenancy_mode' => ['required', 'in:column,database'],
            'tenancy_domains_enabled' => ['required'],
            'tenancy_central_domains' => ['nullable', 'string'],
            'tenancy_owner_enabled' => ['required'],
            'tenancy_owner_type_column' => ['required', 'string', 'max:120'],
            'tenancy_owner_id_column' => ['required', 'string', 'max:120'],
            'tenancy_owner_map' => ['nullable', 'string'],
            'tenancy_profile_enabled' => ['required'],
            'tenancy_profile_table' => ['required', 'string', 'max:120'],
            'tenancy_profile_foreign_key' => ['required', 'string', 'max:120'],
            'tenancy_profile_model' => ['required', 'string', 'max:255'],
            'install_tenant_connections' => ['required'],
            'run_migrate' => ['required'],
            'run_native_install' => ['required'],
            'run_optimize_clear' => ['required'],
        ];
    }

    protected function save(PanelContext $context): RedirectResult
    {
        $data = $context->validated();
        $action = (string) $context->input->get('_action', '');

        $panelName = trim((string) ($data['panel_name'] ?? 'admin'));
        $routing = trim((string) ($data['panel_routing'] ?? 'prefix'));
        $panelPrefix = $routing === 'no_prefix' ? '' : trim((string) ($data['panel_prefix'] ?? ''), '/');
        if ($routing === 'prefix' && $panelPrefix === '') {
            $panelPrefix = $panelName;
        }

        $authRoutePrefix = trim((string) ($data['auth_route_prefix'] ?? 'panel.auth'));
        if ($authRoutePrefix === '') {
            $authRoutePrefix = 'panel.auth';
        }

        $appLocale = trim((string) ($data['app_locale'] ?? config('app.locale', 'pl')));
        if ($appLocale === '') {
            $appLocale = (string) config('app.locale', 'pl');
        }

        $appFallbackLocale = trim((string) ($data['app_fallback_locale'] ?? config('app.fallback_locale', 'en')));
        if ($appFallbackLocale === '') {
            $appFallbackLocale = (string) config('app.fallback_locale', 'en');
        }
        $languageToAdd = trim((string) ($data['language_to_add'] ?? ''));

        $apiEnabled = $this->toBool($data['api_enabled'] ?? true);
        $apiPrefix = trim((string) ($data['api_prefix'] ?? 'api/v1'), '/');
        if ($apiPrefix === '') {
            $apiPrefix = 'api/v1';
        }

        $apiDriver = trim((string) ($data['api_driver'] ?? 'sanctum'));
        if ($apiDriver === '') {
            $apiDriver = 'sanctum';
        }

        $apiGuard = trim((string) ($data['api_guard'] ?? 'sanctum'));
        if ($apiGuard === '') {
            $apiGuard = 'sanctum';
        }

        $registerEnabled = $this->toBool($data['register_enabled'] ?? true);
        $registerAutoLogin = $this->toBool($data['register_auto_login'] ?? true);
        $registerActivationMode = trim((string) ($data['register_activation_mode'] ?? 'none'));
        if ($registerActivationMode === '') {
            $registerActivationMode = 'none';
        }
        $otpEnabled = $this->toBool($data['otp_enabled'] ?? true);
        $otpMethods = $this->parseOtpMethods((string) ($data['otp_methods'] ?? 'email,sms,app'));
        $otpShowAllMethods = $this->toBool($data['otp_show_all_methods'] ?? false);
        $otpAllowUserDisable = $this->toBool($data['otp_allow_user_disable'] ?? true);
        $otpDefaultEnabled = $this->toBool($data['otp_default_enabled'] ?? true);
        $otpResendSeconds = max(0, (int) ($data['otp_resend_seconds'] ?? config('upsoftware.auth.otp.resend_seconds', 60)));
        $otpResendMaxAttempts = max(0, (int) ($data['otp_resend_max_attempts'] ?? config('upsoftware.auth.otp.resend_limit.max_attempts', 5)));
        $otpResendDecayMinutes = max(0, (int) ($data['otp_resend_decay_minutes'] ?? config('upsoftware.auth.otp.resend_limit.decay_minutes', 15)));
        $otpTokenTtlMinutes = max(1, (int) ($data['otp_token_ttl_minutes'] ?? config('upsoftware.auth.otp.token_ttl_minutes', 10)));
        $otpCodeTtlMinutes = max(1, (int) ($data['otp_code_ttl_minutes'] ?? config('upsoftware.auth.otp.code_ttl_minutes', 10)));
        $otpCodeLength = max(1, min(64, (int) ($data['otp_code_length'] ?? config('upsoftware.auth.otp.code_length', 8))));
        $otpCodePattern = trim((string) ($data['otp_code_pattern'] ?? config('upsoftware.auth.otp.code_pattern', 'digits')));
        if (! in_array($otpCodePattern, ['digits', 'chars', 'digits_and_chars'], true)) {
            $otpCodePattern = 'digits';
        }
        $otpInvalidatePreviousCodes = $this->toBool($data['otp_invalidate_previous_codes'] ?? config('upsoftware.auth.otp.invalidate_previous_codes', true));
        $otpMaxFailedAttempts = max(0, (int) ($data['otp_max_failed_attempts'] ?? config('upsoftware.auth.otp.verification.max_failed_attempts', 5)));
        $otpLockMinutes = max(0, (int) ($data['otp_lock_minutes'] ?? config('upsoftware.auth.otp.verification.lock_minutes', 15)));

        $tenancyEnabled = $this->toBool($data['tenancy_enabled'] ?? false);
        $tenancyMode = trim((string) ($data['tenancy_mode'] ?? 'column'));
        if (! in_array($tenancyMode, ['column', 'database'], true)) {
            $tenancyMode = 'column';
        }
        $tenancyDomainsEnabled = $this->toBool($data['tenancy_domains_enabled'] ?? true);

        $centralDomains = $this->parseCsv((string) ($data['tenancy_central_domains'] ?? ''));
        $tenancyOwnerEnabled = $this->toBool($data['tenancy_owner_enabled'] ?? false);
        $tenancyOwnerTypeColumn = trim((string) ($data['tenancy_owner_type_column'] ?? 'owner_type'));
        if ($tenancyOwnerTypeColumn === '') {
            $tenancyOwnerTypeColumn = 'owner_type';
        }
        $tenancyOwnerIdColumn = trim((string) ($data['tenancy_owner_id_column'] ?? 'owner_id'));
        if ($tenancyOwnerIdColumn === '') {
            $tenancyOwnerIdColumn = 'owner_id';
        }
        $tenancyOwnerMap = $this->parseOwnerMap((string) ($data['tenancy_owner_map'] ?? ''));
        $tenancyProfileEnabled = $this->toBool($data['tenancy_profile_enabled'] ?? true);
        $tenancyProfileTable = trim((string) ($data['tenancy_profile_table'] ?? 'tenant_profiles'));
        if ($tenancyProfileTable === '') {
            $tenancyProfileTable = 'tenant_profiles';
        }
        $tenancyProfileForeignKey = trim((string) ($data['tenancy_profile_foreign_key'] ?? 'tenant_id'));
        if ($tenancyProfileForeignKey === '') {
            $tenancyProfileForeignKey = 'tenant_id';
        }
        $tenancyProfileModel = trim((string) ($data['tenancy_profile_model'] ?? '\Upsoftware\Svarium\Models\TenantProfile'));
        if ($tenancyProfileModel === '') {
            $tenancyProfileModel = '\Upsoftware\Svarium\Models\TenantProfile';
        }

        if ($action === 'add_language') {
            if ($languageToAdd === '') {
                return RedirectResult::to($this->url($context))
                    ->warning(__('Select language before adding.'));
            }

            $this->installLocaleCodes([$languageToAdd]);

            return RedirectResult::to($this->url($context))
                ->success(__('Language has been added.'));
        }

        $this->writeConfig(
            $panelName,
            $panelPrefix,
            $authRoutePrefix,
            $apiEnabled,
            $apiPrefix,
            $apiGuard,
            $registerEnabled,
            $registerAutoLogin,
            $registerActivationMode,
            $otpEnabled,
            $otpMethods,
            $otpShowAllMethods,
            $otpAllowUserDisable,
            $otpDefaultEnabled,
            $otpResendSeconds,
            $otpResendMaxAttempts,
            $otpResendDecayMinutes,
            $otpTokenTtlMinutes,
            $otpCodeTtlMinutes,
            $otpCodeLength,
            $otpCodePattern,
            $otpInvalidatePreviousCodes,
            $otpMaxFailedAttempts,
            $otpLockMinutes,
            $tenancyEnabled,
            $tenancyMode,
            $tenancyDomainsEnabled,
            $centralDomains,
            $tenancyOwnerEnabled,
            $tenancyOwnerTypeColumn,
            $tenancyOwnerIdColumn,
            $tenancyOwnerMap,
            $tenancyProfileEnabled,
            $tenancyProfileTable,
            $tenancyProfileForeignKey,
            $tenancyProfileModel
        );
        $this->writeEnv($panelName, $apiDriver, $tenancyEnabled, $tenancyMode, $tenancyDomainsEnabled, $appLocale, $appFallbackLocale);
        $this->ensurePanelsFile($panelName, $panelPrefix);
        $this->installLocales($appLocale, $appFallbackLocale, $languageToAdd !== '' ? [$languageToAdd] : []);

        if ($tenancyEnabled && $tenancyMode === 'database' && $this->toBool($data['install_tenant_connections'] ?? false)) {
            Artisan::call('svarium:tenant.install', ['--no-interaction' => true]);
        }

        if ($this->toBool($data['run_migrate'] ?? false)) {
            Artisan::call('migrate', ['--force' => true]);
        }

        if ($this->toBool($data['run_native_install'] ?? false)) {
            if ($this->artisanCommandExists('native:install')) {
                Artisan::call('native:install');
            }
        }

        if ($this->toBool($data['run_optimize_clear'] ?? true)) {
            Artisan::call('optimize:clear');
        }

        return RedirectResult::to($this->url($context))
            ->success(__('Svarium configuration has been saved.'));
    }

    protected function url(PanelContext $context): string
    {
        $prefix = trim($context->panel()->prefixName(), '/');

        return $prefix !== ''
            ? "{$prefix}/svarium/configuration"
            : 'svarium/configuration';
    }

    protected function artisanCommandExists(string $command): bool
    {
        try {
            return array_key_exists($command, Artisan::all());
        } catch (\Throwable) {
            return false;
        }
    }

    protected function writeConfig(
        string $panelName,
        string $panelPrefix,
        string $authRoutePrefix,
        bool $apiEnabled,
        string $apiPrefix,
        string $apiGuard,
        bool $registerEnabled,
        bool $registerAutoLogin,
        string $registerActivationMode,
        bool $otpEnabled,
        array $otpMethods,
        bool $otpShowAllMethods,
        bool $otpAllowUserDisable,
        bool $otpDefaultEnabled,
        int $otpResendSeconds,
        int $otpResendMaxAttempts,
        int $otpResendDecayMinutes,
        int $otpTokenTtlMinutes,
        int $otpCodeTtlMinutes,
        int $otpCodeLength,
        string $otpCodePattern,
        bool $otpInvalidatePreviousCodes,
        int $otpMaxFailedAttempts,
        int $otpLockMinutes,
        bool $tenancyEnabled,
        string $tenancyMode,
        bool $tenancyDomainsEnabled,
        array $centralDomains,
        bool $tenancyOwnerEnabled,
        string $tenancyOwnerTypeColumn,
        string $tenancyOwnerIdColumn,
        array $tenancyOwnerMap,
        bool $tenancyProfileEnabled,
        string $tenancyProfileTable,
        string $tenancyProfileForeignKey,
        string $tenancyProfileModel
    ): void {
        $config = ArrayFile::open(config_path('upsoftware.php'));

        $config->set('panel.enabled', true);
        $config->set('panel.name', $panelName);
        $config->set('panel.prefix', $panelPrefix);
        $config->set('panel.route_prefix', $authRoutePrefix);

        $config->set('api.enabled', $apiEnabled);
        $config->set('api.prefix', $apiPrefix);
        $config->set('api.auth.guard', $apiGuard);
        $config->set('api.auth.middleware', ["auth:{$apiGuard}"]);

        $config->set('auth.register.enabled', $registerEnabled);
        $config->set('auth.register.auto_login', $registerAutoLogin);
        $config->set('auth.register.activation.mode', $registerActivationMode);
        $config->set('auth.register.login_redirect_route', "{$authRoutePrefix}.login");
        $config->set('auth.otp.enabled', $otpEnabled);
        $config->set('auth.otp.methods', $otpMethods);
        $config->set('auth.otp.show_all_methods', $otpShowAllMethods);
        $config->set('auth.otp.allow_user_disable', $otpAllowUserDisable);
        $config->set('auth.otp.default_enabled', $otpDefaultEnabled);
        $config->set('auth.otp.resend_seconds', max(0, $otpResendSeconds));
        $config->set('auth.otp.resend_limit.max_attempts', max(0, $otpResendMaxAttempts));
        $config->set('auth.otp.resend_limit.decay_minutes', max(0, $otpResendDecayMinutes));
        $config->set('auth.otp.token_ttl_minutes', max(1, $otpTokenTtlMinutes));
        $config->set('auth.otp.code_ttl_minutes', max(1, $otpCodeTtlMinutes));
        $config->set('auth.otp.code_length', max(1, min(64, $otpCodeLength)));
        $config->set('auth.otp.code_pattern', in_array($otpCodePattern, ['digits', 'chars', 'digits_and_chars'], true) ? $otpCodePattern : 'digits');
        $config->set('auth.otp.invalidate_previous_codes', $otpInvalidatePreviousCodes);
        $config->set('auth.otp.verification.max_failed_attempts', max(0, $otpMaxFailedAttempts));
        $config->set('auth.otp.verification.lock_minutes', max(0, $otpLockMinutes));

        $config->set('tenancy.enabled', $tenancyEnabled);
        $config->set('tenancy.mode', $tenancyMode);
        $config->set('tenancy.domains.enabled', $tenancyDomainsEnabled);
        $config->set('tenancy.owner.enabled', $tenancyOwnerEnabled);
        $config->set('tenancy.owner.type_column', $tenancyOwnerTypeColumn);
        $config->set('tenancy.owner.id_column', $tenancyOwnerIdColumn);
        $config->set('tenancy.owner.map', $tenancyOwnerMap);
        $config->set('tenancy.profile.enabled', $tenancyProfileEnabled);
        $config->set('tenancy.profile.table', $tenancyProfileTable);
        $config->set('tenancy.profile.foreign_key', $tenancyProfileForeignKey);
        $config->set('tenancy.profile.model', $tenancyProfileModel);
        $config->set('tenancy.paths.tenant_migrations', app_path('Svarium/Tenancy/Migrations'));
        $config->set('tenancy.paths.migrations', app_path('Svarium/Tenancy/Migrations'));
        $config->set('tenancy.paths.tenant_seeders', app_path('Svarium/Tenancy/Seeders'));
        $config->set('tenancy.paths.seeders', app_path('Svarium/Tenancy/Seeders'));
        $config->set('tenancy.seeders.namespace', 'App\\Svarium\\Tenancy\\Seeders');
        $config->set('tenancy.domains.central_domains', $centralDomains);
        $config->set('tenancy.column.model_maps.domains.table', 'model_has_domains');
        $config->set('tenancy.column.model_maps.domains.domain_key', 'domain_id');

        $config->write();

        if (! File::isDirectory(app_path('Svarium/Tenancy/Migrations'))) {
            File::makeDirectory(app_path('Svarium/Tenancy/Migrations'), 0755, true, true);
        }

        if (! File::isDirectory(app_path('Svarium/Tenancy/Seeders'))) {
            File::makeDirectory(app_path('Svarium/Tenancy/Seeders'), 0755, true, true);
        }
    }

    protected function writeEnv(
        string $panelName,
        string $apiDriver,
        bool $tenancyEnabled,
        string $tenancyMode,
        bool $tenancyDomainsEnabled,
        string $appLocale,
        string $appFallbackLocale
    ): void {
        $env = QuotedEnvFile::open(base_path('.env'));
        $env->set('SVARIUM_PANEL_NAME', $panelName);
        $env->set('SVARIUM_API_DRIVER', $apiDriver);
        $env->set('SVARIUM_TENANCY_ENABLED', $tenancyEnabled ? 'true' : 'false');
        $env->set('SVARIUM_TENANCY_MODE', $tenancyMode);
        $env->set('SVARIUM_TENANCY_DOMAINS_ENABLED', $tenancyDomainsEnabled ? 'true' : 'false');
        $env->set('APP_LOCALE', $appLocale);
        $env->set('APP_FALLBACK_LOCALE', $appFallbackLocale);
        $env->write();
    }

    protected function ensurePanelsFile(string $panelName, string $panelPrefix): void
    {
        $panelsPath = base_path('app/Svarium/panels.php');

        if (File::exists($panelsPath)) {
            return;
        }

        $directory = dirname($panelsPath);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true, true);
        }

        $line = $panelPrefix === ''
            ? "    Panel::make('{$panelName}')->noPrefix(),"
            : "    Panel::make('{$panelName}')->prefix('{$panelPrefix}'),";

        $content = <<<PHP
<?php

use Upsoftware\\Svarium\\Panel\\Panel;

return [
{$line}
];
PHP;

        File::put($panelsPath, $content);
    }

    protected function parseCsv(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($item) => trim((string) $item),
            explode(',', $value)
        )));
    }

    protected function parseOwnerMap(string $value): array
    {
        $pairs = array_filter(array_map(
            static fn ($item) => trim((string) $item),
            explode(',', $value)
        ));

        $result = [];

        foreach ($pairs as $pair) {
            $parts = array_map('trim', explode('=', $pair, 2));

            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                continue;
            }

            $result[$parts[0]] = $parts[1];
        }

        return $result;
    }

    protected function parseOtpMethods(string $value): array
    {
        $supported = ['email', 'sms', 'app'];
        $methods = [];

        foreach (explode(',', $value) as $item) {
            $method = strtolower(trim((string) $item));
            if ($method === '' || ! in_array($method, $supported, true)) {
                continue;
            }
            $methods[$method] = $method;
        }

        if ($methods === []) {
            return $supported;
        }

        return array_values($methods);
    }

    protected function stringifyOwnerMap(array $map): string
    {
        $items = [];

        foreach ($map as $alias => $class) {
            $alias = trim((string) $alias);
            $class = trim((string) $class);

            if ($alias === '' || $class === '') {
                continue;
            }

            $items[] = "{$alias}={$class}";
        }

        return implode(',', $items);
    }

    protected function toBool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return $default;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on', 'tak'], true);
    }

    protected function installLocales(string $appLocale, string $appFallbackLocale, array $languagesToAdd): void
    {
        $this->installLocaleCodes(array_merge([$appLocale, $appFallbackLocale], $languagesToAdd));
    }

    protected function installLocaleCodes(array $localeCodes): void
    {
        $locales = array_values(array_unique(array_filter(array_map(
            static fn ($item) => trim((string) $item),
            $localeCodes
        ))));

        foreach ($locales as $locale) {
            Artisan::call('svarium:lang.add', [
                'lang' => [$locale],
                '--no-interaction' => true,
            ]);
        }

        Artisan::call('svarium:lang.sort', ['--no-interaction' => true]);
    }

    protected function installedLocalesList(): string
    {
        $settingModel = config('svarium.models.setting', \Upsoftware\Svarium\Models\Setting::class);
        $locales = (array) $settingModel::getSettingGlobal('locales', []);
        $codes = array_values(array_unique(array_filter(array_map(
            static fn ($code) => trim((string) $code),
            array_keys($locales)
        ))));

        sort($codes);

        return implode(',', $codes);
    }

    protected function availableLocaleOptions(): array
    {
        $settingModel = config('svarium.models.setting', \Upsoftware\Svarium\Models\Setting::class);
        $installedLocales = array_keys((array) $settingModel::getSettingGlobal('locales', []));
        $installedMap = array_fill_keys($installedLocales, true);

        $options = [];

        foreach (Locales::available() as $locale) {
            $code = (string) $locale->code;

            if (isset($installedMap[$code])) {
                continue;
            }

            $options[] = [
                'value' => $code,
                'label' => "{$locale->localized} ({$locale->native})",
            ];
        }

        usort($options, static function (array $left, array $right): int {
            return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return $options;
    }
}
