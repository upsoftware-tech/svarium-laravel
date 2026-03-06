<?php

return [
    'app' => [
        'init' => 'Initialize the application (adds required configuration)',
        'layout' => '(Re)configure panel layout',
    ],
    'auth' => [
        'socials' => [
            'install' => 'Configure social login providers (Google/Facebook/Apple/etc.)',
        ],
    ],
    'lang' => [
        'add' => 'Add a new language',
        'merge' => 'Merge Svarium package JSON files with application JSON files.',
        'prepare' => 'Convert PHP translation files (messages.php) to JSON files (pl.json)',
        'sort' => 'Sort languages',
    ],
    'make' => [
        'layout' => 'Create a new Svarium layout',
        'module' => 'Create a new Svarium module',
        'plugin' => 'Create a plugin scaffold',
        'resource' => 'Create a new Svarium resource',
        'tenant' => [
            'default' => 'Create tenant + primary domain (and DB data in database mode)',
            'migration' => 'Create a tenant migration in the configured tenant migrations directory',
            'seeder' => 'Create a tenant seeder in the configured tenant seeders directory',
        ],
    ],
    'menu' => [
        'add' => 'Add a new menu item',
    ],
    'panel' => [
        'add' => 'Add a panel definition to app/Svarium/panels.php',
    ],
    'permission' => 'Create base permission settings',
    'tenant' => [
        'install' => [
            'default' => 'Configure central/tenant connections in config/database.php',
            'owner' => 'Enable/disable tenant owner binding and optionally run add_tenant_owner_columns.php migration',
            'profile' => 'Enable/disable tenant profile and optionally run create_tenant_profiles_table.php migration',
        ],
        'migrate' => 'Run tenant migrations using built-in Svarium tenancy',
        'migrate_rollback' => 'Rollback tenant migrations using built-in Svarium tenancy',
        'seed' => 'Seed tenant databases using built-in Svarium tenancy',
        'uninstall' => 'Disable tenancy and rollback tenant migrations',
    ],
];
