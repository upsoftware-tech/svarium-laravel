<?php

return [
    'common' => [
        'yes' => 'Ja',
        'no' => 'Nein',
    ],
    'app' => [
        'install' => 'Installiert Svarium in einer bestehenden Laravel-Anwendung',
        'init' => 'Initialisiert die Anwendung (fügt erforderliche Konfiguration hinzu)',
        'layout' => '(Re)konfiguriert das Panel-Layout',
        'init_ui' => [
            'app_locale_prompt' => 'Gib die Standardsprache der Anwendung an (APP_LOCALE)',
            'installing_language' => 'Sprachdateien werden installiert für: :locale ...',
            'switch_console_locale_prompt' => 'Soll die Sprache der Konsolenoberfläche auf die ausgewählte Sprache (:locale) umgestellt werden?',
            'app_locale_updated' => 'APP_LOCALE in .env wurde aktualisiert auf: :locale',
            'console_locale_switched' => 'Sprache der Konsolenoberfläche wurde umgestellt auf: :locale',
            'core_configuration' => 'Grundkonfiguration von Svarium',
            'reconfigure_prompt' => 'Aktive Svarium-Konfiguration erkannt (SVARIUM=enabled). Erneut konfigurieren?',
            'reconfigure_cancelled' => 'Abgebrochen. Die Konfiguration wurde nicht geändert.',
            'native_install_prompt' => 'Native Installation jetzt ausführen (php artisan svarium:native install)?',
            'native_install_failed' => 'Der Befehl svarium:native install wurde mit einem Fehler beendet.',
            'add_next_language_confirm' => 'Möchtest du eine Sprache hinzufügen (oder eine weitere)?',
            'enter_language_code' => 'Sprachcode eingeben (z. B. pl, en, de, es)',
            'empty_language_code' => 'Kein Sprachcode angegeben. Bitte versuche es erneut.',
            'adding_language' => 'Sprache wird hinzugefügt: :locale ...',
            'overwrite_file_prompt' => 'Datei überschreiben: :path',
            'file_overwritten' => 'Datei überschrieben: :path',
            'file_created' => 'Datei erstellt: :path',
            'colors_initialize_failed' => 'Der Befehl svarium:app.colors im Modus initialize konnte nicht ausgeführt werden.',
            'config_flag_set' => 'Konfigurationsflag gesetzt: SVARIUM=enabled',
            'done' => 'Fertig!',
        ],
    ],
    'auth' => [
        'socials' => [
            'install' => 'Konfiguriert Social-Login-Anbieter (Google/Facebook/Apple/usw.)',
        ],
    ],
    'lang' => [
        'add' => 'Neue Sprache hinzufügen',
        'merge' => 'Führt JSON-Dateien aus dem Svarium-Paket mit den JSON-Dateien der Hauptanwendung zusammen.',
        'prepare' => 'Konvertiert PHP-Übersetzungsdateien (messages.php) in JSON-Dateien (pl.json)',
        'sort' => 'Sprachen sortieren',
    ],
    'make' => [
        'layout' => 'Neues Svarium-Layout erstellen',
        'module' => 'Neues Svarium-Modul erstellen',
        'plugin' => 'Plugin-Template erstellen',
        'resource' => 'Neue Svarium-Ressource erstellen',
        'tenant' => [
            'default' => 'Tenant + primäre Domain erstellen (inkl. DB-Daten im Modus database)',
            'migration' => 'Tenant-Migration im konfigurierten Tenant-Migrationsverzeichnis erstellen',
            'seeder' => 'Tenant-Seeder im konfigurierten Tenant-Seederverzeichnis erstellen',
        ],
    ],
    'menu' => [
        'add' => 'Neuen Menüeintrag hinzufügen',
    ],
    'panel' => [
        'add' => 'Panel-Definition zu app/Svarium/panels.php hinzufügen',
    ],
    'permission' => 'Grundlegende Berechtigungseinstellungen erstellen',
    'tenant' => [
        'install' => [
            'default' => 'Central/Tenant-Verbindungen in config/database.php konfigurieren',
            'owner' => 'Tenant-Owner-Verknüpfung aktivieren/deaktivieren und optional die Migration add_tenant_owner_columns.php ausführen',
            'profile' => 'Tenant-Profil aktivieren/deaktivieren und optional die Migration create_tenant_profiles_table.php ausführen',
        ],
        'migrate' => 'Tenant-Migrationen mit integrierter Svarium-Tenancy ausführen',
        'migrate_rollback' => 'Tenant-Migrationen mit integrierter Svarium-Tenancy zurückrollen',
        'seed' => 'Tenant-Datenbanken mit integrierter Svarium-Tenancy seeden',
        'uninstall' => 'Tenancy deaktivieren und Tenant-Migrationen zurückrollen',
    ],
    'subscription' => [
        'install' => 'Abonnement-Modul konfigurieren und optional Migration ausführen',
        'uninstall' => 'Abonnement-Modul deaktivieren und optional Migration zurückrollen',
    ],
];
