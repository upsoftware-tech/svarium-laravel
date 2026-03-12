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
    'resource' => [...],
    'colors' => [...],
    'panel' => [...],
    'auth' => [...],
    'tenancy' => [...],
    'tracking' => [...],
    'models' => [...],
    'components' => [...],
    'ui' => [...],
    'modules' => [...],
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
- `condensed` – domyślna kondensacja tabel (`true`/`false`).
- `searchbar` – automatyczne dodawanie `InputSearch::make('q')`.
- `exported` – domyślna dostępność eksportu:
  - `true` - eksport włączony (wszystkie formaty),
  - `false` - eksport wyłączony,
  - `['sql', 'csv']` - tylko wskazane formaty.
  - Dostępne formaty: `csv`, `tsv`, `xlsx`, `xls`, `ods`, `json`, `xml`, `sql`, `pdf`.
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
    'condensed' => false,
    'searchbar' => true,
    'exported' => true,
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

## `colors`

Domyślne ustawienia kolorów dla komendy `php artisan svarium:app.colors`.

- `css_file` – domyślna ścieżka pliku CSS.
- `tone` – domyślna tonacja neutralna (`slate|gray|zinc|neutral|stone|taupe|mauve|mist|olive`).
- `primary.light.color` / `primary.light.shade` – domyślny `PRIMARY` dla `:root`.
- `primary.dark.color` / `primary.dark.shade` – domyślny `PRIMARY` dla `.dark`.

Przykład:

```php
'colors' => [
    'css_file' => 'resources/css/app.css',
    'tone' => 'zinc',
    'primary' => [
        'light' => [
            'color' => 'amber',
            'shade' => 500,
        ],
        'dark' => [
            'color' => 'amber',
            'shade' => 600,
        ],
    ],
],
```

## `resource`

- `form_tab_layout` – klasa layoutu używana do renderowania contentu zakładki formularza w `Resource`.
- `form.tab.position` – domyślna pozycja tabów formularza (`top`, `right`, `bottom`, `left`).
- `form.tab.variant` – domyślny wariant tabów formularza (`default`, `simple`).
- `form.tab.title` – czy pokazywać automatyczny tytuł nad tabami.
- `form.language.display` – domyślny sposób wyświetlania wyboru języków w formularzach (`inline`, `select`).
- `form.language.multiple` – czy wybór języków ma być wielokrotnego wyboru (`Select` i `LocaleInline`).
- `form.language.showIcon` – czy pokazywać flagę/ikonę w `LocaleInline`.
- `form.language.showLabel` – czy pokazywać etykietę języka w `LocaleInline`.

Domyślnie:

```php
'resource' => [
    'form_tab_layout' => \Upsoftware\Svarium\Layouts\Panel\FormTabLayout::class,
    'form' => [
        'tab' => [
            'position' => 'top',
            'variant' => 'default',
            'title' => true,
        ],
        'language' => [
            'display' => 'inline',
            'multiple' => false,
            'showIcon' => false,
            'showLabel' => true,
        ],
    ],
],
```

Jeśli chcesz podmienić globalnie wygląd wrappera dla tabów formularza, ustaw tutaj własną klasę:

```php
'resource' => [
    'form_tab_layout' => \App\Svarium\Layouts\Panel\CustomFormTabLayout::class,
],
```

Klasa powinna implementować ten sam kontrakt renderowania co layout sekcyjny i zwracać `Component|array|null` z metody `build()`.

Przykład zmiany globalnego sposobu wyboru języków:

```php
'resource' => [
    'form' => [
        'language' => [
            'display' => 'select',
            'multiple' => true,
            'showIcon' => true,
            'showLabel' => true,
        ],
    ],
],
```

Per resource możesz to nadpisać przez:

```php
public function formConfig(PanelContext $context, ?Model $record = null): array
{
    return [
        'tab' => [
            'position' => 'left',
            'variant' => 'simple',
            'title' => false,
        ],
        'language' => [
            'display' => 'select',
            'multiple' => false,
            'showIcon' => false,
            'showLabel' => true,
        ],
    ];
}
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

## `ui`

### `ui.sidebar_user`

- `menu_enabled` – czy dropdown `SidebarUser` ma renderować wpisy z runtime menu.
- `menu_navigation_id` – klucz nawigacji, z której czytane są wpisy do dropdownu (np. `sidebar_user`).

Przykład:

```php
'ui' => [
    'sidebar_user' => [
        'menu_enabled' => true,
        'menu_navigation_id' => 'sidebar_user',
    ],
],
```

## `modules`

### `modules.builtin`

Przełączniki modułów wbudowanych paczki (true/false), np.:
- `user`
- `role`
- `my_profile`
- `otp`
- `activity_log`
- `system_mailboxes`
- `system_mail_templates`
- `languages`
- `translation`

### `modules.placements`

Konfiguruje gdzie ma się zarejestrować wpis menu modułu:

- `target`:
  - `main_menu` – główna nawigacja panelu,
  - `sidebar_user` – dropdown komponentu `SidebarUser`,
  - `none` – brak wpisu.
- `path` – etykietowa ścieżka grup (UI).
- `path_ids` – techniczne ID ścieżki (stabilne, niezależne od tłumaczeń).
- `order` – kolejność.
- `icon` – ikonka.
- `navigation_id` – opcjonalny klucz nawigacji.

Przykład:

```php
'modules' => [
    'placements' => [
        'my_profile' => [
            'target' => 'sidebar_user',
            'order' => 10,
            'icon' => 'lucide:user-round',
        ],
        'system_mail_templates' => [
            'target' => 'main_menu',
            'path' => ['Ustawienia'],
            'path_ids' => ['settings'],
            'order' => 50,
            'icon' => 'lucide:mail-open',
        ],
        'languages' => [
            'target' => 'main_menu',
            'path' => ['Ustawienia'],
            'path_ids' => ['settings'],
            'order' => 60,
            'icon' => 'lucide:languages',
        ],
        'translation' => [
            'target' => 'main_menu',
            'path' => ['Ustawienia'],
            'path_ids' => ['settings'],
            'order' => 70,
            'icon' => 'lucide:book-text',
        ],
    ],
],
```

## `lang`

- `key_locale` – locale używany do rozwiązywania kluczy tłumaczeń w atrybutach pól (np. gdy w `attributes.php` jest `__('First Name')`).
- Praktycznie: backend przekaże klucz (np. `First Name`), a frontend przetłumaczy go dynamicznie po zmianie języka.

## `auth`

- `tenant_bypass_role_keys` – lista `role_key`, które omijają sprawdzanie kontekstu tenant podczas logowania (tryb `tenancy=column`), np. `['superadmin', 'admin']`.
- `tenant_bypass_scope` – zakres działania bypass:
  - `all_tenants` – rola z `tenant_bypass_role_keys` może zalogować się na każdej domenie tenantowej.
  - `tenant` – rola z `tenant_bypass_role_keys` musi być przypisana do bieżącego tenanta (lub jako globalna rola z `tenant_id = null`).

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
- `SVARIUM_LANG_KEY_LOCALE`
