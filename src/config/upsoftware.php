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
        'name' => env('SVARIUM_PANEL_NAME', 'admin'),
        'route_prefix' => 'panel.auth',
        'prefix' => '',
        'public_auth_route_patterns' => [
            'panel.auth.login',
            'panel.auth.login.*',
            'panel.auth.reset',
            'panel.auth.reset.*',
            'panel.auth.register',
            'panel.auth.register.*',
            'panel.auth.method',
            'panel.auth.method.*',
            'panel.auth.verification',
            'panel.auth.verification.*',
            'panel.auth.redirect',
            'panel.auth.callback',
        ],
        'public_auth_path_patterns' => [
            'auth/login',
            'auth/login/*',
            'auth/reset',
            'auth/reset/*',
            'auth/register',
            'auth/register/*',
        ],
    ],
    'auth' => [
        'register' => [
            'enabled' => true,
            'auto_login' => true,
            'layout' => 'AuthLayout',
            'redirect_to' => '/',
            'redirect_route' => null,
            'login_redirect_route' => 'panel.auth.login',
            'success_message' => 'Account has been created.',
            'creator' => null,
            'after_create' => null,
            'schema' => null,
            'activation' => [
                'mode' => 'none',
                'verification_route' => 'panel.auth.verification',
                'verification_type' => 'register',
                'custom_handler' => null,
            ],
            'events' => [
                'dispatch_registered' => true,
                'dispatch' => [],
                'listeners' => [],
            ],
            'password_rules' => [
                'required',
                'string',
                'min:8',
            ],
            'fields' => [
                [
                    'name' => 'email',
                    'label' => 'Email address',
                    'type' => 'email',
                    'required' => true,
                    'autocomplete' => 'email',
                    'rules' => ['required', 'email'],
                ],
                [
                    'name' => 'password',
                    'label' => 'Password',
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'new-password',
                ],
                [
                    'name' => 'company',
                    'label' => 'Company',
                    'type' => 'text',
                    'required' => false,
                    'autocomplete' => 'organization',
                ],
            ],
        ],
    ],
    'tenancy' => [
        'enabled' => env('SVARIUM_TENANCY_ENABLED', false),
        // Supported: column | database
        'mode' => env('SVARIUM_TENANCY_MODE', 'column'),
        // Paths used by tenant migrate/seed commands.
        'paths' => [
            'migrations' => app_path('Svarium/Tenancy/Migrations'),
            'seeders' => app_path('Svarium/Tenancy/Seeders'),
        ],
        'seeders' => [
            'namespace' => 'App\\Svarium\\Tenancy\\Seeders',
        ],
        'domains' => [
            // Comma-separated env values are also supported by the manager.
            'central_domains' => [],
        ],
        'database' => [
            // Connection used by central/shared tables (settings/users/roles etc.).
            'central_connection' => env('SVARIUM_TENANCY_CENTRAL_CONNECTION', 'central'),
            // Runtime connection name for tenant database mode.
            'tenant_connection' => env('SVARIUM_TENANCY_TENANT_CONNECTION', 'tenant'),
            // Base connection template copied before swapping tenant credentials.
            'template_connection' => env('SVARIUM_TENANCY_TEMPLATE_CONNECTION', env('DB_CONNECTION', 'mysql')),
        ],
        'column' => [
            // Column used to partition shared tables by tenant.
            'column' => 'tenant_id',
            // When true and no tenant context is resolved, scoped models return empty results.
            'strict' => false,
        ],
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
        'tenant_domain' => \Upsoftware\Svarium\Models\TenantDomain::class,
        'user' => \Upsoftware\Svarium\Models\User::class,
        'user_auth' => \Upsoftware\Svarium\Models\UserAuth::class,
        'user_auth_code' => \Upsoftware\Svarium\Models\UserAuthCode::class,
    ],
    'components' => [

    ]
];
