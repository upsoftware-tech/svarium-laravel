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

    if (config()->has('tenancy.database.central_connection')) {
        return config('tenancy.database.central_connection');
    }

    if (config()->has('database.connections.central')) {
        return 'central';
    }

    return config('database.default');
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
        $panelSegment = trim((string) ($panel ?? config('upsoftware.panel.name', 'admin')), '/');
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
