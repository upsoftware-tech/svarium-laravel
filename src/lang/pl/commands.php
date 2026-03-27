<?php

return [
    'common' => [
        'yes' => 'Tak',
        'no' => 'Nie',
    ],
    'app' => [
        'install' => 'Instaluje Svarium w istniejącej aplikacji Laravel',
        'init' => 'Inicjuje aplikację (dodaje niezbędną konfigurację)',
        'layout' => '(Re)konfiguracja układu panelu',
        'init_ui' => [
            'app_locale_prompt' => 'Podaj domyślny język aplikacji (APP_LOCALE)',
            'installing_language' => 'Instalowanie plików językowych dla: :locale ...',
            'switch_console_locale_prompt' => 'Czy przełączyć język interfejsu konsoli na wskazany (:locale)?',
            'app_locale_updated' => 'Zaktualizowano APP_LOCALE w pliku .env na: :locale',
            'console_locale_switched' => 'Przełączono język interfejsu konsoli na: :locale',
            'core_configuration' => 'Konfiguracja podstawowa Svarium',
            'reconfigure_prompt' => 'Wykryto aktywną konfigurację Svarium (SVARIUM=enabled). Czy chcesz ponownie rekonfigurować?',
            'reconfigure_cancelled' => 'Przerwano. Konfiguracja nie została zmieniona.',
            'native_install_prompt' => 'Czy uruchomić instalację natywną (php artisan svarium:native install)?',
            'native_install_failed' => 'Komenda svarium:native install zakończyła się błędem.',
            'add_next_language_confirm' => 'Czy chcesz dodać język (lub kolejny)?',
            'enter_language_code' => 'Wpisz kod języka (np. pl, en, de, es)',
            'empty_language_code' => 'Nie podano kodu języka. Spróbuj ponownie.',
            'adding_language' => 'Dodawanie języka: :locale ...',
            'overwrite_file_prompt' => 'Czy nadpisać plik: :path',
            'file_overwritten' => 'Nadpisany plik: :path',
            'file_created' => 'Utworzono plik: :path',
            'colors_initialize_failed' => 'Nie udało się uruchomić komendy svarium:app.colors w trybie initialize.',
            'config_flag_set' => 'Ustawiono flagę konfiguracji: SVARIUM=enabled',
            'done' => 'Gotowe!',
        ],
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
        'migration' => 'Tworzy migrację globalnie lub w wybranym module Svarium',
        'module' => 'Tworzy nowy moduł Svarium',
        'notification' => 'Tworzy nową Notification globalnie lub w wybranym module Svarium',
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
    'subscription' => [
        'install' => 'Konfiguruje moduł subskrypcji i opcjonalnie uruchamia migrację',
        'uninstall' => 'Wyłącza moduł subskrypcji i opcjonalnie wycofuje migrację',
    ],
];
