<?php

return [
    'app' => [
        'init' => 'Initialisiert die Anwendung (fügt erforderliche Konfiguration hinzu)',
        'layout' => '(Re)konfiguriert das Panel-Layout',
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
];
