<?php

use \Upsoftware\Svarium\Services\DeviceTracking\DeviceHijackingDetectorDefault;

return [
    'middleware' => [
        'web' => [],
        'api' => [],
    ],
    'api' => [
        'enabled' => true,
        'prefix' => 'api/v1',
        'auth' => [
            'driver' => env('SVARIUM_API_DRIVER', 'sanctum'),
            'guard' => 'sanctum',
            'middleware' => ['auth:sanctum'],
            'custom_handler' => null,
        ]
    ],
    'table' => [
        'action_display' => 'inline',
        'pagination' => true,
        'per_page' => 15,
    ],
    'panel' => [
        'enabled' => true,
        'route_prefix' => 'panel.auth',
        'prefix' => '',
    ],
    'tracking' => [
        'enabled' => true,
        'user_model' => null,
        'detect_on_login' => true,
        'geoip_provider' => null,
        'device_cookie' => 'device_uuid',
        'cookie_http_only' => true,
        'session_key' => 'device-tracking',
        'hijacking_detector' => DeviceHijackingDetectorDefault::class,
    ],
    'models' => [
        'activity' => \Upsoftware\Svarium\Models\Activity::class,
        'device' => \Upsoftware\Svarium\Models\Device::class,
        'device_user' => \Upsoftware\Svarium\Models\DeviceUser::class,
        'model_has_role' => \Upsoftware\Svarium\Models\ModelHasRole::class,
        'navigation' => \Upsoftware\Svarium\Models\Navigation::class,
        'permission' => \Spatie\Permission\Models\Permission::class,
        'role' => \Upsoftware\Svarium\Models\Role::class,
        'setting' => \Upsoftware\Svarium\Models\Setting::class,
        'tenant' => \Upsoftware\Svarium\Models\Tenant::class,
        'user' => \Upsoftware\Svarium\Models\User::class,
        'user_auth' => \Upsoftware\Svarium\Models\UserAuth::class,
        'user_auth_code' => \Upsoftware\Svarium\Models\UserAuthCode::class,
    ],
    'components' => [

    ]
];
