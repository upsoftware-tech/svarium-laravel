<?php

return [
    'common' => [
        'yes' => 'Yes',
        'no' => 'No',
    ],
    'app' => [
        'install' => 'Install Svarium in an existing Laravel application',
        'init' => 'Initialize the application (adds required configuration)',
        'layout' => '(Re)configure panel layout',
        'init_ui' => [
            'app_locale_prompt' => 'Enter default application language (APP_LOCALE)',
            'installing_language' => 'Installing language files for: :locale ...',
            'switch_console_locale_prompt' => 'Switch the console interface language to selected (:locale)?',
            'app_locale_updated' => 'Updated APP_LOCALE in .env to: :locale',
            'console_locale_switched' => 'Switched console interface language to: :locale',
            'core_configuration' => 'Svarium core configuration',
            'reconfigure_prompt' => 'Detected active Svarium configuration (SVARIUM=enabled). Reconfigure again?',
            'reconfigure_cancelled' => 'Cancelled. Configuration was not changed.',
            'native_install_prompt' => 'Run native installation now (php artisan svarium:native install)?',
            'native_install_failed' => 'Command svarium:native install finished with an error.',
            'add_next_language_confirm' => 'Do you want to add a language (or another one)?',
            'enter_language_code' => 'Enter language code (e.g. pl, en, de, es)',
            'empty_language_code' => 'Language code was not provided. Try again.',
            'adding_language' => 'Adding language: :locale ...',
            'overwrite_file_prompt' => 'Overwrite file: :path',
            'file_overwritten' => 'Overwritten file: :path',
            'file_created' => 'Created file: :path',
            'colors_initialize_failed' => 'Failed to run command svarium:app.colors in initialize mode.',
            'config_flag_set' => 'Set configuration flag: SVARIUM=enabled',
            'done' => 'Done!',
        ],
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
        'migration' => 'Create a migration globally or in a selected Svarium module',
        'module' => 'Create a new Svarium module',
        'notification' => 'Create a Notification globally or in a selected Svarium module',
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
    'subscription' => [
        'install' => 'Configure subscription module and optionally run migration',
        'uninstall' => 'Disable subscription module and optionally rollback migration',
    ],
];
