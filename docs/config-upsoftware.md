# Konfiguracja `config/upsoftware.php`

Ten dokument opisuje kluczowe sekcje konfiguracji Svarium i ich zastosowanie.

## Gdzie jest plik

- docelowo w aplikacji: `config/upsoftware.php`
- źródło domyślne w paczce: `packages/svarium-laravel/src/config/upsoftware.php`

## Ważna zasada

W plikach `config/*.php` nie używaj `__()` i innych helperów zależnych od bootstrapa translacji.

Poprawnie:

```php
'rowsPerPageLabel' => null,
```

Niepoprawnie:

```php
'rowsPerPageLabel' => __('Rows per page'),
```

## Struktura główna

```php
return [
    'middleware' => [...],
    'api' => [...],
    'table' => [...],
    'panel' => [...],
    'auth' => [...],
    'tenancy' => [...],
    'tracking' => [...],
    'models' => [...],
    'components' => [...],
    'logo' => [...],
];
```

## `middleware`

- `middleware.web` – globalne middleware Svarium dla ścieżek web.
- `middleware.api` – globalne middleware Svarium dla API.

## `api`

- `enabled` – włącza API.
- `prefix` – prefix tras API (np. `api/v1`).
- `auth.driver` – np. `sanctum`.
- `auth.guard` – guard dla API.
- `auth.middleware` – lista middleware dla API.
- `auth.custom_handler` – opcjonalny własny handler.

## `table`

- `action_display` – domyślny tryb akcji (`inline` / `dropdown`).
- `condesed` – domyślna kondensacja tabel (`true`/`false`).
- `searchbar` – automatyczne dodawanie `InputSearch::make('q')`.
- `pagination` – pełna konfiguracja paginacji:
  - `enabled`
  - `rowsPerPageOptions`
  - `rowsPerPage`
  - `rowsPerPageLabel`
  - `rowsPerPageAllLabel`
  - `paginationLabel`
  - `showButtonLabel`
  - `showFirstLabel`
  - `showLastLabel`
  - `ellipsisAfter`
  - `firstButtonLabel`
  - `previousButtonLabel`
  - `nextButtonLabel`
  - `lastButtonLabel`

Przykład:

```php
'table' => [
    'action_display' => 'inline',
    'condesed' => false,
    'searchbar' => true,
    'pagination' => [
        'enabled' => true,
        'rowsPerPageOptions' => [10, 20, 50, 100, 0],
        'rowsPerPage' => 20,
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
],
```

## `panel`

- `enabled` – czy panel Svarium jest aktywny.
- `name` – nazwa domyślnego panelu.
- `route_prefix` – prefix nazw tras auth (np. `app.auth`).
- `prefix` – prefix URL panelu (`''` oznacza `noPrefix()`).
- `root_layout` – root layout (np. `CleanLayout`).
- `definition_layout_types` – layouty traktowane jako definicyjne.
- `public_auth_route_patterns` – route names dostępne bez auth middleware.
- `public_auth_path_patterns` – ścieżki dostępne bez auth middleware.

## `auth`

### `auth.register`

- `enabled` – włącza/wyłącza rejestrację.
- `auto_login` – auto logowanie po rejestracji.
- `layout` – layout strony rejestracji.
- `redirect_to` / `redirect_route` / `login_redirect_route` – przekierowania.
- `success_message` – komunikat sukcesu.
- `creator` / `after_create` – hooki backendowe.
- `schema` – niestandardowy schema formularza.
- `activation` – tryb aktywacji (`none`, `email_code`, `email_link`, ...).
- `events` – dispatch/listenery po rejestracji.
- `password_rules` – reguły walidacji hasła.
- `fields` – lista pól formularza.

### `auth.otp`

- `enabled` – globalny toggle OTP.
- `methods` – dozwolone metody (`email`, `sms`, `app`).
- `token_ttl_minutes` – TTL tokenu sesji OTP.
- `code_ttl_minutes` – TTL kodu OTP.
- `code_length` – długość kodu.
- `code_pattern` – `digits` / `chars` / `digits_and_chars`.
- `invalidate_previous_codes` – czy nowy kod unieważnia poprzednie.
- `resend_seconds` – cooldown resend.
- `resend_limit.max_attempts` + `resend_limit.decay_minutes` – rate limit resend.
- `verification.max_failed_attempts` + `verification.lock_minutes` – blokada po błędnych próbach.
- `show_all_methods` – pokazuj wszystkie metody vs tylko aktywne.
- `allow_user_disable` – czy user może wyłączyć OTP.
- `default_enabled` – domyślny status OTP dla usera.
- `rate_limit_store` – store cache pod limiter OTP.

## `tenancy`

- `enabled` – włączenie tenancy.
- `mode` – `column` lub `database`.
- `paths.tenant_migrations` / `paths.tenant_seeders` – ścieżki usera.
- `seeders.namespace` – namespace seederów tenant.

### `tenancy.domains`

- `enabled` – rozpoznawanie tenanta po domenie.
- `central_domains` – domeny centralne.
- `seo.canonical_on_primary` – canonical na domenie primary.
- `seo.noindex_aliases` – noindex dla aliasów.

### `tenancy.owner`

- `enabled` – powiązanie tenanta z właścicielem biznesowym.
- `type_column` / `id_column` – kolumny polymorphic.
- `map` – alias => klasa modelu.

### `tenancy.profile`

- `enabled` – tabela profilu tenanta.
- `table` / `foreign_key` / `model` – konfiguracja modelu profilu.

### `tenancy.database`

- `central_connection` – nazwa połączenia centralnego.
- `tenant_connection` – nazwa połączenia tenant runtime.
- `template_connection` – bazowe połączenie do klonowania ustawień.

### `tenancy.column`

- `column` – kolumna tenant partition (np. `tenant_id`).
- `strict` – czy bez kontekstu tenant zwracać pusty wynik.
- `model_maps.tenants` – mapa tabeli `model_has_tenants`.
- `model_maps.domains` – mapa tabeli `model_has_domains`.

## `tracking`

- `enabled` – włącza device tracking.
- `user_model` – opcjonalny model usera.
- `detect_on_login` – wykrywanie urządzenia przy logowaniu.
- `geoip_provider` – dostawca geolokalizacji.
- `device_cookie` / `cookie_http_only` / `session_key` – ustawienia sesji/cookie.
- `hijacking_detector` – klasa detektora przejęcia sesji.

## `models`

Mapa klas modeli używanych przez Svarium.  
Klucze wskazują, jaką klasę modelu runtime ma użyć system.

Przykład:

```php
'models' => [
    'user' => \Upsoftware\Svarium\Models\User::class,
    'role' => \Upsoftware\Svarium\Models\Role::class,
    'tenant' => \Upsoftware\Svarium\Models\Tenant::class,
],
```

## `components`

- `prefix` – prefix używany w integracji frontu komponentów.

## `logo`

Konfiguracja ścieżek logo (np. `default.light`, `default.dark`, `small.light`, `small.dark`).

Przykład:

```php
'logo' => [
    'default' => [
        'light' => 'image/logo_horizontal.png',
        'dark' => 'image/logo_horizontal_dark.png',
    ],
    'small' => [
        'light' => 'image/logo_small.png',
        'dark' => 'image/logo_small_dark.png',
    ],
],
```

## Najczęściej używane zmienne ENV

- `SVARIUM_API_DRIVER`
- `SVARIUM_PANEL_NAME`
- `SVARIUM_OTP_RATE_LIMIT_STORE`
- `SVARIUM_TENANCY_ENABLED`
- `SVARIUM_TENANCY_MODE`
- `SVARIUM_TENANCY_DOMAINS_ENABLED`
- `SVARIUM_TENANCY_CENTRAL_CONNECTION`
- `SVARIUM_TENANCY_TENANT_CONNECTION`
- `SVARIUM_TENANCY_TEMPLATE_CONNECTION`

