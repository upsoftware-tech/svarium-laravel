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

function locales() {
    $locales = get_model('setting')::getSettingGlobal('locales', []);
    return array_values(array_map(function ($value) {
        $array = [];
        $array["value"] = $value["value"] ?? $value["code"] ?? $value["id"] ?? '';

        if (!isset($value["icon"])) {
            $array["icon"] = ["type" => "icon", "value" => "cif:".$value['flag'] ?? $value['code']];
        } else {
            $array["icon"] = $value["icon"];
        }

        $array["label"] = $value["native"] ?? $value['localized'] ?? '';

        return $array;
    }, $locales));
}

function set_title($title) {
    layout()->set_title($title);
}

function get_title() {
    return layout()->get_title();
}

function central_connection() {
    if ($forcedConnection = config('svarium.database_connection')) {
        return $forcedConnection;
    }

    if (config()->has('upsoftware.tenancy.database.central_connection')) {
        return config('upsoftware.tenancy.database.central_connection');
    }

    if (config()->has('tenancy.database.central_connection')) {
        return config('tenancy.database.central_connection');
    }

    if (config()->has('database.connections.central')) {
        return 'central';
    }

    return config('database.default');
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


function get_model(string $model): string {
    $models = config('upsoftware.models', []);

    if (!isset($models[$model])) {
        throw new \Exception("Model {$model} is not defined in configuration.");
    }

    return $models[$model];
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
            $noPrefixPanels = array_values(array_filter(
                $panels,
                fn ($candidate) => $candidate instanceof \Upsoftware\Svarium\Panel\Panel && $candidate->prefix === null
            ));

            if (count($noPrefixPanels) === 1) {
                $resolvedPanel = $noPrefixPanels[0];
            }
        }

        if (! $resolvedPanel instanceof \Upsoftware\Svarium\Panel\Panel) {
            $configuredName = trim((string) config('upsoftware.panel.name', ''));

            if ($configuredName !== '') {
                $configuredPanel = $registry->get($configuredName);
                if ($configuredPanel instanceof \Upsoftware\Svarium\Panel\Panel) {
                    $resolvedPanel = $configuredPanel;
                }
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

        $slug = (string) str($moduleName)->replace('_', '')->plural()->lower();
        $base = trim(implode('/', array_filter([$panelSegment, $slug])), '/');

        $normalizedAction = strtolower(trim((string) $action));

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

if (! function_exists('panel_route_name')) {
    /**
     * Resolve panel route name with configured prefixes.
     *
     * Examples:
     * - panel_route_name('login') => "panel.auth.login"
     * - panel_route_name('auth.login') => "panel.auth.login"
     * - panel_route_name('panel.auth.login') => "panel.auth.login"
     */
    function panel_route_name(string $name): string
    {
        $value = trim($name);
        $authPrefix = trim((string) config('upsoftware.panel.route_prefix', 'panel.auth'), '.');

        if ($value === '') {
            return $authPrefix;
        }

        if (str_starts_with($value, 'panel.')) {
            return $value;
        }

        if (str_starts_with($value, 'auth.')) {
            return $authPrefix.'.'.substr($value, 5);
        }

        return $authPrefix.'.'.ltrim($value, '.');
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
        bool $absolute = true
    ): string {
        return route(panel_route_name($name), $parameters, $absolute);
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

if (! function_exists('register_menu')) {
    /**
     * Register runtime menu items from modules/pages.
     *
     * Example:
     * register_menu([
     *   ['label' => 'Pages', 'route_name' => 'panel.pages', 'path' => ['CMS', 'Content']],
     * ]);
     */
    function register_menu(array $items, string|int|null $navigationId = null, ?string $source = null): void
    {
        app(\Upsoftware\Svarium\Menu\MenuRegistry::class)->register($items, [
            'navigation_id' => $navigationId,
            'source' => $source ?? 'helper',
        ]);
    }
}
