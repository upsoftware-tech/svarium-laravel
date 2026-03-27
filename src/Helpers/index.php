<?php

/**
 * Svarium - Commercial Resource Planning System
 *
 * @package    Svarium
 * @author     Upsoftware
 * @copyright  Copyright (c) 2024, Upsoftware
 * @license    Proprietary
 */

use Jenssegers\Agent\Agent;

if (!function_exists('layout')) {
    function layout() {
        return app('layout');
    }
}

function locales()
{
    $languages = setting('locales', []);

    if (is_string($languages)) {
        $decoded = json_decode($languages, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $languages = $decoded;
        }
    }

    if (! is_array($languages)) {
        return [];
    }

    return $languages;
}

if (! function_exists('setting')) {
    function setting(
        string $key,
        mixed $default = null,
        int|string|null $userId = null,
        bool $fallbackToGlobal = true
    ): mixed {
        $model = (string) config('upsoftware.models.setting', '');
        if ($model === '') {
            $model = (string) config('svarium.models.setting', '');
        }
        if ($model === '' && function_exists('get_model')) {
            $model = (string) get_model('setting');
        }
        if ($model === '') {
            $model = \Upsoftware\Svarium\Models\Setting::class;
        }

        if (! class_exists($model) || ! method_exists($model, 'getSettingGlobal')) {
            return $default;
        }

        $value = $model::getSettingGlobal($key, $default, null, $userId, $fallbackToGlobal);

        if (is_string($value)) {
            $trimmed = trim($value);
            $looksLikeJson = $trimmed !== ''
                && (
                    (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}'))
                    || (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'))
                );

            if ($looksLikeJson) {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                }
            }
        }

        if ((bool) config('svarium.debug.setting', false)) {
            \Illuminate\Support\Facades\Log::debug('svarium.setting helper', [
                'key' => $key,
                'model' => $model,
                'user_id' => $userId,
                'fallback_to_global' => $fallbackToGlobal,
                'value_is_default' => $value === $default,
                'value_type' => gettype($value),
                'value_preview' => is_scalar($value)
                    ? (string) $value
                    : (is_array($value) ? 'array('.count($value).')' : get_debug_type($value)),
            ]);
        }

        return $value;
    }
}

function set_title($title) {
    layout()->set_title($title);
}

function get_title() {
    return layout()->get_title();
}

function central_connection() {
    $defaultConnection = (string) config('database.default');
    $connections = (array) config('database.connections', []);
    $isConfigured = static fn (?string $name): bool => is_string($name) && $name !== '' && array_key_exists($name, $connections);

    $forcedConnection = config('svarium.database_connection');
    if (is_string($forcedConnection) && $forcedConnection !== '' && $isConfigured($forcedConnection)) {
        return $forcedConnection;
    }

    // In column tenancy mode shared data should stay on the default connection.
    // Dedicated "central" connection is only required in database tenancy mode.
    if (function_exists('svarium_tenancy_database_mode') && ! svarium_tenancy_database_mode()) {
        return $defaultConnection;
    }

    $candidates = [
        config('upsoftware.tenancy.database.central_connection'),
        config('tenancy.database.central_connection'),
        'central',
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && $isConfigured($candidate)) {
            return $candidate;
        }
    }

    return $defaultConnection;
}

if (! function_exists('debug_connection')) {
    /**
     * Dump database connection configuration and runtime details.
     *
     * @param  string|null  $connection  Dump only selected connection when provided.
     * @param  bool  $terminate  Stop script execution after dump.
     * @return array<string, mixed>
     */
    function debug_connection(?string $connection = null, bool $terminate = true): array
    {
        $defaultConnection = (string) config('database.default', 'mysql');
        $configuredConnections = (array) config('database.connections', []);

        $targetConnections = $connection !== null && $connection !== ''
            ? [$connection]
            : array_keys($configuredConnections);

        $dump = [
            'default_connection' => $defaultConnection,
            'central_connection' => function_exists('central_connection') ? central_connection() : null,
            'tenancy' => [
                'enabled' => function_exists('svarium_tenancy_enabled') ? svarium_tenancy_enabled() : null,
                'mode' => function_exists('svarium_tenancy_mode') ? svarium_tenancy_mode() : null,
            ],
            'connections' => [],
        ];

        foreach ($targetConnections as $name) {
            $name = (string) $name;
            $config = (array) ($configuredConnections[$name] ?? []);

            $runtime = [
                'connected' => false,
                'driver' => null,
                'database' => null,
                'table_prefix' => null,
                'error' => null,
            ];

            try {
                $connectionInstance = \Illuminate\Support\Facades\DB::connection($name);
                $runtime['connected'] = true;
                $runtime['driver'] = $connectionInstance->getDriverName();
                $runtime['database'] = $connectionInstance->getDatabaseName();
                $runtime['table_prefix'] = $connectionInstance->getTablePrefix();
            } catch (\Throwable $e) {
                $runtime['error'] = $e->getMessage();
            }

            $dump['connections'][$name] = [
                'is_default' => $name === $defaultConnection,
                'config' => $config,
                'runtime' => $runtime,
            ];
        }

        echo '<pre>';
        print_r($dump);
        echo '</pre>';

        if ($terminate) {
            die;
        }

        return $dump;
    }
}

if (! function_exists('svarium_tenancy_enabled')) {
    function svarium_tenancy_enabled(): bool
    {
        return (bool) config('upsoftware.tenancy.enabled', config('tenancy.enabled', false));
    }
}

if (! function_exists('svarium_tenancy_mode')) {
    function svarium_tenancy_mode(): string
    {
        $mode = strtolower(trim((string) config('upsoftware.tenancy.mode', 'column')));

        return in_array($mode, ['column', 'database'], true)
            ? $mode
            : 'column';
    }
}

if (! function_exists('svarium_tenancy_column_mode')) {
    function svarium_tenancy_column_mode(): bool
    {
        return svarium_tenancy_enabled() && svarium_tenancy_mode() === 'column';
    }
}

if (! function_exists('svarium_tenancy_database_mode')) {
    function svarium_tenancy_database_mode(): bool
    {
        return svarium_tenancy_enabled() && svarium_tenancy_mode() === 'database';
    }
}

if (! function_exists('tenant')) {
    /**
     * Compatibility helper previously provided by stancl/tenancy.
     */
    function tenant(?string $key = null, mixed $default = null): mixed
    {
        $tenant = app(\Upsoftware\Svarium\Tenancy\TenancyManager::class)->tenant();

        if ($key === null) {
            return $tenant;
        }

        if ($tenant === null) {
            return $default;
        }

        return data_get($tenant, $key, $default);
    }
}

if (! function_exists('tenant_domain')) {
    function tenant_domain(?string $key = null, mixed $default = null): mixed
    {
        $domain = app(\Upsoftware\Svarium\Tenancy\TenancyManager::class)->domain();
        if ($domain === null) {
            $domain = request()?->attributes?->get('svarium.domain');
        }

        if ($key === null) {
            return $domain;
        }

        if ($domain === null) {
            return $default;
        }

        return data_get($domain, $key, $default);
    }
}

if (! function_exists('tenant_owner')) {
    function tenant_owner(?string $key = null, mixed $default = null): mixed
    {
        $tenant = tenant();

        if (! $tenant || ! method_exists($tenant, 'ownerEntity')) {
            return $default;
        }

        $owner = $tenant->ownerEntity();

        if ($key === null) {
            return $owner ?? $default;
        }

        if ($owner === null) {
            return $default;
        }

        return data_get($owner, $key, $default);
    }
}

function device(): array {
    $agent = new Agent();
    $array = [];
    $array['ip'] = request()->ip();
    $array['deviceType'] = $agent->device();
    $array['platform'] = $agent->platform();
    $array['platformVer'] = $agent->version($array['platform']);
    $array['browser'] = $agent->browser();
    $array['browserVer'] = $agent->version($array['browser']);

    return $array;
}

function svarium_path($path = ''): string
{
    return app_path(implode('/', ['Svarium', $path]));
}

function svarium_resources($path = ''): string
{
    return svarium_path(implode('/', ['Resources', $path]));
}

function svarium_modules($path = ''): string
{
    return svarium_path(implode('/', ['Modules', $path]));
}

function svarium_config($path = ''): string
{
    return svarium_path(implode('/', ['Config', $path]));
}

function svarium_plugins($path = ''): string
{
    return svarium_path(implode('/', ['Plugins', $path]));
}

function pluck(string $modelClass, string $value, ?string $key = null): array
{
    if (!class_exists($modelClass)) {
        return [];
    }
    return $modelClass::pluck($value, $key)->toArray();
}

if (! function_exists('optionsModel')) {
    function optionsModel(
        string $modelClass,
        string $value = 'id',
        string $label = 'name'
    ): \Upsoftware\Svarium\Support\ModelOptionsBuilder {
        return new \Upsoftware\Svarium\Support\ModelOptionsBuilder($modelClass, $value, $label);
    }
}

if (! function_exists('options')) {
    function options(
        ?string $modelClass = null,
        string $value = 'id',
        string $label = 'name'
    ): array|\Upsoftware\Svarium\Support\ModelOptionsBuilder {
        if (! is_string($modelClass) || trim($modelClass) === '') {
            return [];
        }

        return optionsModel($modelClass, $value, $label);
    }
}


function get_model(string $model): string {
    $models = config('upsoftware.models', []);

    if (isset($models[$model]) && is_string($models[$model]) && trim($models[$model]) !== '') {
        return $models[$model];
    }

    $fallbacks = [
        'activity' => \Upsoftware\Svarium\Models\Activity::class,
        'device' => \Upsoftware\Svarium\Models\Device::class,
        'device_user' => \Upsoftware\Svarium\Models\DeviceUser::class,
        'domain' => \Upsoftware\Svarium\Models\Domain::class,
        'model_has_domain' => \Upsoftware\Svarium\Models\ModelHasDomain::class,
        'model_has_domains' => \Upsoftware\Svarium\Models\ModelHasDomain::class,
        'model_has_domain_tenants' => \Upsoftware\Svarium\Models\ModelHasDomainTenant::class,
        'model_has_role' => \Upsoftware\Svarium\Models\ModelHasRole::class,
        'model_has_tenant' => \Upsoftware\Svarium\Models\ModelHasTenant::class,
        'model_has_tenants' => \Upsoftware\Svarium\Models\ModelHasTenant::class,
        'navigation' => \Upsoftware\Svarium\Models\Navigation::class,
        'role' => \Upsoftware\Svarium\Models\Role::class,
        'setting' => \Upsoftware\Svarium\Models\Setting::class,
        'subscription_module' => \Upsoftware\Svarium\Models\SubscriptionModule::class,
        'subscription_limit_tier' => \Upsoftware\Svarium\Models\SubscriptionLimitTier::class,
        'system_mailbox' => \Upsoftware\Svarium\Models\SystemMailbox::class,
        'tenant' => \Upsoftware\Svarium\Models\Tenant::class,
        'tenant_domain' => \Upsoftware\Svarium\Models\TenantDomain::class,
        'tenant_profile' => \Upsoftware\Svarium\Models\TenantProfile::class,
        'tenant_subscription' => \Upsoftware\Svarium\Models\TenantSubscription::class,
        'tenant_subscription_item' => \Upsoftware\Svarium\Models\TenantSubscriptionItem::class,
        'user' => \Upsoftware\Svarium\Models\User::class,
        'user_auth' => \Upsoftware\Svarium\Models\UserAuth::class,
        'user_auth_code' => \Upsoftware\Svarium\Models\UserAuthCode::class,
    ];

    if (isset($fallbacks[$model]) && class_exists($fallbacks[$model])) {
        config()->set("upsoftware.models.{$model}", $fallbacks[$model]);

        return $fallbacks[$model];
    }

    throw new \Exception("Model {$model} is not defined in configuration.");
}

if (! function_exists('svarium_labels')) {
    function svarium_labels(): array
    {
        static $labels = null;

        if (is_array($labels)) {
            return $labels;
        }

        $file = svarium_path('labels.php');
        if (! is_file($file)) {
            $labels = [];

            return $labels;
        }

        $resolved = require $file;

        if ($resolved instanceof \Closure) {
            $resolved = $resolved();
        }

        $labels = is_array($resolved) ? $resolved : [];

        return $labels;
    }
}

if (! function_exists('svarium_label')) {
    function svarium_label(string $key, mixed $default = null): string
    {
        $value = data_get(svarium_labels(), $key, $default);

        if (is_array($value)) {
            $locale = (string) app()->getLocale();
            $fallback = (string) config('app.fallback_locale', 'en');

            $value = $value[$locale]
                ?? $value[$fallback]
                ?? reset($value)
                ?? $default;
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}

if (! function_exists('svarium_user_model')) {
    function svarium_user_model(): string
    {
        $candidates = [
            config('upsoftware.models.user'),
            config('upsoftware.tracking.user_model'),
            config('upsoftware.user_model'),
            \Upsoftware\Svarium\Models\User::class,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && class_exists($candidate)) {
                return $candidate;
            }
        }

        return \Upsoftware\Svarium\Models\User::class;
    }
}

if (! function_exists('svarium_model_type')) {
    function svarium_model_type(object|string $model): string
    {
        if (is_object($model)) {
            if (method_exists($model, 'getMorphClass')) {
                return ltrim((string) $model->getMorphClass(), '\\');
            }

            return ltrim($model::class, '\\');
        }

        $class = ltrim($model, '\\');

        if ($class !== '' && class_exists($class)) {
            $instance = new $class;

            if (method_exists($instance, 'getMorphClass')) {
                return ltrim((string) $instance->getMorphClass(), '\\');
            }
        }

        return $class;
    }
}


function show(string|array $dataOrView, ?array $params) {
    if (is_string($dataOrView)) {
        return inertia($dataOrView, $params);
    } else if (is_array($dataOrView)) {
        return $dataOrView;
    }
}

if (! function_exists('module_route')) {
    /**
     * Build panel module/resource path in Svarium.
     *
     * Examples:
     * - module_route('patient') => "admin/patients"
     * - module_route('patient', 'create') => "admin/patients/create"
     * - module_route('patient', 'edit', 10) => "admin/patients/10/edit"
     */
    function module_route(
        string $module,
        ?string $action = null,
        string|int|null $id = null,
        ?string $panel = null
    ): string {
        $panelSegment = '';
        $registry = app(\Upsoftware\Svarium\Panel\PanelRegistry::class);
        $panels = $registry->all();

        $resolvePanelFromPath = static function (array $availablePanels): ?\Upsoftware\Svarium\Panel\Panel {
            $path = trim((string) request()->path(), '/');
            $firstSegment = explode('/', $path)[0] ?? null;

            foreach ($availablePanels as $candidate) {
                if (! $candidate instanceof \Upsoftware\Svarium\Panel\Panel) {
                    continue;
                }

                if ($candidate->prefix !== null && trim($candidate->prefix, '/') === (string) $firstSegment) {
                    return $candidate;
                }
            }

            return null;
        };

        $resolvedPanel = null;

        if ($panel !== null) {
            $panelName = trim((string) $panel);
            if ($panelName !== '') {
                $resolvedPanel = $registry->get($panelName);

                if (! $resolvedPanel instanceof \Upsoftware\Svarium\Panel\Panel) {
                    $panelSegment = trim($panelName, '/');
                }
            }
        }

        if (! $resolvedPanel instanceof \Upsoftware\Svarium\Panel\Panel) {
            $resolvedPanel = $resolvePanelFromPath($panels);
        }

        if (! $resolvedPanel instanceof \Upsoftware\Svarium\Panel\Panel) {
            $currentPanelName = trim((string) request()?->attributes?->get('panel', ''));
            if ($currentPanelName !== '') {
                $currentPanel = $registry->get($currentPanelName);
                if ($currentPanel instanceof \Upsoftware\Svarium\Panel\Panel) {
                    $resolvedPanel = $currentPanel;
                }
            }
        }

        if (! $resolvedPanel instanceof \Upsoftware\Svarium\Panel\Panel) {
            $configuredName = trim((string) config('upsoftware.panel.name', env('SVARIUM_PANEL_NAME', '')));

            if ($configuredName !== '') {
                $configuredPanel = $registry->get($configuredName);
                if ($configuredPanel instanceof \Upsoftware\Svarium\Panel\Panel) {
                    $resolvedPanel = $configuredPanel;
                }
            }
        }

        if (! $resolvedPanel instanceof \Upsoftware\Svarium\Panel\Panel) {
            $noPrefixPanels = array_values(array_filter(
                $panels,
                fn ($candidate) => $candidate instanceof \Upsoftware\Svarium\Panel\Panel && $candidate->prefix === null
            ));

            if (count($noPrefixPanels) === 1) {
                $resolvedPanel = $noPrefixPanels[0];
            }
        }

        if ($resolvedPanel instanceof \Upsoftware\Svarium\Panel\Panel) {
            $panelSegment = trim((string) $resolvedPanel->prefix, '/');
        } elseif ($panelSegment === '') {
            $panelSegment = trim((string) config('upsoftware.panel.prefix', ''), '/');
        }

        $moduleName = trim($module);

        if ($moduleName === '') {
            return $panelSegment;
        }

        if (str_contains($moduleName, '\\')) {
            $moduleName = class_basename($moduleName);
        }

        $moduleName = (string) str($moduleName)
            ->replace('Resource', '')
            ->replace('Module', '')
            ->replace(['/', '-'], '_')
            ->snake();

        $normalizedAction = strtolower(trim((string) $action));

        $moduleKeyCandidates = array_values(array_unique(array_filter([
            trim($moduleName, '. '),
            (string) str($moduleName)->replace('_', '.')->trim('.'),
            (string) str($moduleName)->singular()->toString(),
            (string) str($moduleName)->plural()->toString(),
            (string) str($moduleName)->replace('_', '.')->singular()->trim('.'),
            (string) str($moduleName)->replace('_', '.')->plural()->trim('.'),
        ], static fn (mixed $candidate): bool => is_string($candidate) && trim($candidate) !== '')));

        $actionKeyCandidates = [];
        if ($normalizedAction !== '' && ! in_array($normalizedAction, ['index', 'list'], true)) {
            $normalizedActionKey = (string) str($normalizedAction)
                ->replace('-', '_')
                ->replace('/', '.')
                ->trim('.')
                ->toString();

            if ($normalizedActionKey !== '') {
                $actionKeyCandidates[] = $normalizedActionKey;
            }
        }

        $panelNameForRoute = $resolvedPanel instanceof \Upsoftware\Svarium\Panel\Panel
            ? trim((string) $resolvedPanel->name)
            : trim((string) $panel);

        $routeParams = [];
        if ($id !== null && $id !== '') {
            $routeParams['id'] = $id;
        }

        $candidateRouteNames = [];
        foreach ($moduleKeyCandidates as $moduleKeyCandidate) {
            $moduleKeyCandidate = trim((string) $moduleKeyCandidate, '.');
            if ($moduleKeyCandidate === '') {
                continue;
            }

            $baseNames = [
                "module:{$moduleKeyCandidate}",
            ];

            if ($panelNameForRoute !== '') {
                $baseNames[] = "module:{$panelNameForRoute}.{$moduleKeyCandidate}";
            }

            foreach ($baseNames as $baseName) {
                $candidateRouteNames[] = $baseName;

                foreach ($actionKeyCandidates as $actionKeyCandidate) {
                    $candidateRouteNames[] = "{$baseName}.{$actionKeyCandidate}";
                }
            }
        }

        foreach (array_values(array_unique($candidateRouteNames)) as $candidateRouteName) {
            if (! \Illuminate\Support\Facades\Route::has($candidateRouteName)) {
                continue;
            }

            try {
                return trim(route($candidateRouteName, $routeParams, false), '/');
            } catch (\Throwable) {
                // Ignore unresolved route parameters and fallback to legacy path builder.
            }
        }

        $slug = (string) str($moduleName)->replace('_', '')->plural()->lower();
        $base = trim(implode('/', array_filter([$panelSegment, $slug])), '/');

        if ($normalizedAction === '' || in_array($normalizedAction, ['index', 'list'], true)) {
            return $base;
        }

        if ($normalizedAction === 'create') {
            return "{$base}/create";
        }

        if (in_array($normalizedAction, ['edit', 'duplicate', 'delete'], true)) {
            if ($id === null || $id === '') {
                throw new InvalidArgumentException("Action [{$normalizedAction}] requires record identifier.");
            }

            return "{$base}/{$id}/{$normalizedAction}";
        }

        if ($id !== null && $id !== '') {
            return "{$base}/{$id}/{$normalizedAction}";
        }

        return "{$base}/{$normalizedAction}";
    }
}

if (! function_exists('module_helper')) {
    function module_helper(
        string $module,
        ?string $action = null,
        string|int|null $id = null,
        ?string $panel = null
    ): string {
        return module_route($module, $action, $id, $panel);
    }
}

if (! function_exists('route_module')) {
    /**
     * Alias for module_route().
     *
     * Examples:
     * - route_module('patient') => "admin/patients"
     * - route_module('patient', 'create') => "admin/patients/create"
     * - route_module('patient', 'edit', 10) => "admin/patients/10/edit"
     */
    function route_module(
        string $module,
        ?string $action = null,
        string|int|null $id = null,
        ?string $panel = null
    ): string {
        return module_route($module, $action, $id, $panel);
    }
}

if (! function_exists('module_operation_route_name')) {
    /**
     * Resolve operation route name in "module:{module}.{operation}[.{action}]" format.
     *
     * Examples:
     * - module_operation_route_name('ksef.documents') => "module:ksef.documents"
     * - module_operation_route_name('module:ksef.documents') => "module:ksef.documents"
     */
    function module_operation_route_name(string $name): string
    {
        $value = trim($name);
        if ($value === '') {
            throw new InvalidArgumentException('Operation route name cannot be empty.');
        }

        if (str_starts_with($value, 'module:')) {
            return $value;
        }

        return 'module:'.trim($value, '.');
    }
}

if (! function_exists('module_operation_route')) {
    /**
     * Build URL for operation route aliases.
     *
     * Examples:
     * - module_operation_route('ksef.documents')
     * - module_operation_route('ksef.documents.get')
     */
    function module_operation_route(
        string $name,
        array $parameters = [],
        ?string $panel = null,
        bool $absolute = false
    ): string {
        $resolvedName = module_operation_route_name($name);

        if (\Illuminate\Support\Facades\Route::has($resolvedName)) {
            return route($resolvedName, $parameters, $absolute);
        }

        $raw = trim((string) preg_replace('/^module:/', '', $resolvedName));
        $segments = array_values(array_filter(explode('.', $raw), static fn (string $segment): bool => trim($segment) !== ''));

        if ($segments !== []) {
            $last = strtolower((string) end($segments));
            if (in_array($last, ['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'index', 'store', 'update'], true)) {
                array_pop($segments);
            }
        }

        return panel_href(implode('/', $segments), $panel);
    }
}

if (! function_exists('svarium_all_panels')) {
    /**
     * @return list<\Upsoftware\Svarium\Panel\Panel>
     */
    function svarium_all_panels(): array
    {
        $registry = app(\Upsoftware\Svarium\Panel\PanelRegistry::class);

        return array_values(array_filter(
            $registry->all(),
            fn ($candidate) => $candidate instanceof \Upsoftware\Svarium\Panel\Panel
        ));
    }
}

if (! function_exists('svarium_default_panel_name')) {
    function svarium_default_panel_name(): ?string
    {
        $registry = app(\Upsoftware\Svarium\Panel\PanelRegistry::class);
        $panels = svarium_all_panels();

        $configuredDefault = trim((string) config('upsoftware.panel.auth.default_panel', ''));
        if ($configuredDefault !== '' && $registry->get($configuredDefault) instanceof \Upsoftware\Svarium\Panel\Panel) {
            return $configuredDefault;
        }

        $configuredPanelName = trim((string) config('upsoftware.panel.name', ''));
        if ($configuredPanelName !== '' && $registry->get($configuredPanelName) instanceof \Upsoftware\Svarium\Panel\Panel) {
            return $configuredPanelName;
        }

        foreach ($panels as $panel) {
            if ($panel->prefix === null || trim((string) $panel->prefix, '/') === '') {
                return $panel->name;
            }
        }

        return $panels[0]->name ?? null;
    }
}

if (! function_exists('svarium_resolve_panel')) {
    function svarium_resolve_panel(
        ?string $panel = null,
        ?\Illuminate\Http\Request $request = null
    ): ?\Upsoftware\Svarium\Panel\Panel {
        $registry = app(\Upsoftware\Svarium\Panel\PanelRegistry::class);
        $panels = svarium_all_panels();

        if (is_string($panel)) {
            $panelValue = trim($panel);

            if ($panelValue !== '') {
                $namedPanel = $registry->get($panelValue);
                if ($namedPanel instanceof \Upsoftware\Svarium\Panel\Panel) {
                    return $namedPanel;
                }

                $trimmedPrefix = trim($panelValue, '/');
                foreach ($panels as $candidate) {
                    if (trim((string) $candidate->prefix, '/') === $trimmedPrefix) {
                        return $candidate;
                    }
                }
            }
        }

        $request ??= request();

        if ($request instanceof \Illuminate\Http\Request) {
            $routeName = trim((string) optional($request->route())->getName());

            if ($routeName !== '' && preg_match('/^panel\.([^.]+)\.auth\./', $routeName, $matches) === 1) {
                $routePanel = $registry->get((string) ($matches[1] ?? ''));
                if ($routePanel instanceof \Upsoftware\Svarium\Panel\Panel) {
                    return $routePanel;
                }
            }

            $path = trim((string) $request->path(), '/');
            $firstSegment = explode('/', $path)[0] ?? '';

            foreach ($panels as $candidate) {
                $prefix = trim((string) $candidate->prefix, '/');

                if ($prefix === '') {
                    continue;
                }

                if ($prefix === (string) $firstSegment) {
                    return $candidate;
                }
            }

            $method = strtoupper((string) $request->method());
            if ($method === 'HEAD') {
                $method = 'GET';
            }

            $path = trim((string) $request->path(), '/');

            try {
                $operationRegistry = app(\Upsoftware\Svarium\Panel\OperationRegistry::class);
                foreach ($panels as $candidate) {
                    if ($candidate->prefix !== null) {
                        continue;
                    }

                    if ($operationRegistry->resolve($candidate->name, $method, $path) !== null) {
                        return $candidate;
                    }
                }
            } catch (\Throwable) {
                // Ignore registry resolution errors in helper context.
            }
        }

        $defaultPanelName = svarium_default_panel_name();

        if (is_string($defaultPanelName) && $defaultPanelName !== '') {
            $defaultPanel = $registry->get($defaultPanelName);
            if ($defaultPanel instanceof \Upsoftware\Svarium\Panel\Panel) {
                return $defaultPanel;
            }
        }

        return $panels[0] ?? null;
    }
}

if (! function_exists('svarium_auth_per_panel_enabled')) {
    function svarium_auth_per_panel_enabled(): bool
    {
        return (bool) config('upsoftware.panel.auth.per_panel', true);
    }
}

if (! function_exists('svarium_auth_compat_route_prefixes')) {
    /**
     * @return list<string>
     */
    function svarium_auth_compat_route_prefixes(): array
    {
        $prefixes = [];

        $legacy = trim((string) config('upsoftware.panel.route_prefix', 'panel.auth'), '.');
        if ($legacy !== '') {
            $prefixes[] = $legacy;
        }

        $prefixes[] = 'panel.auth';

        return array_values(array_unique(array_filter($prefixes, static fn ($value) => is_string($value) && $value !== '')));
    }
}

if (! function_exists('svarium_auth_route_prefix')) {
    function svarium_auth_route_prefix(
        ?string $panel = null,
        ?\Illuminate\Http\Request $request = null
    ): string {
        $legacy = trim((string) config('upsoftware.panel.route_prefix', 'panel.auth'), '.');
        if ($legacy === '') {
            $legacy = 'panel.auth';
        }

        if (! svarium_auth_per_panel_enabled()) {
            return $legacy;
        }

        $resolvedPanel = svarium_resolve_panel($panel, $request);

        if ($resolvedPanel instanceof \Upsoftware\Svarium\Panel\Panel) {
            return 'panel.'.trim((string) $resolvedPanel->name).'.auth';
        }

        return $legacy;
    }
}

if (! function_exists('svarium_public_auth_route_patterns')) {
    /**
     * @return list<string>
     */
    function svarium_public_auth_route_patterns(
        ?string $panel = null,
        ?\Illuminate\Http\Request $request = null
    ): array {
        $suffixes = [
            'login',
            'login.*',
            'reset',
            'reset.*',
            'register',
            'register.*',
            'method',
            'method.*',
            'verification',
            'verification.*',
            'redirect',
            'callback',
        ];

        $prefixes = [];

        if (svarium_auth_per_panel_enabled()) {
            if (is_string($panel) && trim($panel) !== '') {
                $prefixes[] = svarium_auth_route_prefix($panel, $request);
            } else {
                foreach (svarium_all_panels() as $candidate) {
                    $prefixes[] = 'panel.'.trim((string) $candidate->name).'.auth';
                }

                foreach (svarium_auth_compat_route_prefixes() as $compatPrefix) {
                    $prefixes[] = $compatPrefix;
                }
            }
        } else {
            $prefixes[] = svarium_auth_route_prefix($panel, $request);
        }

        $patterns = [];

        foreach (array_values(array_unique(array_filter($prefixes))) as $prefix) {
            foreach ($suffixes as $suffix) {
                $patterns[] = trim($prefix, '.').'.'.$suffix;
            }
        }

        $configured = config('upsoftware.panel.public_auth_route_patterns', []);
        if (is_array($configured)) {
            foreach ($configured as $pattern) {
                if (is_string($pattern) && trim($pattern) !== '') {
                    $patterns[] = trim($pattern);
                }
            }
        }

        return array_values(array_unique($patterns));
    }
}

if (! function_exists('svarium_public_auth_path_patterns')) {
    /**
     * @return list<string>
     */
    function svarium_public_auth_path_patterns(
        ?string $panel = null,
        ?\Illuminate\Http\Request $request = null
    ): array {
        $basePatterns = [
            'auth/login',
            'auth/login/*',
            'auth/reset',
            'auth/reset/*',
            'auth/register',
            'auth/register/*',
            'auth/*/method/*',
            'auth/*/verification/*',
            'auth/*/verification/*/resend',
            'auth/*/redirect',
            'auth/*/callback',
        ];

        $panelPrefixes = [];

        if (is_string($panel) && trim($panel) !== '') {
            $resolved = svarium_resolve_panel($panel, $request);
            if ($resolved instanceof \Upsoftware\Svarium\Panel\Panel) {
                $panelPrefixes[] = trim((string) $resolved->prefix, '/');
            }
        } else {
            foreach (svarium_all_panels() as $candidate) {
                $panelPrefixes[] = trim((string) $candidate->prefix, '/');
            }
        }

        $panelPrefixes[] = trim((string) config('upsoftware.panel.prefix', ''), '/');
        $panelPrefixes[] = '';

        $patterns = [];

        foreach (array_values(array_unique($panelPrefixes)) as $prefix) {
            $base = $prefix !== '' ? $prefix.'/' : '';

            foreach ($basePatterns as $pattern) {
                $patterns[] = $base.$pattern;
            }
        }

        $configured = config('upsoftware.panel.public_auth_path_patterns', []);
        if (is_array($configured)) {
            foreach ($configured as $pattern) {
                if (is_string($pattern) && trim($pattern) !== '') {
                    $patterns[] = trim($pattern);
                }
            }
        }

        return array_values(array_unique($patterns));
    }
}

if (! function_exists('svarium_is_public_auth_request')) {
    function svarium_is_public_auth_request(
        \Illuminate\Http\Request $request,
        ?string $panel = null
    ): bool {
        foreach (svarium_public_auth_route_patterns($panel, $request) as $pattern) {
            if ($request->routeIs($pattern)) {
                return true;
            }
        }

        foreach (svarium_public_auth_path_patterns($panel, $request) as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('panel_route_name')) {
    /**
     * Resolve panel route name with configured prefixes.
     *
     * Examples:
     * - panel_route_name('login') => "panel.auth.login"
     * - panel_route_name('auth.login') => "panel.auth.login"
     * - panel_route_name('panel.auth.login') => "panel.auth.login"
     */
    function panel_route_name(string $name, ?string $panel = null): string
    {
        $value = trim($name);
        $resolvedPanel = svarium_resolve_panel($panel);
        $authPrefix = svarium_auth_route_prefix($resolvedPanel?->name);
        $normalizeAuthFallback = static function (string $candidate) use ($resolvedPanel): string {
            $normalizedCandidate = trim($candidate);
            if (
                $normalizedCandidate === ''
                || ! svarium_auth_per_panel_enabled()
                || ! ($resolvedPanel instanceof \Upsoftware\Svarium\Panel\Panel)
            ) {
                return $normalizedCandidate;
            }

            if (\Illuminate\Support\Facades\Route::has($normalizedCandidate)) {
                return $normalizedCandidate;
            }

            if (
                preg_match('/^panel\.([^.]+)\.auth(\..+)?$/', $normalizedCandidate, $matches) !== 1
                || (string) ($matches[1] ?? '') !== (string) svarium_default_panel_name()
            ) {
                return $normalizedCandidate;
            }

            $suffix = isset($matches[2]) ? ltrim((string) $matches[2], '.') : '';

            foreach (svarium_auth_compat_route_prefixes() as $legacyPrefix) {
                $legacyPrefix = trim((string) $legacyPrefix, '.');
                if ($legacyPrefix === '') {
                    continue;
                }

                $fallback = $suffix === '' ? $legacyPrefix : $legacyPrefix.'.'.$suffix;
                if (\Illuminate\Support\Facades\Route::has($fallback)) {
                    return $fallback;
                }
            }

            return $normalizedCandidate;
        };

        if ($value === '') {
            return $normalizeAuthFallback($authPrefix);
        }

        if (preg_match('/^panel\.[^.]+\.auth\./', $value) === 1) {
            return $normalizeAuthFallback($value);
        }

        $legacyPrefixes = svarium_auth_compat_route_prefixes();
        $legacyPrefixMatch = null;

        foreach ($legacyPrefixes as $legacyPrefix) {
            $legacyPrefix = trim((string) $legacyPrefix, '.');
            if ($legacyPrefix === '') {
                continue;
            }

            if ($value === $legacyPrefix || str_starts_with($value, $legacyPrefix.'.')) {
                $legacyPrefixMatch = $legacyPrefix;
                break;
            }
        }

        if ($legacyPrefixMatch !== null && svarium_auth_per_panel_enabled() && $resolvedPanel instanceof \Upsoftware\Svarium\Panel\Panel) {
            $targetPrefix = 'panel.'.trim((string) $resolvedPanel->name).'.auth';
            $suffix = ltrim(substr($value, strlen($legacyPrefixMatch)), '.');

            return $normalizeAuthFallback($suffix === ''
                ? $targetPrefix
                : $targetPrefix.'.'.$suffix);
        }

        if (str_starts_with($value, 'panel.') || preg_match('/^[a-z0-9_-]+\.auth\./i', $value) === 1) {
            return $normalizeAuthFallback($value);
        }

        if (str_starts_with($value, 'auth.')) {
            return $normalizeAuthFallback($authPrefix.'.'.substr($value, 5));
        }

        return $normalizeAuthFallback($authPrefix.'.'.ltrim($value, '.'));
    }
}

if (! function_exists('route_panel')) {
    /**
     * Build URL for panel auth routes.
     *
     * Examples:
     * - route_panel('login') => route('panel.auth.login')
     * - route_panel('register') => route('panel.auth.register')
     */
    function route_panel(
        string $name,
        array $parameters = [],
        bool $absolute = true,
        ?string $panel = null
    ): string {
        return route(panel_route_name($name, $panel), $parameters, $absolute);
    }
}

if (! function_exists('svarium_auth_login_path')) {
    /**
     * Resolve panel login path with priority:
     * 1) explicit panel name,
     * 2) first noPrefix panel,
     * 3) first registered panel.
     */
    function svarium_auth_login_path(?string $panel = null): string
    {
        $resolvedPanel = svarium_resolve_panel($panel);

        $prefix = trim((string) ($resolvedPanel?->prefix ?? ''), '/');
        $path = trim(implode('/', array_filter([$prefix, 'auth/login'])), '/');

        return '/'.$path;
    }
}

if (! function_exists('svarium_login_url')) {
    /**
     * Resolve login URL with priority:
     * 1) app frontend route('login') when preferred,
     * 2) panel auth named route,
     * 3) resolved panel auth path.
     */
    function svarium_login_url(bool $preferFrontend = true, ?string $panel = null): string
    {
        if ($preferFrontend && $panel === null && \Illuminate\Support\Facades\Route::has('login')) {
            return route('login');
        }

        $candidates = [
            panel_route_name('login', $panel),
            panel_route_name('login', svarium_default_panel_name()),
            panel_route_name('login'),
        ];

        foreach (svarium_auth_compat_route_prefixes() as $prefix) {
            $candidates[] = trim($prefix, '.').'.login';
        }

        foreach (array_values(array_unique(array_filter($candidates, static fn ($value) => is_string($value) && $value !== ''))) as $routeName) {
            if (\Illuminate\Support\Facades\Route::has($routeName)) {
                return route($routeName);
            }
        }

        return svarium_auth_login_path($panel);
    }
}

if (! function_exists('panel_href')) {
    /**
     * Build panel URL path with panel prefix resolution.
     *
     * Examples:
     * - panel_href('auth/login') => "/admin/auth/login" or "/auth/login" for noPrefix()
     * - panel_href('pages') => "/admin/pages"
     */
    function panel_href(string $path = '', ?string $panel = null): string
    {
        $normalizedPath = trim($path, '/');
        $panelBase = trim(module_route('', null, null, $panel), '/');

        $fullPath = trim(implode('/', array_filter([$panelBase, $normalizedPath])), '/');

        return '/'.$fullPath;
    }
}

if (! function_exists('svarium_panel_root_path')) {
    function svarium_panel_root_path(?string $panel = null): string
    {
        $resolvedPanel = svarium_resolve_panel($panel);
        $prefix = trim((string) ($resolvedPanel?->prefix ?? ''), '/');

        return $prefix !== '' ? '/'.$prefix : '/';
    }
}

if (! function_exists('svarium_panel_dashboard_option')) {
    function svarium_panel_dashboard_option(string $key, ?string $panel = null, mixed $default = null): mixed
    {
        $dashboardConfig = config('upsoftware.panel.dashboard', []);

        if (! is_array($dashboardConfig)) {
            return $default;
        }

        $rawValue = $dashboardConfig[$key] ?? $default;

        if (! is_array($rawValue)) {
            return $rawValue;
        }

        $resolvedPanel = svarium_resolve_panel($panel);
        $panelName = trim((string) ($resolvedPanel?->name ?? ''));

        if ($panelName !== '' && array_key_exists($panelName, $rawValue)) {
            return $rawValue[$panelName];
        }

        if (array_key_exists('default', $rawValue)) {
            return $rawValue['default'];
        }

        return $default;
    }
}

if (! function_exists('svarium_panel_dashboard_visible')) {
    function svarium_panel_dashboard_visible(?string $panel = null): bool
    {
        return (bool) svarium_panel_dashboard_option('visible', $panel, true);
    }
}

if (! function_exists('svarium_panel_start_path')) {
    function svarium_panel_start_path(?string $panel = null): string
    {
        $resolvedPanel = svarium_resolve_panel($panel);
        $panelName = trim((string) ($resolvedPanel?->name ?? ''));
        $rootPath = svarium_panel_root_path($panelName !== '' ? $panelName : $panel);

        $start = trim((string) svarium_panel_dashboard_option('start', $panelName !== '' ? $panelName : $panel, ''));
        if ($start === '') {
            return $rootPath;
        }

        if (str_starts_with($start, '/')) {
            $normalized = '/'.trim($start, '/');

            return $normalized === '//' ? '/' : $normalized;
        }

        if (str_starts_with($start, 'route:')) {
            $routeName = trim(substr($start, 6));

            if ($routeName !== '' && \Illuminate\Support\Facades\Route::has($routeName)) {
                return route($routeName, [], false);
            }

            return $rootPath;
        }

        if (str_starts_with($start, 'module:')) {
            $module = trim(substr($start, 7));

            if ($module === '') {
                return $rootPath;
            }

            return '/'.ltrim(module_route($module, null, null, $panelName !== '' ? $panelName : null), '/');
        }

        if (\Illuminate\Support\Facades\Route::has($start)) {
            return route($start, [], false);
        }

        if (str_contains($start, '/')) {
            return panel_href($start, $panelName !== '' ? $panelName : null);
        }

        return '/'.ltrim(module_route($start, null, null, $panelName !== '' ? $panelName : null), '/');
    }
}

if (! function_exists('svarium_panel_start_at_root')) {
    function svarium_panel_start_at_root(?string $panel = null): bool
    {
        return (bool) svarium_panel_dashboard_option('start_at_root', $panel, false);
    }
}

if (! function_exists('register_menu')) {
    /**
     * Register runtime menu items from modules/pages.
     *
     * Examples:
     * register_menu([
     *   ['label' => 'Pages', 'route_name' => 'panel.pages', 'path' => ['CMS', 'Content']],
     * ]);
     *
     * // Register named menu container + items:
     * register_menu('setting', 'Ustawienia', [
     *   MenuItem::make()->menu('setting')->label('messages.menu.settings'),
     * ]);
     */
    function register_menu(
        array|string $items,
        string|int|null $navigationId = null,
        array|string|null $source = null
    ): void
    {
        /** @var \Upsoftware\Svarium\Menu\MenuRegistry $registry */
        $registry = app(\Upsoftware\Svarium\Menu\MenuRegistry::class);

        // Shorthand style:
        // register_menu('setting', 'Ustawienia', [MenuItem::make()->menu('setting')->...]);
        if (is_string($items)) {
            $menuId = trim($items);
            if ($menuId === '') {
                return;
            }

            $menuLabel = is_string($navigationId) || is_int($navigationId)
                ? trim((string) $navigationId)
                : '';

            $menuItems = is_array($source) ? $source : [];
            $resolvedSource = is_string($source) ? trim($source) : 'helper';
            if ($resolvedSource === '') {
                $resolvedSource = 'helper';
            }

            $registry->registerNavigation(
                ctype_digit($menuId) ? (int) $menuId : $menuId,
                $menuLabel !== '' ? $menuLabel : null,
                ['source' => $resolvedSource]
            );

            if ($menuItems !== []) {
                $registry->register($menuItems, [
                    'navigation_id' => ctype_digit($menuId) ? (int) $menuId : $menuId,
                    'navigation_label' => $menuLabel !== '' ? $menuLabel : null,
                    'source' => $resolvedSource,
                ]);
            }

            return;
        }

        $resolvedSource = is_string($source) ? trim($source) : 'helper';
        if ($resolvedSource === '') {
            $resolvedSource = 'helper';
        }

        $registry->register($items, [
            'navigation_id' => $navigationId,
            'source' => $resolvedSource,
        ]);
    }
}

if (! function_exists('menu_tree')) {
    /**
     * Return runtime menu tree in root + children structure.
     *
     * Example result:
     * [
     *   'id' => 'navigation-runtime-root:default',
     *   'label' => 'Panel',
     *   'children' => [ ... ]
     * ]
     */
    function menu_tree(string|int|null $navigationId = null, bool $applyOverrides = true): array
    {
        return \Upsoftware\Svarium\Services\NavigationService::make()
            ->getRegisteredTree($navigationId, $applyOverrides);
    }
}

if (! function_exists('menu_children')) {
    /**
     * Return only root children nodes for runtime menu.
     *
     * Example result:
     * [
     *   ['label' => 'Dashboard', ...],
     *   ['label' => 'Users', ...],
     * ]
     */
    function menu_children(string|int|null $navigationId = null, bool $applyOverrides = true): array
    {
        $tree = menu_tree($navigationId, $applyOverrides);

        return is_array($tree['children'] ?? null) ? $tree['children'] : [];
    }
}

if (! function_exists('widget')) {
    /**
     * Build dashboard/page widget definition.
     */
    function widget(string $key): \Upsoftware\Svarium\Widgets\Widget
    {
        return \Upsoftware\Svarium\Widgets\Widget::make($key);
    }
}

if (! function_exists('register_widget')) {
    /**
     * Register runtime widgets globally.
     *
     * Example:
     * register_widget([
     *   widget('pages.stats')
     *      ->on(['dashboard', 'pages.index'])
     *      ->schema(fn (array $data) => [\Upsoftware\Svarium\UI\Components\Block::make()]),
     * ]);
     */
    function register_widget(
        \Upsoftware\Svarium\Widgets\Widget|array $widgets,
        string|array|null $contexts = null,
        ?string $source = null
    ): void {
        $defaults = [];

        if ($contexts !== null) {
            $defaults['contexts'] = $contexts;
        }

        if (is_string($source) && trim($source) !== '') {
            $defaults['source'] = trim($source);
        }

        app(\Upsoftware\Svarium\Widgets\WidgetRegistry::class)->register($widgets, $defaults);
    }
}

if (! function_exists('empty_state')) {
    /**
     * Build EmptyState component (maps to Vue Empty component).
     */
    function empty_state(
        \Upsoftware\Svarium\UI\Component|array|string|int|float|bool|null $content = null
    ): \Upsoftware\Svarium\UI\Components\EmptyState {
        $component = \Upsoftware\Svarium\UI\Components\EmptyState::make();

        if ($content === null) {
            return $component;
        }

        if ($content instanceof \Upsoftware\Svarium\UI\Component || is_array($content)) {
            return $component->children($content);
        }

        return $component->children([
            \Upsoftware\Svarium\UI\Components\Text::make((string) $content),
        ]);
    }
}

if (! function_exists('svarium_resolve_auth_user_model')) {
    function svarium_resolve_auth_user_model(?\Illuminate\Database\Eloquent\Model $user = null): ?\Illuminate\Database\Eloquent\Model
    {
        if ($user instanceof \Illuminate\Database\Eloquent\Model) {
            return $user;
        }

        try {
            $resolved = auth()->user();

            return $resolved instanceof \Illuminate\Database\Eloquent\Model ? $resolved : null;
        } catch (\Throwable) {
            return null;
        }
    }
}

if (! function_exists('svarium_session_store')) {
    function svarium_session_store(): mixed
    {
        try {
            if (function_exists('session')) {
                $store = session();
                if (is_object($store) && method_exists($store, 'get') && method_exists($store, 'put')) {
                    return $store;
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        try {
            $request = request();
            if ($request instanceof \Illuminate\Http\Request && $request->hasSession()) {
                $store = $request->session();
                if (is_object($store) && method_exists($store, 'get') && method_exists($store, 'put')) {
                    return $store;
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        return null;
    }
}

if (! function_exists('svarium_session_get')) {
    function svarium_session_get(string $key, mixed $default = null): mixed
    {
        $store = svarium_session_store();
        if (! is_object($store) || ! method_exists($store, 'get')) {
            return $default;
        }

        try {
            return $store->get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}

if (! function_exists('svarium_session_put')) {
    function svarium_session_put(string $key, mixed $value): void
    {
        $store = svarium_session_store();
        if (! is_object($store) || ! method_exists($store, 'put')) {
            return;
        }

        try {
            $store->put($key, $value);
        } catch (\Throwable) {
            // ignore
        }
    }
}

if (! function_exists('debug_role')) {
    /**
     * Get or set role switch debug mode.
     *
     * - debug_role() => bool (session fallback to config)
     * - debug_role(true|false) => bool (persist in session and return current value)
     */
    function debug_role(?bool $enabled = null): bool
    {
        $sessionKey = 'svarium.debug_role';
        $default = (bool) config('upsoftware.ui.sidebar_user.debug_role', false);

        if ($enabled !== null) {
            svarium_session_put($sessionKey, $enabled);
        }

        return (bool) svarium_session_get($sessionKey, $default);
    }
}

if (! function_exists('svarium_role_model_type_candidates')) {
    /**
     * @return array<int, string>
     */
    function svarium_role_model_type_candidates(\Illuminate\Database\Eloquent\Model $user): array
    {
        $candidates = [
            trim((string) (function_exists('svarium_model_type') ? svarium_model_type($user) : $user::class)),
            trim((string) $user::class),
            trim((string) config('upsoftware.models.user', '')),
            trim((string) config('auth.providers.users.model', '')),
            'App\\Models\\User',
            'Upsoftware\\Svarium\\Models\\User',
        ];

        $unique = [];
        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $normalized = ltrim($candidate, '\\');
            if (in_array($normalized, $unique, true)) {
                continue;
            }

            $unique[] = $normalized;
        }

        return $unique;
    }
}

if (! function_exists('svarium_resolve_role_label')) {
    function svarium_resolve_role_label(object $role): string
    {
        $locale = (string) app()->getLocale();
        $fallbackLocale = (string) config('app.fallback_locale', 'en');

        if (method_exists($role, 'getTranslation')) {
            try {
                $translated = trim((string) $role->getTranslation('name', $locale, false));
                if ($translated !== '') {
                    return $translated;
                }

                $fallback = trim((string) $role->getTranslation('name', $fallbackLocale, false));
                if ($fallback !== '') {
                    return $fallback;
                }
            } catch (\Throwable) {
                // ignore and continue with fallback resolution
            }
        }

        $name = '';
        if (method_exists($role, 'getAttribute')) {
            $name = trim((string) $role->getAttribute('name'));
        } elseif (isset($role->name)) {
            $name = trim((string) $role->name);
        }

        if ($name !== '') {
            $decoded = json_decode($name, true);
            if (is_array($decoded)) {
                $fromLocale = trim((string) ($decoded[$locale] ?? ''));
                if ($fromLocale !== '') {
                    return $fromLocale;
                }

                $fromFallback = trim((string) ($decoded[$fallbackLocale] ?? ''));
                if ($fromFallback !== '') {
                    return $fromFallback;
                }

                $first = reset($decoded);
                $firstString = trim((string) ($first ?? ''));
                if ($firstString !== '') {
                    return $firstString;
                }
            }

            return $name;
        }

        $nameLocale = method_exists($role, 'getAttribute')
            ? trim((string) $role->getAttribute('name_locale'))
            : trim((string) ($role->name_locale ?? ''));

        if ($nameLocale !== '') {
            return $nameLocale;
        }

        $roleKey = method_exists($role, 'getAttribute')
            ? trim((string) $role->getAttribute('role_key'))
            : trim((string) ($role->role_key ?? ''));

        if ($roleKey !== '') {
            return $roleKey;
        }

        $id = method_exists($role, 'getAttribute')
            ? $role->getAttribute('id')
            : ($role->id ?? null);

        return trim((string) ($id ?? ''));
    }
}

if (! function_exists('get_roles')) {
    /**
     * @return array<int, array{id:int,name:string,name_locale:string,role_key:string,guard_name:string}>
     */
    function get_roles(?\Illuminate\Database\Eloquent\Model $user = null): array
    {
        $user = svarium_resolve_auth_user_model($user);
        if (! $user) {
            return [];
        }

        $cacheKey = ltrim($user::class, '\\').'#'.(string) $user->getKey();
        static $cache = [];
        if (isset($cache[$cacheKey]) && is_array($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $table = trim((string) config('permission.table_names.model_has_roles', 'model_has_roles'));
        if ($table === '') {
            $table = 'model_has_roles';
        }

        $modelHasRoleClass = (string) (function_exists('get_model') ? get_model('model_has_role') : '');
        if ($modelHasRoleClass === '' || ! class_exists($modelHasRoleClass)) {
            $modelHasRoleClass = \Upsoftware\Svarium\Models\ModelHasRole::class;
        }

        $roleIds = [];

        try {
            $modelHasRole = new $modelHasRoleClass;
            $connectionName = (string) ($modelHasRole->getConnectionName() ?: config('database.default'));
            $schema = \Illuminate\Support\Facades\Schema::connection($connectionName);

            if (! $schema->hasTable($table)) {
                $cache[$cacheKey] = [];

                return [];
            }

            $query = $modelHasRoleClass::query()
                ->from($table)
                ->where('model_id', $user->getKey());

            if ($schema->hasColumn($table, 'model_type')) {
                $candidates = svarium_role_model_type_candidates($user);
                if ($candidates !== []) {
                    $query->whereIn('model_type', $candidates);
                }
            }

            if ($schema->hasColumn($table, 'status')) {
                $query->where('status', 1);
            }

            $roleIds = $query->pluck('role_id')
                ->map(static fn (mixed $value): int => (int) $value)
                ->filter(static fn (int $value): bool => $value > 0)
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable) {
            $roleIds = [];
        }

        if ($roleIds === [] && method_exists($user, 'roles')) {
            try {
                $roleIds = $user->roles()
                    ->pluck('id')
                    ->map(static fn (mixed $value): int => (int) $value)
                    ->filter(static fn (int $value): bool => $value > 0)
                    ->unique()
                    ->values()
                    ->all();
            } catch (\Throwable) {
                $roleIds = [];
            }
        }

        if ($roleIds === []) {
            $cache[$cacheKey] = [];
            svarium_session_put('svarium.roles', []);

            return [];
        }

        $roleModelClass = (string) (function_exists('get_model') ? get_model('role') : '');
        if ($roleModelClass === '' || ! class_exists($roleModelClass)) {
            $roleModelClass = (string) config('permission.models.role', \Spatie\Permission\Models\Role::class);
        }

        if ($roleModelClass === '' || ! class_exists($roleModelClass)) {
            $cache[$cacheKey] = [];

            return [];
        }

        try {
            $roles = $roleModelClass::query()
                ->whereIn('id', $roleIds)
                ->get()
                ->keyBy('id');
        } catch (\Throwable) {
            $cache[$cacheKey] = [];

            return [];
        }

        $items = [];

        foreach ($roleIds as $roleId) {
            $role = $roles->get($roleId);
            if (! $role) {
                continue;
            }

            $name = svarium_resolve_role_label($role);
            $roleKey = trim((string) $role->getAttribute('role_key'));
            $guardName = trim((string) $role->getAttribute('guard_name'));

            $items[] = [
                'id' => (int) $roleId,
                'name' => $name,
                'name_locale' => $name,
                'role_key' => $roleKey,
                'guard_name' => $guardName,
            ];
        }

        $cache[$cacheKey] = $items;
        svarium_session_put('svarium.roles', $items);

        return $items;
    }
}

if (! function_exists('change_role')) {
    function change_role(int|string|array|object|null $role, ?\Illuminate\Database\Eloquent\Model $user = null): bool
    {
        $roles = get_roles($user);
        if ($roles === []) {
            return false;
        }

        $targetId = 0;

        if (is_numeric($role)) {
            $targetId = (int) $role;
        } elseif (is_array($role)) {
            $targetId = (int) ($role['id'] ?? 0);
            if ($targetId <= 0) {
                $token = trim((string) ($role['role_key'] ?? $role['name'] ?? ''));
                foreach ($roles as $candidate) {
                    if (strcasecmp((string) ($candidate['role_key'] ?? ''), $token) === 0
                        || strcasecmp((string) ($candidate['name'] ?? ''), $token) === 0) {
                        $targetId = (int) ($candidate['id'] ?? 0);
                        break;
                    }
                }
            }
        } elseif (is_object($role)) {
            $targetId = (int) (data_get($role, 'id', 0));
        } elseif (is_string($role)) {
            $token = trim($role);
            foreach ($roles as $candidate) {
                if ((string) ($candidate['id'] ?? '') === $token
                    || strcasecmp((string) ($candidate['role_key'] ?? ''), $token) === 0
                    || strcasecmp((string) ($candidate['name'] ?? ''), $token) === 0) {
                    $targetId = (int) ($candidate['id'] ?? 0);
                    break;
                }
            }
        }

        if ($targetId <= 0) {
            return false;
        }

        $allowedIds = array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $roles);
        if (! in_array($targetId, $allowedIds, true)) {
            return false;
        }

        svarium_session_put('svarium.active_role_id', $targetId);
        svarium_session_put('role_id', $targetId);

        return true;
    }
}

if (! function_exists('get_role_id')) {
    function get_role_id(?\Illuminate\Database\Eloquent\Model $user = null): ?int
    {
        $roles = get_roles($user);
        if ($roles === []) {
            return null;
        }

        $allowedIds = array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $roles);

        $active = (int) (svarium_session_get('svarium.active_role_id', svarium_session_get('role_id', 0)) ?? 0);
        if ($active > 0 && in_array($active, $allowedIds, true)) {
            return $active;
        }

        $first = (int) ($allowedIds[0] ?? 0);
        if ($first > 0) {
            svarium_session_put('svarium.active_role_id', $first);
            svarium_session_put('role_id', $first);

            return $first;
        }

        return null;
    }
}

if (! function_exists('ge_role_id')) {
    function ge_role_id(?\Illuminate\Database\Eloquent\Model $user = null): ?int
    {
        return get_role_id($user);
    }
}

if (! function_exists('get_role')) {
    /**
     * @return array{id:int,name:string,name_locale:string,role_key:string,guard_name:string}|null
     */
    function get_role(?\Illuminate\Database\Eloquent\Model $user = null): ?array
    {
        $roles = get_roles($user);
        if ($roles === []) {
            return null;
        }

        $activeId = get_role_id($user);
        if ($activeId === null) {
            return $roles[0] ?? null;
        }

        foreach ($roles as $role) {
            if ((int) ($role['id'] ?? 0) === $activeId) {
                return $role;
            }
        }

        return $roles[0] ?? null;
    }
}

if (! function_exists('get_role_name')) {
    function get_role_name(?\Illuminate\Database\Eloquent\Model $user = null): ?string
    {
        $role = get_role($user);
        if (! is_array($role)) {
            return null;
        }

        return trim((string) ($role['name_locale'] ?? $role['name'] ?? '')) ?: null;
    }
}

if (! function_exists('get_role_key')) {
    function get_role_key(?\Illuminate\Database\Eloquent\Model $user = null): ?string
    {
        $role = get_role($user);
        if (! is_array($role)) {
            return null;
        }

        return trim((string) ($role['role_key'] ?? '')) ?: null;
    }
}
