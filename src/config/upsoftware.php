<?php

use Upsoftware\Svarium\Layouts\Panel\FormTabLayout;
use Upsoftware\Svarium\Services\DeviceTracking\DeviceHijackingDetectorDefault;

return [
    'middleware' => [
        'web' => [],
        'api' => [],
    ],
    'debug' => [
        'form' => false,
    ],
    'api' => [
        'enabled' => true,
        'prefix' => 'api/v1',
        'auth' => [
            'driver' => env('SVARIUM_API_DRIVER', 'sanctum'),
            'guard' => 'sanctum',
            'middleware' => ['auth:sanctum'],
            'custom_handler' => null,
        ],
        'docs' => [
            // Enable Redoc/OpenAPI endpoints.
            'enabled' => env('SVARIUM_API_DOCS_ENABLED', true),
            // URL path for ReDoc page.
            'path' => env('SVARIUM_API_DOCS_PATH', 'api/docs'),
            // URL path for OpenAPI JSON spec.
            'spec_path' => env('SVARIUM_API_DOCS_SPEC_PATH', 'api/openapi.json'),
            // If true, docs/spec are visible without login.
            'public' => true,
            // Extra middleware for docs/spec routes.
            'middleware' => [],
            // Auto regenerate spec on docs/spec request.
            'auto_generate' => true,
            // Where generated spec is saved (absolute or base_path-relative).
            'storage_path' => 'storage/app/svarium/openapi.json',
            // Optional custom title/version shown in ReDoc.
            'title' => null,
            'version' => null,
            // Optional explicit server URL in OpenAPI.
            'server_url' => null,
            // Optional static ReDoc tag groups (x-tagGroups).
            // Example:
            // [
            //   ['name' => 'MSIG API V2', 'tags' => ['Ogłoszenia', 'Pacjenci']],
            // ]
            'tag_groups' => [],
            // Keep tags without explicit group visible in ReDoc under fallback group.
            'tag_groups_include_ungrouped' => false,
            // Fallback group name for ungrouped tags.
            'ungrouped_tag_group_name' => 'Other',
            // ReDoc rendering options (passed to Redoc.init).
            'redoc' => [
                // Show field-level "Example" for object schemas (request/response bodies).
                'showObjectSchemaExamples' => true,
            ],
        ],
    ],
    'table' => [
        'action_display' => 'inline',
        'pagination' => [
            'enabled' => true,
            'rowsPerPageOptions' => [10, 20, 30, 50, 100, 0],
            'rowsPerPage' => 50,
            'rowsPerPageLabel' => null,
            'rowsPerPageAllLabel' => null,
            'paginationLabel' => null,
            'showButtonLabel' => true,
            'showFirstLabel' => true,
            'showLastLabel' => true,
            'ellipsisAfter' => 7,
            'firstButtonLabel' => null,
            'previousButtonLabel' => null,
            'nextButtonLabel' => null,
            'lastButtonLabel' => null,
        ],
        'condensed' => false,
        'bordered' => false,
        'searchbar' => false,
        'selectable' => true,
        'sortable' => false,
        'multi_sortable' => false,
        'column_visibility' => false,
        'create_action' => false,
        'views_addable' => true,
        'custom_columns' => true,
        'exported' => true,
        'imported' => true,
    ],
    'form' => [
        'required_indicator' => [
            'enabled' => false,
            'label' => false,
            'position' => 'left',
        ],
        'select_options' => [
            // Endpoint used by Select::optionsModel() in remote mode (dependsOn / large dictionaries).
            'path' => 'svarium/form/options/model',
            // Middleware for endpoint above.
            'middleware' => ['auth'],
            // Max number of options returned in one request.
            'limit' => 200,
        ],
    ],
    'resource' => [
        // Layout wrapper used to render form tab content in Resource create/edit screens.
        'form_tab_layout' => FormTabLayout::class,
        'form' => [
            'tab' => [
                'position' => 'left',
                'variant' => 'simple',
                'title' => true,
                // null|default|card|cards|tabs
                'view' => null,
                // Backward compatibility fallback for old projects.
                'card' => false,
                'validation_error_icon' => [
                    'enabled' => false,
                    'icon' => 'lucide:circle-alert',
                ],
            ],
            'language' => [
                // inline = checklist/inline options, select = Select component.
                'display' => 'inline',
                // Used when display = select.
                'multiple' => false,
                // Show flag icon in inline language selector.
                'showIcon' => false,
                // Show label text in inline language selector.
                'showLabel' => true,
            ],
        ],
    ],
    'colors' => [
        // Default file used by "php artisan svarium:app.colors".
        'css_file' => 'resources/css/app.css',
        // Default neutral tone (slate|gray|zinc|neutral|stone|taupe|mauve|mist|olive).
        'tone' => 'zinc',
        // Default PRIMARY color for light and dark themes.
        'primary' => [
            'light' => [
                'color' => 'blue',
                'shade' => 500,
            ],
            'dark' => [
                'color' => 'blue',
                'shade' => 500,
            ],
        ],
    ],
    'panel' => [
        'enabled' => true,
        'name' => env('SVARIUM_PANEL_NAME', 'admin'),
        // Legacy auth route prefix used for backward compatibility aliases.
        'route_prefix' => 'panel.auth',
        'prefix' => '',
        'container' => [
            // Wrap panel body content with Container component.
            'enabled' => true,
            // false = Tailwind .container, true = full width.
            'fluid' => false,
            // left|center|right
            'position' => 'center',
        ],
        'root_layout' => 'CleanLayout',
        'definition_layout_types' => ['AuthLayout'],
        'dashboard' => [
            // Show/hide Dashboard (Pulpit) in the main panel menu.
            'visible' => true,
            // Start page after entering panel root (examples: null, 'patient', 'module:patient', '/admin/patients').
            'start' => null,
            // Render "start" module directly under panel root URL (e.g. /admin or /) without redirect.
            'start_at_root' => false,
        ],
        'auth' => [
            // true: register auth routes per panel (e.g. panel.admin.auth.login).
            // false: register one global auth route prefix (legacy mode).
            'per_panel' => true,
            // Optional default panel name used for compatibility aliases (panel.auth.*).
            'default_panel' => null,
        ],
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
    'navigation' => [
        'per_role' => [
            // Auto-select navigation by active role when navigation_id is not provided explicitly.
            // Map keys: role_key, name_locale, translated role name, id or id:{role_id}.
            'enabled' => true,
            'map' => [
                // 'superadmin' => 'main_menu',
                // 'id:2' => 'accounting_menu',
            ],
        ],
    ],
    'lang' => [
        // Locale used to resolve translation keys in attributes (for labels defined as __('...')).
        // Frontend then translates this key dynamically with current UI locale.
        'key_locale' => env('SVARIUM_LANG_KEY_LOCALE', 'en'),
    ],
    'validation' => [
        // Hard fallback dictionary for validation attribute labels when runtime translations
        // are unavailable in current app context.
        // You can override/extend in your app config.
        'attribute_fallbacks' => [
            'pl' => [
                'name' => 'Nazwa',
            ],
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
        // Role keys that can log in with tenant bypass in column tenancy mode.
        'tenant_bypass_role_keys' => ['superadmin'],
        // Scope of tenant bypass for roles above:
        // - all_tenants: role can log in on every tenant/domain context.
        // - tenant: role must exist for current tenant (or global role with null tenant_id).
        'tenant_bypass_scope' => 'all_tenants',
        'otp' => [
            /**************************************************************************
             * Służy do:
             * Konfiguracji procesu OTP (kodów jednorazowych) dla logowania i weryfikacji.
             *
             * enabled:
             * Włącza/wyłącza globalnie OTP.
             *
             * methods:
             * Dostępne metody OTP (email, sms, app).
             *
             * token_ttl_minutes:
             * Czas ważności tokenu sesji OTP (hash user_auth w URL method/verification).
             *
             * code_ttl_minutes:
             * Czas ważności kodu OTP.
             *
             * code_length:
             * Długość kodu OTP.
             *
             * code_pattern:
             * Typ znaków w kodzie OTP:
             * - digits            (tylko cyfry)
             * - chars             (tylko litery A-Z)
             * - digits_and_chars  (litery A-Z i cyfry)
             *
             * invalidate_previous_codes:
             * true  -> nowy kod unieważnia poprzednie aktywne kody.
             * false -> wszystkie aktywne i niewygasłe kody pozostają ważne.
             *
             * resend_seconds:
             * Minimalny odstęp czasu pomiędzy kolejnymi żądaniami resend.
             *
             * resend_limit:
             * Ograniczenie antyspamowe resend (max_attempts / decay_minutes).
             *
             * verification:
             * Blokada po błędnych próbach wpisania kodu.
             *
             * show_all_methods:
             * true  -> pokazuje wszystkie metody (również niedostępne).
             * false -> pokazuje tylko metody aktywne/dostępne dla użytkownika.
             *
             * allow_user_disable:
             * Czy użytkownik może wyłączyć OTP na swoim koncie.
             *
             * default_enabled:
             * Domyślny stan OTP dla użytkownika, gdy brak ustawienia per user.
             *
             * rate_limit_store:
             * Store cache używany wyłącznie przez limiter OTP (resend/verify).
             * Zalecane: file lub redis.
             **************************************************************************/
            // Global OTP toggle for login flow.
            'enabled' => true,
            // Allowed methods: email, sms, app.
            'methods' => ['email', 'sms', 'app'],
            // OTP session token lifetime in minutes (user_auth hash URL).
            'token_ttl_minutes' => 10,
            // OTP code lifetime in minutes.
            'code_ttl_minutes' => 10,
            // OTP code length.
            'code_length' => 8,
            // OTP code pattern: digits | chars | digits_and_chars.
            'code_pattern' => 'digits',
            // true = new code invalidates previous active codes.
            'invalidate_previous_codes' => true,
            // Seconds before the "resend code" action becomes available.
            'resend_seconds' => 60,
            // Resend anti-spam limiter.
            'resend_limit' => [
                // Max resend attempts inside decay window.
                'max_attempts' => 5,
                // Decay window in minutes.
                'decay_minutes' => 15,
            ],
            // Verification lock after too many invalid code attempts.
            'verification' => [
                // Max invalid attempts before lock.
                'max_failed_attempts' => 5,
                // Lock duration in minutes.
                'lock_minutes' => 15,
            ],
            // When true, selection screen shows all configured OTP methods (available and unavailable).
            // When false, selection screen shows only active/available methods for current user.
            'show_all_methods' => false,
            // When false, user cannot disable OTP in account settings.
            'allow_user_disable' => true,
            // Default user OTP status when user-specific setting is missing.
            'default_enabled' => true,
            // Dedicated cache store for OTP rate limit (prevents tenant DB cache table errors).
            'rate_limit_store' => env('SVARIUM_OTP_RATE_LIMIT_STORE', 'file'),
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
        'translation_keyset' => \Upsoftware\Svarium\Models\TranslationKeyset::class,
        'translation_key' => \Upsoftware\Svarium\Models\TranslationKey::class,
        'translation_value' => \Upsoftware\Svarium\Models\TranslationValue::class,
        'translation_revision' => \Upsoftware\Svarium\Models\TranslationRevision::class,
        'translation_order' => \Upsoftware\Svarium\Models\TranslationOrder::class,
        'translation_order_item' => \Upsoftware\Svarium\Models\TranslationOrderItem::class,
        'subscription_module' => \Upsoftware\Svarium\Models\SubscriptionModule::class,
        'subscription_limit_tier' => \Upsoftware\Svarium\Models\SubscriptionLimitTier::class,
        'system_mailbox' => \Upsoftware\Svarium\Models\SystemMailbox::class,
        'tenant' => \Upsoftware\Svarium\Models\Tenant::class,
        'tenant_profile' => \Upsoftware\Svarium\Models\TenantProfile::class,
        'tenant_subscription' => \Upsoftware\Svarium\Models\TenantSubscription::class,
        'tenant_subscription_item' => \Upsoftware\Svarium\Models\TenantSubscriptionItem::class,
        'domain' => \Upsoftware\Svarium\Models\Domain::class,
        'tenant_domain' => \Upsoftware\Svarium\Models\TenantDomain::class,
        'user' => \Upsoftware\Svarium\Models\User::class,
        'user_auth' => \Upsoftware\Svarium\Models\UserAuth::class,
        'user_auth_code' => \Upsoftware\Svarium\Models\UserAuthCode::class,
    ],
    'components' => [
        'prefix' => '',
        'select_icon' => [
            // Default collections visible in SelectIcon picker.
            'collections' => ['lucide'],
            // Optional predefined icon list (list or map by collection).
            // Example:
            // 'icons' => [
            //     'mdi' => ['account', 'cog', 'home'],
            //     'solar' => ['home-2-bold', 'user-bold'],
            // ],
            'icons' => [],
        ],
    ],
    'ui' => [
        'sidebar_user' => [
            // Enable items registered for user dropdown.
            'menu_enabled' => true,
            // Navigation key consumed by SidebarUser component.
            'menu_navigation_id' => 'sidebar_user',
            // Enable role switcher in user dropdown.
            'roles_enabled' => true,
            // Show role switch debug payload in SidebarUser dropdown (session + panel + role ids).
            'debug_role' => false,
        ],
    ],
    'modules' => [
        'builtin' => [
            // Built-in package modules toggles.
            'media' => true,
            'user' => true,
            'role' => true,
            'dictionary' => true,
            'my_profile' => true,
            'otp' => true,
            'activity_log' => true,
            'system_mailboxes' => true,
            'otp_code_logs' => true,
            'system_mail_templates' => true,
            'languages' => true,
            'translation' => true,
            'menu_manager' => true,
            'subscriptions' => true,
        ],
        'placements' => [
            // Available target values:
            // - main_menu: registers in panel navigation
            // - sidebar_user: registers in SidebarUser dropdown
            // - none: do not register menu item
            //
            // Optional values:
            // - path: submenu path (for main_menu or grouped sidebar entries)
            // - path_ids: stable submenu identifiers matching path segments (language independent)
            // - order: sort order inside target container
            // - icon: icon name override
            // - group_icon: icon for auto-generated path group node (for example "System setting")
            // - navigation_id: custom navigation key
            'my_profile' => [
                'target' => 'sidebar_user',
                'order' => 10,
                'icon' => 'lucide:user-round',
            ],
            'otp' => [
                'target' => 'sidebar_user',
                'order' => 20,
                'icon' => 'lucide:shield-check',
            ],
            'activity_log' => [
                'target' => 'sidebar_user',
                'order' => 30,
                'icon' => 'lucide:history',
            ],
            'system_mailboxes' => [
                'target' => 'main_menu',
                'path' => ['System setting'],
                'path_ids' => ['system'],
                'order' => 40,
                'icon' => '',
                'group_icon' => 'lucide:sliders',
            ],
            'otp_code_logs' => [
                'target' => 'main_menu',
                'path' => ['System setting'],
                'path_ids' => ['system'],
                'order' => 45,
                'icon' => 'lucide:key-round',
            ],
            'system_mail_templates' => [
                'target' => 'main_menu',
                'path' => ['System setting'],
                'path_ids' => ['system'],
                'order' => 50,
                'icon' => '',
                'group_icon' => 'lucide:sliders',
            ],
            'languages' => [
                'target' => 'main_menu',
                'path' => ['System setting'],
                'path_ids' => ['system'],
                'order' => 60,
                'icon' => '',
                'group_icon' => 'lucide:sliders',
            ],
            'translation' => [
                'target' => 'main_menu',
                'path' => ['System setting'],
                'path_ids' => ['system'],
                'order' => 70,
                'icon' => '',
                'group_icon' => 'lucide:sliders',
            ],
            'menu_manager' => [
                'target' => 'main_menu',
                'path' => ['System setting'],
                'path_ids' => ['system'],
                'order' => 75,
                'icon' => 'lucide:menu-square',
                'group_icon' => 'lucide:sliders',
            ],
            'subscriptions' => [
                'target' => 'main_menu',
                'path' => ['System setting'],
                'path_ids' => ['system'],
                'order' => 80,
                'icon' => '',
                'group_icon' => 'lucide:sliders',
            ],
        ],
    ],
    'logo' => [],
];
