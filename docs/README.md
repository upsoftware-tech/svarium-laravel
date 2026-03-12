# Dokumentacja Svarium Laravel

## Instalacja

Masz obecnie dwa podstawowe warianty startu:

1. Nowa aplikacja od zera:

```bash
composer create-project upsoftware/svarium app
```

Alternatywnie przez CLI:

```bash
svarium new app
```

2. Instalacja Svarium do istniejącej aplikacji Laravel:

```bash
composer require upsoftware/svarium-laravel
php artisan svarium:app:install
```

- [Roadmap ogólny Svarium](./roadmap.md)
- [Konfiguracja `config/upsoftware.php`](./config-upsoftware.md)
- Operacje aplikacji: preferowana ścieżka `app/Svarium/Operations` (legacy: `app/Svarium/Panel/Operations`)
- [Layouty panelu](./panel-layouts.md)
- [Komponent Container (`position`, `fluid`)](./container-component.md)
- [Komponent Grid (kolumny, breakpointy, span, rows)](./grid-component.md)
- [Komponent Repeater (table/key/custom, max, searchable, empty)](./repeater-component.md)
- [Komponent Link (PHP + Vue, wrapper Inertia)](./link-component.md)
- [Komponent Logo (PHP + Vue, light/dark/default/small)](./logo-component.md)
- [Komponent Pin (PHP + Vue, OTP / verification code)](./pin-component.md)
- [Komponent InputFile (PHP + Vue, upload/autostart/progress/preview)](./input-file-component.md)
- [DropdownSearch (pełne API + kolory + pozycje ikon/liczników)](./dropdown-search.md)
- [Tabela (`TableBuilder`) - pełna dokumentacja](./table.md)
- [Taby formularza w `Resource` (`create/edit/{tab}`)](./resource-tabs.md)
- [Komendy CLI Svarium (pełna lista + przykłady)](./commands.md)
- [AuthLoginService (logowanie + OTP)](./auth-login-service.md)
- [Layouty i konfiguracja rejestracji](./register-panel-config.md)
- [Rejestracja menu (moduły i strony)](./menu-registration.md)
- [Wbudowane moduły paczki: User i Role](./built-in-modules.md)
- [UI konfiguracji i instalacji (`/svarium/install`, `/svarium/configuration`)](./ui-configuration.md)
- [Tenancy (wbudowane): konfiguracja, owner/profile, migracje, seedery, `svarium:make.tenant`, `$table->tenant_id()`](./tenancy.md)
- [Tenancy per domena: widoczność rekordów tylko na wybranych domenach](./tenancy-domain-visibility.md)
- [Roadmap tenancy: co wdrażamy później i co robimy po swojemu](./tenancy-roadmap.md)
- [Dostęp do pól zasobu (`access()`)](./resource-access.md)
- [Stopka tabeli (`Column::footer()`)](./table-footer.md)
