<?php

return [
    'app' => [
        'init' => 'Inicjuje aplikację (dodaje niezbędną konfigurację)',
        'layout' => '(Re)konfiguracja układu panelu',
    ],
    'auth' => [
        'socials' => [
            'install' => 'Konfiguruje logowanie socialite (Google/Facebook/Apple/itd.)',
        ],
    ],
    'lang' => [
        'add' => 'Dodaj nowy język',
        'merge' => 'Łączy pliki JSON z paczki Svarium z plikami JSON głównej aplikacji.',
        'prepare' => 'Konwertuje pliki tłumaczeń PHP (messages.php) na pliki JSON (pl.json)',
        'sort' => 'Sortowanie języków',
    ],
    'make' => [
        'layout' => 'Tworzy nowy layout Svarium',
        'module' => 'Tworzy nowy moduł Svarium',
        'plugin' => 'Tworzy szablon pluginu',
        'resource' => 'Tworzy nowy zasób (Resource) Svarium',
        'tenant' => [
            'default' => 'Tworzy tenant + główną domenę (oraz dane DB w trybie database)',
            'migration' => 'Tworzy migrację tenanta w skonfigurowanym katalogu migracji tenant',
            'seeder' => 'Tworzy seeder tenanta w skonfigurowanym katalogu seederów tenant',
        ],
    ],
    'menu' => [
        'add' => 'Dodaje nowe menu',
    ],
    'panel' => [
        'add' => 'Dodaje definicję panelu do app/Svarium/panels.php',
    ],
    'permission' => 'Tworzy podstawowe ustawienia uprawnień',
    'tenant' => [
        'install' => [
            'default' => 'Konfiguruje połączenia central/tenant w config/database.php',
            'owner' => 'Włącza/wyłącza owner tenantu i opcjonalnie uruchamia migrację add_tenant_owner_columns.php',
            'profile' => 'Włącza/wyłącza profil tenantu i opcjonalnie uruchamia migrację create_tenant_profiles_table.php',
        ],
        'migrate' => 'Uruchamia migracje tenantów z użyciem wbudowanej tenancy Svarium',
        'migrate_rollback' => 'Wycofuje migracje tenantów z użyciem wbudowanej tenancy Svarium',
        'seed' => 'Uruchamia seedy tenantów z użyciem wbudowanej tenancy Svarium',
        'uninstall' => 'Wyłącza tenancy i wycofuje migracje tenant',
    ],
];
