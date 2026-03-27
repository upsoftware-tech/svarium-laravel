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
    'debug' => [...],
    'api' => [...],
    'table' => [...],
    'form' => [...],
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

## `debug`

- `form` – włącza debug formularzy w UI:
  - store pól formularza,
  - błędy walidacji,
  - debug `showWhen(...)` / `visibleWhen(...)`.

Przykład:

```php
'debug' => [
    'form' => false,
],
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

Uwaga: `config('upsoftware.api.*')` ustawia globalne zachowanie API, ale endpointy CRUD dla modułów są włączane na poziomie resource (`Resource::api()`).
Szczegóły: [CRUD API dla `Resource` + ReDoc/OpenAPI](./resource-api.md).

### `api.docs` (ReDoc + OpenAPI)

- `api.docs.enabled` – włącza endpointy dokumentacji.
- `api.docs.path` – URL strony ReDoc (domyślnie `api/docs`).
- `api.docs.spec_path` – URL JSON specyfikacji (domyślnie `api/openapi.json`).
- `api.docs.public` – czy endpointy docs/spec są publiczne.
- `api.docs.middleware` – dodatkowe middleware dla docs/spec.
- `api.docs.auto_generate` – automatyczna regeneracja spec przy wejściu na docs/spec.
- `api.docs.storage_path` – ścieżka pliku spec (abs lub rel do `base_path`).
- `api.docs.title` – opcjonalny tytuł dokumentacji.
- `api.docs.version` – opcjonalna wersja API w OpenAPI.
- `api.docs.server_url` – opcjonalny `servers[0].url` w spec.
- `api.docs.tag_groups` – opcjonalne grupowanie tagów dla ReDoc (`x-tagGroups`), np.:

```php
'api' => [
    'docs' => [
        'tag_groups' => [
            ['name' => 'MSIG API V2', 'tags' => ['Ogłoszenia', 'Pacjenci']],
        ],
    ],
],
```

- `api.docs.tag_groups_include_ungrouped` – gdy `true`, tagi bez jawnej grupy trafiają do grupy fallback (domyślnie `false`).
- `api.docs.ungrouped_tag_group_name` – nazwa grupy fallback dla nieprzypisanych tagów (domyślnie `Other`).

Przykład: bez etykiety fallback dla nieprzypisanych tagów + jedna grupa „Ustawienia”:

```php
'api' => [
    'docs' => [
        'tag_groups_include_ungrouped' => false,
        'tag_groups' => [
            ['name' => 'Ustawienia', 'tags' => ['System mailbox', 'System mail templates']],
        ],
    ],
],
```

Przykład:

```php
'api' => [
    'enabled' => true,
    'prefix' => 'api/v1',
    'auth' => [
        'driver' => 'sanctum',
        'guard' => 'sanctum',
        'middleware' => ['auth:sanctum'],
        'custom_handler' => null,
    ],
    'docs' => [
        'enabled' => true,
        'path' => 'api/docs',
        'spec_path' => 'api/openapi.json',
        'public' => true,
        'middleware' => [],
        'auto_generate' => true,
        'storage_path' => 'storage/app/svarium/openapi.json',
        'title' => null,
        'version' => null,
        'server_url' => null,
    ],
],
```

## `table`

- `action_display` – domyślny tryb akcji (`inline` / `dropdown`).
- `condensed` – domyślna kondensacja tabel (`true`/`false`).
- `bordered` – domyślnie dodaje pionowe linie między komórkami.
- `searchbar` – automatyczne dodawanie `InputSearch::make('q')`.
- `selectable` – globalnie włącza/wyłącza zaznaczanie wierszy i komórek.
- `sortable` – domyślne sortowanie kolumn:
  - `false` – domyślnie brak sortowania,
  - `true` – wszystkie kolumny domyślnie sortowalne,
  - `['name', 'created_at']` – sortowalne tylko wskazane kolumny.
- `multi_sortable` – domyślne multi-sortowanie:
  - `false` – brak multi-sortowania,
  - `true` – każda sortowalna kolumna może być dodana jako kolejna (`CTRL/CMD + klik`),
  - `['name', 'created_at']` – multi-sort tylko dla wskazanych kolumn.
- `column_visibility` – automatycznie pokazuje przycisk `ColumnVisibility` w nagłówku tabeli.
- `create_action` – automatycznie pokazuje `Action::create()` w nagłówku tabeli.
- `views_addable` – pozwala użytkownikowi zapisywać własne widoki tabel.
- `custom_columns` – globalna widoczność przycisku/dialogu „Custom columns”.
- `exported` – domyślna dostępność eksportu:
  - `true` - eksport włączony (wszystkie formaty),
  - `false` - eksport wyłączony,
  - `['sql', 'csv']` - tylko wskazane formaty.
  - Dostępne formaty: `csv`, `tsv`, `xlsx`, `xls`, `ods`, `json`, `xml`, `sql`, `pdf`.
- `imported` – domyślna dostępność importu.
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
    'bordered' => false,
    'searchbar' => true,
    'selectable' => true,
    'sortable' => false,
    'multi_sortable' => false,
    'column_visibility' => false,
    'create_action' => false,
    'views_addable' => true,
    'custom_columns' => true,
    'exported' => true,
    'imported' => true,
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

## `form`

- `required_indicator.enabled` - globalnie włącza/wyłącza oznaczenie pól wymaganych.
- `required_indicator.label` - sposób oznaczenia pola:
  - `false` - klasyczna gwiazdka `*`,
  - `true` - tekst `required`,
  - `'wymagane'` - własny tekst.
- `required_indicator.position` - pozycja znacznika:
  - `left` - przy etykiecie, np. `Imię *`,
  - `right` - po prawej stronie tego samego wiersza etykiety.

Przykład:

```php
'form' => [
    'required_indicator' => [
        'enabled' => true,
        'label' => false,
        'position' => 'left',
    ],
],
```

Per formularz możesz to nadpisać przez:

```php
Form::make()
    ->requiredIndicator(true)
    ->requiredIndicatorLabel('wymagane')
    ->requiredIndicatorPosition('right');
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
- `form.tab.card` – czy aktywny tab ma być domyślnie renderowany w wrapperze karty.
- `form.tab.defaults` – domyślne parametry layoutu contentu tabów formularza:
  - `content` (aliasy: `contentCols`, `cols`) – wewnętrzna liczba kolumn contentu.
  - `colSpan` (aliasy: `colspan`, `span`) – szerokość wrappera taba w siatce nadrzędnej.
  - `grid` (aliasy: `gridColumns`) – liczba kolumn siatki nadrzędnej wrappera taba.
  - `widthContent` (alias: `width`) – `max-width` contentu taba.
  - `paddingContent` (alias: `padding`) – padding contentu taba.
  - `fieldColSpan` (alias: `field_col_span`) – domyślny `colSpan` dla pól (`FieldComponent`) bez jawnie ustawionego `colSpan`.
- `form.tab.validation_error_icon.enabled` – pokazuje ikonę błędu na zakładce z błędami walidacji.
- `form.tab.validation_error_icon.icon` – ikona błędu zakładki (np. `lucide:circle-alert`).
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
            'card' => true,
            'defaults' => [],
            'validation_error_icon' => [
                'enabled' => false,
                'icon' => 'lucide:circle-alert',
            ],
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
            'card' => true,
            'defaults' => [
                'content' => 12,
                'colSpan' => 'full',
                'grid' => 12,
                'widthContent' => '72rem',
                'paddingContent' => '4',
                'fieldColSpan' => '1/2',
            ],
            'validation_error_icon' => [
                'enabled' => true,
                'icon' => 'lucide:circle-alert',
            ],
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

Lub wprost w `Resource`:

```php
public function formTabDefaults(PanelContext $context, ?Model $record = null): array
{
    return [
        'content' => 12,
        'colSpan' => 'full',
        'grid' => 12,
        'widthContent' => '72rem',
        'paddingContent' => '4',
        'fieldColSpan' => '1/2',
    ];
}
```

Priorytet jest taki: `config` -> `formTabDefaults()` -> ustawienia pojedynczego taba.

## `panel`

- `enabled` – czy panel Svarium jest aktywny.
- `name` – nazwa domyślnego panelu.
- `route_prefix` – prefix nazw tras auth (np. `app.auth`).
- `prefix` – prefix URL panelu (`''` oznacza `noPrefix()`).
- `container.enabled` – czy `PanelLayout` ma automatycznie owijać body/content komponentem `Container`.
- `container.fluid` – czy automatyczny `Container` ma być pełnej szerokości (`w-full max-w-none`).
- `container.position` – pozycjonowanie automatycznego `Container` (`left`, `center`, `right`).
- automatyczny `Container` renderuje klasy `container app__container`.
- `root_layout` – root layout (np. `CleanLayout`).
- `definition_layout_types` – layouty traktowane jako definicyjne.
- `public_auth_route_patterns` – route names dostępne bez auth middleware.
- `public_auth_path_patterns` – ścieżki dostępne bez auth middleware.

Przykład:

```php
'panel' => [
    'enabled' => true,
    'name' => 'admin',
    'prefix' => '',
    'container' => [
        'enabled' => true,
        'fluid' => false,
        'position' => 'center',
    ],
    'root_layout' => 'CleanLayout',
],
```

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

- `tenant_bypass_role_keys` – lista kluczy bypass dla logowania w trybie `tenancy=column`.
  Dopasowanie działa po `role_key`, `name`, `name_locale`, `id`, `id:{id}`.
  Przykład: `['superadmin', 'admin']`.
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
- `select_icon.collections` – domyślne kolekcje widoczne w pickerze `SelectIcon`.
- `select_icon.icons` – opcjonalna domyślna lista ikon dla `SelectIcon`:
  - lista pełnych nazw (`lucide:user`, `mdi:account`),
  - albo mapa kolekcji (`'mdi' => ['account', 'cog']`).

Przykład:

```php
'components' => [
    'prefix' => '',
    'select_icon' => [
        'collections' => ['lucide', 'solar'],
        'icons' => [
            'solar' => ['home-2-bold', 'user-bold'],
            'mdi' => ['account', 'cog'],
        ],
    ],
],
```

Per pole formularza możesz nadpisać te ustawienia przez:

```php
SelectIcon::make('icon')
    ->collections(['carbon', 'solar'])
    ->icons(['carbon:add-alt', 'solar:home-2-bold']);
```

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
