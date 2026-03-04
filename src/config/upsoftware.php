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
        'root_layout' => 'CleanLayout',
        'definition_layout_types' => ['AuthLayout'],
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
        'otp' => [
            // Global OTP toggle for login flow.
            'enabled' => true,
            // Allowed methods: email, sms, app.
            'methods' => ['email', 'sms', 'app'],
            // When false, user cannot disable OTP in account settings.
            'allow_user_disable' => true,
            // Default user OTP status when user-specific setting is missing.
            'default_enabled' => true,
        ],
    ],
    'tenancy' => [
        'enabled' => env('SVARIUM_TENANCY_ENABLED', false),
        // Supported: column | database
        'mode' => env('SVARIUM_TENANCY_MODE', 'column'),
        // Paths used by tenant migrate/seed commands.
        'paths' => [
            // User tenant migrations (executed on tenant DBs in database mode).
            'tenant_migrations' => app_path('Svarium/Tenancy/Migrations'),
            // Legacy key (kept for backward compatibility).
            'migrations' => app_path('Svarium/Tenancy/Migrations'),
            // User tenant seeders.
            'tenant_seeders' => app_path('Svarium/Tenancy/Seeders'),
            // Legacy key (kept for backward compatibility).
            'seeders' => app_path('Svarium/Tenancy/Seeders'),
        ],
        'seeders' => [
            'namespace' => 'App\\Svarium\\Tenancy\\Seeders',
        ],
        'domains' => [
            // Enable resolving tenant context by request host/domain.
            'enabled' => env('SVARIUM_TENANCY_DOMAINS_ENABLED', true),
            // Comma-separated env values are also supported by the manager.
            'central_domains' => [],
            // SEO behavior for alias/primary domains.
            'seo' => [
                'canonical_on_primary' => true,
                'noindex_aliases' => true,
            ],
        ],
        // Optional binding of tenant to business owner entity.
        'owner' => [
            'enabled' => false,
            'type_column' => 'owner_type',
            'id_column' => 'owner_id',
            // Example: ['customer' => App\Models\Customer::class]
            'map' => [],
        ],
        // Optional 1:1 extension table for tenant additional data.
        'profile' => [
            'enabled' => true,
            'table' => 'tenant_profiles',
            'foreign_key' => 'tenant_id',
            'model' => \Upsoftware\Svarium\Models\TenantProfile::class,
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
            // Optional polymorphic maps for assigning model records to tenants/domains.
            'model_maps' => [
                'tenants' => [
                    'enabled' => true,
                    'table' => 'model_has_tenants',
                ],
                'domains' => [
                    'enabled' => true,
                    'table' => 'model_has_domains',
                    'domain_key' => 'domain_id',
                ],
            ],
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
        'model_has_tenant' => \Upsoftware\Svarium\Models\ModelHasTenant::class,
        'model_has_tenants' => \Upsoftware\Svarium\Models\ModelHasTenant::class,
        'model_has_domain' => \Upsoftware\Svarium\Models\ModelHasDomain::class,
        'model_has_domains' => \Upsoftware\Svarium\Models\ModelHasDomain::class,
        'model_has_domain_tenants' => \Upsoftware\Svarium\Models\ModelHasDomainTenant::class,
        'navigation' => \Upsoftware\Svarium\Models\Navigation::class,
        'permission' => \Spatie\Permission\Models\Permission::class,
        'role' => \Upsoftware\Svarium\Models\Role::class,
        'setting' => \Upsoftware\Svarium\Models\Setting::class,
        'tenant' => \Upsoftware\Svarium\Models\Tenant::class,
        'tenant_profile' => \Upsoftware\Svarium\Models\TenantProfile::class,
        'domain' => \Upsoftware\Svarium\Models\Domain::class,
        'tenant_domain' => \Upsoftware\Svarium\Models\TenantDomain::class,
        'user' => \Upsoftware\Svarium\Models\User::class,
        'user_auth' => \Upsoftware\Svarium\Models\UserAuth::class,
        'user_auth_code' => \Upsoftware\Svarium\Models\UserAuthCode::class,
    ],
    'components' => [

    ],
    'logo' => []
];
