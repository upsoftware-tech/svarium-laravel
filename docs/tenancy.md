# Tenancy (wbudowane)

Svarium ma wbudowany mechanizm tenancy i nie wymaga `stancl/tenancy`.

## Szybki start

1. Usuń starą zależność (w paczce Svarium została już usunięta):
   - `stancl/tenancy` nie jest już potrzebne.
2. Zaktualizuj zależności:
   - `composer update`
3. Uruchom migracje:
   - `php artisan migrate`
4. Włącz tenancy w `config/upsoftware.php`:
   - ustaw `tenancy.enabled` na `true`
   - ustaw `tenancy.mode` na `column` albo `database`

## Jak to działa w runtime

- Gdy tenancy jest włączone, trasy Svarium korzystają z middleware tenancy.
- Middleware rozpoznaje tenant po domenie/hoście żądania.
- Rozpoznany tenant jest dostępny przez helper `tenant()`.
- Działanie zależy od trybu:
  - `column`: dane są filtrowane po kluczu tenant (`tenant_id` domyślnie).
  - `database`: połączenie DB jest przełączane na bazę tenanta na czas requestu.

## Konfiguracja

Skonfiguruj w `config/upsoftware.php`:

```php
'tenancy' => [
    'enabled' => env('SVARIUM_TENANCY_ENABLED', false),
    'mode' => env('SVARIUM_TENANCY_MODE', 'column'), // column | database
    'paths' => [
        'migrations' => app_path('Svarium/Tenancy/Migrations'),
        'seeders' => app_path('Svarium/Tenancy/Seeders'),
    ],
    'seeders' => [
        'namespace' => 'App\\Svarium\\Tenancy\\Seeders',
    ],
    'domains' => [
        'enabled' => env('SVARIUM_TENANCY_DOMAINS_ENABLED', true),
        'central_domains' => ['app.example.com'],
    ],
    'owner' => [
        'enabled' => false,
        'type_column' => 'owner_type',
        'id_column' => 'owner_id',
        'map' => [
            'customer' => App\Models\Customer::class,
            'company' => App\Models\UserCompany::class,
        ],
    ],
    'profile' => [
        'enabled' => true,
        'table' => 'tenant_profiles',
        'foreign_key' => 'tenant_id',
        'model' => \Upsoftware\Svarium\Models\TenantProfile::class,
    ],
    'database' => [
        'central_connection' => env('SVARIUM_TENANCY_CENTRAL_CONNECTION', 'central'),
        'tenant_connection' => env('SVARIUM_TENANCY_TENANT_CONNECTION', 'tenant'),
        'template_connection' => env('SVARIUM_TENANCY_TEMPLATE_CONNECTION', env('DB_CONNECTION', 'mysql')),
    ],
    'column' => [
        'column' => 'tenant_id',
        'strict' => false,
    ],
],
```

## Opis kluczy konfiguracji

- `tenancy.enabled`: globalnie włącza/wyłącza tenancy.
- `tenancy.mode`:
  - `column`: jedna współdzielona baza, podział przez klucz tenanta.
  - `database`: osobna baza dla każdego tenanta.
- `tenancy.paths.migrations`: folder (lub foldery) używany przez `svarium:tenant.migrate`.
- `tenancy.paths.seeders`: folder używany przez `svarium:tenant.seed` i `svarium:make.seeder`.
- `tenancy.paths.tenant_migrations`: preferowany folder migracji tenant użytkownika (legacy: `tenancy.paths.migrations`).
- `tenancy.paths.tenant_seeders`: preferowany folder seederów tenant użytkownika (legacy: `tenancy.paths.seeders`).
- `tenancy.seeders.namespace`: bazowy namespace klas seederów tenant.
- `tenancy.domains.enabled`: włącza/wyłącza rozpoznawanie tenantów po host/domenie.
- `tenancy.domains.central_domains`: domeny, które nie powinny rozpoznawać tenanta.
- `tenancy.owner.enabled`: włącza mapowanie tenanta na encję biznesową (`owner_type`/`owner_id`).
- `tenancy.owner.type_column`: kolumna typu właściciela w tabeli `tenants`.
- `tenancy.owner.id_column`: kolumna identyfikatora właściciela w tabeli `tenants`.
- `tenancy.owner.map`: mapowanie aliasu na klasę modelu, np. `customer => App\Models\Customer`.
- `tenancy.profile.enabled`: włącza tabelę rozszerzającą dane tenanta.
- `tenancy.profile.table`: nazwa tabeli profilu tenanta (domyślnie `tenant_profiles`).
- `tenancy.profile.foreign_key`: kolumna FK do `tenants.id` w tabeli profilu.
- `tenancy.profile.model`: model obsługujący tabelę profilu.
- `tenancy.database.central_connection`: połączenie centralne (dane wspólne).
- `tenancy.database.tenant_connection`: nazwa runtime connection dla trybu `database`.
- `tenancy.database.template_connection`: połączenie bazowe kopiowane przed podmianą host/db/user/pass.
- `tenancy.column.column`: nazwa kolumny tenant (domyślnie `tenant_id`).
- `tenancy.column.strict`: gdy `true` i tenant nie jest rozpoznany, modele tenantowe zwracają pusty wynik.

## Tryb `column` (`tenant_id`)

Używaj tenant scope tylko w modelach, które mają być partycjonowane per tenant.

```php
use Upsoftware\Svarium\Tenancy\Concerns\BelongsToTenant;

class Invoice extends Model
{
    use BelongsToTenant;
}
```

Trait `BelongsToTenant`:
- dodaje globalny scope tenanta dla odczytu,
- automatycznie ustawia klucz tenant przy zapisie,
- pozwala zmienić kolumnę tenant przez właściwość modelu:

```php
protected string $tenantColumn = 'tenant_id';
```

Jeśli chcesz ograniczać rekordy do wybranych domen jednego tenanta, zobacz:
- [Tenancy per domena](./tenancy-domain-visibility.md)

## Tryb `database` (osobna baza per tenant)

Tenant jest rozpoznawany po domenie, a następnie Svarium przełącza połączenie na `tenancy.database.tenant_connection`.

Dane dostępowe do bazy tenanta są brane z pól rekordu tenanta:
- `env` (`local|prod|development`)
- `tenancy_db_host`
- `tenancy_db_port`
- `tenancy_db_name`
- `tenancy_db_username`
- `tenancy_db_password`

## Modele tenant i domen

Wbudowane modele:
- `Upsoftware\Svarium\Models\Tenant`
- `Upsoftware\Svarium\Models\Domain` (alias legacy: `TenantDomain`)

Domyślne tabele:
- `tenants`
- `tenant_domains` gdy `tenancy.enabled=true`
- `domains` gdy `tenancy.enabled=false`
- `model_has_tenants` (relacja wiele-do-wielu: model ↔ tenant)
- `model_has_domains` (relacja per domena: model ↔ domena)

Tworzone przez migracje tenancy (ładowane tylko gdy `upsoftware.tenancy.enabled=true`):
- `database/migrations/tenancy/2030_02_02_000001_create_tenants_table.php`
- `database/migrations/tenancy/2030_02_02_000002_create_tenant_domains_table.php`
- `database/migrations/tenancy/2030_02_02_000003_create_model_has_tenants_table.php`
- `database/migrations/tenancy/2030_02_02_000004_create_model_has_domain_tenants_table.php`
- `database/migrations/tenancy/2030_02_03_000005_rename_tenant_domain_tables.php` (upgrade do nowych nazw)
- `database/migrations/tenancy/2030_02_03_000006_add_domain_context_columns.php`
- `database/migrations/tenancy/2030_02_03_000007_add_tenant_owner_columns.php`
- `database/migrations/tenancy/2030_02_03_000008_create_tenant_profiles_table.php`

Kompatybilność:
- stare nazwy (`tenant_domains`, `model_has_domain_tenants`) są nadal wspierane przez warstwę kompatybilności,
- po migracji upgrade zalecane jest używanie nowych nazw (`domains`, `model_has_domains`).

Tabela `model_has_tenants`:
- `tenant_id`
- `model_type` (np. `App\Models\Product`)
- `model_id`

Pozwala przypisać jeden rekord do wielu tenantów bez trzymania wyłącznie jednego `tenant_id` w rekordzie.

Tabela `model_has_domains`:
- `domain_id` (FK do `domains.id`)
- `model_type`
- `model_id`

Pozwala ograniczyć widoczność rekordu do konkretnych domen w ramach jednego tenanta.

## Scope tenant przez pivoty

Trait `Upsoftware\Svarium\Tenancy\Concerns\BelongsToTenant` wspiera teraz dwa mechanizmy jednocześnie:
- klasyczny `tenant_id` w tabeli modelu,
- mapowanie wiele-do-wielu przez `model_has_tenants`,
- mapowanie per domena przez `model_has_domains`.

Jeśli model ma kolumnę `tenant_id` i wpisy w pivotach, scope dopuści rekord gdy spełniony jest co najmniej jeden warunek tenantowy.
Gdy rekord ma wpisy w `model_has_domains`, zostanie dodatkowo ograniczony do aktualnej domeny tenanta.

Dostępne metody w modelu:
- `$model->tenants()` – relacja morphToMany,
- `$model->attachTenant('tenant_xxx')`,
- `$model->syncTenants(['tenant_a', 'tenant_b'])`.
- `$model->attachTenantDomain($domainId)`,
- `$model->syncTenantDomains([1, 2, 5])`.

Przykład: 1 tenant ma 16 domen, apartament ma być widoczny tylko na 4 domenach:

```php
$apartment->attachTenant('tenant_01');
$apartment->syncTenantDomains([3, 7, 11, 15]);
```

Konfiguracja:

```php
'tenancy' => [
    'column' => [
        'column' => 'tenant_id',
        'model_maps' => [
            'tenants' => [
                'enabled' => true,
                'table' => 'model_has_tenants',
            ],
            'domains' => [
                'enabled' => true,
                'table' => 'model_has_domains',
                'domain_key' => 'domain_id',
            ],
        ],
    ],
],
```

## Przykładowa konfiguracja danych

Tworzenie tenanta i domeny:

```php
use Upsoftware\Svarium\Models\Tenant;
use Upsoftware\Svarium\Models\Domain;

$tenant = Tenant::create([
    'name' => 'ACME',
    'slug' => 'acme',
]);

Domain::create([
    'tenant_id' => $tenant->id,
    'domain' => 'acme.example.test',
    'is_primary' => true,
]);
```

## Helpery

Dostępne helpery runtime:
- `tenant($key = null, $default = null)`
- `tenant_domain($key = null, $default = null)`
- `tenant_owner($key = null, $default = null)`
- `svarium_tenancy_enabled()`
- `svarium_tenancy_mode()`
- `svarium_tenancy_column_mode()`
- `svarium_tenancy_database_mode()`
- `central_connection()`

Przykład:

```php
$tenant = tenant();
$tenantId = tenant('id');
$owner = tenant_owner();
$ownerEmail = tenant_owner('email');

if (svarium_tenancy_column_mode()) {
    // logika tenant_id dla konkretnych operacji
}
```

## Powiązanie tenanta z tabelą biznesową

Masz dwa wygodne warianty:

1. Encja biznesowa jako właściciel tenanta (np. `customers`, `user_companies`):
   - ustawiasz `tenants.owner_type` + `tenants.owner_id`,
   - konfigurujesz mapę `tenancy.owner.map`,
   - pobierasz właściciela przez `tenant_owner()`.
2. Dodatkowy profil tenanta:
   - trzymasz rozszerzone dane w tabeli `tenant_profiles` (lub własnej),
   - relacja `Tenant::profile()` daje 1:1 do danych konfiguracyjnych.

Przykład mapowania:

```php
'tenancy' => [
    'owner' => [
        'enabled' => true,
        'map' => [
            'customer' => App\Models\Customer::class,
            'company' => App\Models\UserCompany::class,
        ],
    ],
],
```

Przykład zapisu:

```php
use Upsoftware\Svarium\Models\Tenant;

$tenant = Tenant::create([
    'name' => 'Hotel Prime',
    'owner_type' => 'company',
    'owner_id' => '15',
]);

$tenant->profile()->updateOrCreate([], [
    'payload' => [
        'billing_email' => 'office@example.com',
        'currency' => 'PLN',
    ],
]);
```

## Automatyczne wiązanie z `user_company` (bez ręcznego owner_type/owner_id)

Krok po kroku:

1. Włącz owner binding i mapowanie aliasu:

```php
// config/upsoftware.php
'tenancy' => [
    'owner' => [
        'enabled' => true,
        'type_column' => 'owner_type',
        'id_column' => 'owner_id',
        'map' => [
            'company' => App\Models\UserCompany::class,
        ],
    ],
],
```

2. Uruchom migracje tenancy (żeby mieć kolumny `owner_type` i `owner_id`):

```bash
php artisan svarium:tenant.install --migrate-tenancy
```

3. Dodaj trait do modelu `UserCompany`:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Tenancy\Concerns\OwnsTenants;

class UserCompany extends Model
{
    use OwnsTenants;
}
```

4. Tenant tworzy się automatycznie po utworzeniu firmy:

```php
$company = UserCompany::create([
    'name' => 'Demo Company',
]);

// tenant został utworzony automatycznie, jeśli nie istniał
$tenant = $company->tenants()->first();
```

5. (Opcjonalnie) ręczne utworzenie przez relację właściciela:

```php
$company = UserCompany::findOrFail(15);

$tenant = $company->createTenant([
    'name' => 'Demo Company',
    'slug' => 'demo-company',
]);
```

6. Odczyt właściciela z poziomu requestu:

```php
$owner = tenant_owner(); // App\Models\UserCompany|null
$ownerName = tenant_owner('name');
```

Jeśli chcesz wyłączyć auto-tworzenie w konkretnym modelu, nadpisz metodę:

```php
protected function shouldAutoCreateTenant(): bool
{
    return false;
}
```

Możesz też użyć helpera na modelu `Tenant`:

```php
use Upsoftware\Svarium\Models\Tenant;

$tenant = Tenant::createForOwner($company, [
    'name' => 'Demo Company',
    'slug' => 'demo-company',
]);
```

Mechanizmy (auto i ręczne):
- same ustawiają `owner_type` i `owner_id`,
- respektują `tenancy.owner.map` (gdy alias istnieje, zapisze alias, np. `company`),
- nie wymagają ręcznego dopisywania tych pól przy każdym create/update.

## Integracja z auth/panelem

Logowanie/reset i sprawdzanie ról w Svarium korzystają z helperów tenancy:
- w trybie `column` sprawdzanie jest filtrowane po `tenant_id`,
- w trybie `database` dane centralne używają `central_connection()`.

## Komenda init

`php artisan svarium:init` wspiera tenancy i:
- pyta, czy włączyć multi-tenant Svarium,
- pyta o tryb (`column` lub `database`),
- pyta, czy włączyć domeny tenantów (tabele `domains` / `model_has_domains`),
- pyta, czy tenant ma być rozpoznawany po domenie (`tenancy.domains.enabled`),
- ustawia mapowanie domen w kolumnowym tenancy na nowe nazwy:
  - `tenancy.column.model_maps.domains.table = model_has_domains`
  - `tenancy.column.model_maps.domains.domain_key = domain_id`
- zapisuje klucze w `config/upsoftware.php`,
- tworzy foldery:
  - `app/Svarium/Tenancy/Migrations`
  - `app/Svarium/Tenancy/Seeders`

## Komendy migracji tenant

`svarium:tenant.migrate` działa warstwowo:
- migracje systemowe paczki: `packages/svarium-laravel/src/database/migrations/tenants` (fallback: `.../migrations/tenancy`),
- migracje użytkownika: `tenancy.paths.tenant_migrations`.

Ważne:
- migracje użytkownika tenant są uruchamiane na bazach tenantów (tryb `database`),
- w trybie `column` migracje użytkownika tenant są pomijane (nie idą do bazy centralnej).
- domyślnie tenanty są filtrowane po `APP_ENV`:
  - komenda porównuje `APP_ENV` z kolumną `tenants.env`,
  - aby uruchomić migracje dla wszystkich tenantów niezależnie od środowiska, użyj `--all`.

Tworzenie migracji tenant w folderze z konfiguracji:

```bash
php artisan svarium:tenant.migration create_invoices_table --create=invoices
```

## Instalacja połączeń DB dla tenancy

Aby automatycznie dopisać połączenia `central` i `tenant` do `config/database.php`, użyj:

```bash
php artisan svarium:tenant.install
```

Komenda:
- kopiuje wskazane połączenie bazowe (domyślnie `database.default`),
- tworzy/aktualizuje połączenia `central` i `tenant`,
- pyta, czy włączyć tenancy,
- pyta, czy utworzyć tabele tenancy,
- pyta, czy włączyć domeny tenancy i czy utworzyć tabele domen,
- pyta, czy włączyć owner binding (`owner_type`/`owner_id`) i mapę ownerów,
- pyta, czy włączyć profil tenanta i pozwala ustawić tabelę/FK/model,
- automatycznie synchronizuje nazwę tabeli domen:
  - `tenant_domains` dla `tenancy.enabled=true`
  - `domains` dla `tenancy.enabled=false`,
- zapisuje referencje do ENV (np. `SVARIUM_CENTRAL_DB_HOST`, `SVARIUM_TENANT_DB_HOST`),
- aktualizuje klucze:
  - `upsoftware.tenancy.database.central_connection`
  - `upsoftware.tenancy.database.tenant_connection`
  - `upsoftware.tenancy.database.template_connection`

Opcje:

```bash
php artisan svarium:tenant.install --central=central --tenant=tenant --template=mysql
```

Tryb bez pytań (np. CI):

```bash
php artisan svarium:tenant.install --enable-tenancy=true --enable-domains=true --migrate-tenancy --migrate-domains --no-interaction
```

Pełny przykład z owner/profile:

```bash
php artisan svarium:tenant.install \
  --enable-tenancy=true \
  --enable-domains=true \
  --owner-enabled=true \
  --owner-map="customer=App\\Models\\Customer,company=App\\Models\\UserCompany" \
  --profile-enabled=true \
  --profile-table=tenant_profiles \
  --profile-foreign-key=tenant_id \
  --profile-model="\\Upsoftware\\Svarium\\Models\\TenantProfile" \
  --migrate-tenancy \
  --migrate-domains
```

Odinstalowanie tenancy:

```bash
php artisan svarium:tenant.uninstall
```

Komenda `svarium:tenant.uninstall`:
- usuwa tabele tenancy (`tenants`, `model_has_tenants`, `model_has_domains`, tabela profilu tenanta),
- synchronizuje tabelę domen do nazwy `domains`,
- ustawia:
  - `tenancy.enabled = false`
  - `tenancy.column.model_maps.tenants.enabled = false`
- zachowuje ustawienie domen (`tenancy.domains.enabled`) i map domen zgodnie z wcześniejszą konfiguracją,
- oraz aktualizuje ENV:
  - `SVARIUM_TENANCY_ENABLED=false`
  - `SVARIUM_TENANCY_DOMAINS_ENABLED` zgodnie z konfiguracją domen.

## Pola domeny i middleware domenowy

Tabela domen (`tenant_domains` / `domains`) wspiera pola:
- `is_primary`
- `locale`
- `theme`
- `status`
- `redirect_to_primary`
- `force_https`

Middleware domeny:
- rozpoznaje domenę requestu,
- opcjonalnie robi `301` do domeny głównej (`redirect_to_primary`),
- wymusza HTTPS (`force_https`),
- ustawia `locale` i `theme` z domeny,
- ustawia SEO:
  - canonical na domenę główną,
  - `noindex,follow` dla domen aliasowych.

Po wykonaniu:

```bash
php artisan optimize:clear
```

Uruchomienie migracji tenant:

```bash
php artisan svarium:tenant.migrate
```

Uruchomienie dla wszystkich tenantów (bez filtra `APP_ENV`):

```bash
php artisan svarium:tenant.migrate --all
```

Uruchomienie z resetem (`migrate:fresh`):

```bash
php artisan svarium:tenant.migrate --fresh
```

Rollback migracji tenant:

```bash
php artisan svarium:tenant.migrate.rollback
```

Rollback dla wszystkich tenantów (bez filtra `APP_ENV`):

```bash
php artisan svarium:tenant.migrate.rollback --all
```

Rollback z liczbą kroków:

```bash
php artisan svarium:tenant.migrate.rollback --step=3
```

Uruchomienie tylko dla wybranych tenantów (tryb `database`):

```bash
php artisan svarium:tenant.migrate --tenant=tenant_01 --tenant=tenant_02
```

Własna ścieżka migracji:

```bash
php artisan svarium:tenant.migrate --path=app/Svarium/Tenancy/Migrations
```

### Makro migracji: `$table->tenant_id()`

W migracjach możesz użyć skrótu:

```php
$table->tenant_id();
```

Makro automatycznie:
- dodaje kolumnę `tenant_id` (`string`),
- tworzy klucz obcy do `tenants.id`,
- ustawia `cascadeOnDelete()`.

Możesz też podać własne parametry:

```php
$table->tenant_id('tenant_id', 'tenants', 'id');
```

Pełny przykład migracji:

```php
Schema::create('invoices', function (Blueprint $table): void {
    $table->id();
    $table->tenant_id();
    $table->string('number');
    $table->timestamps();
});
```

## Komendy seederów tenant

Tworzenie seedera tenant w folderze z konfiguracji:

```bash
php artisan svarium:make.seeder DemoTenantSeeder
```

Uruchomienie seedowania tenant (auto-detekcja seederów):

```bash
php artisan svarium:tenant.seed
```

Uruchomienie konkretnego seedera:

```bash
php artisan svarium:tenant.seed --seeder=DemoTenantSeeder
```

Uruchomienie seedowania tylko dla wybranych tenantów (tryb `database`):

```bash
php artisan svarium:tenant.seed --tenant=tenant_01 --tenant=tenant_02
```

Uruchomienie seedowania razem z migracją:

```bash
php artisan svarium:tenant.migrate --seed
php artisan svarium:tenant.migrate --seed --seeder=DemoTenantSeeder
```

Uwaga:
- `--fresh` i `--rollback` są wzajemnie wykluczające.
- `--seed` jest ignorowane w trybie `--rollback`.
- komenda `svarium:tenant.migrate.rollback` jest skrótem dla `svarium:tenant.migrate --rollback`.

## Komenda tworzenia tenant

Szybkie utworzenie tenanta i jego domeny:

```bash
php artisan svarium:make.tenant
```

Komenda pyta interaktywnie o:
- nazwę tenanta,
- domenę główną.

W trybie `database` dodatkowo pyta o dane połączenia DB:
- host,
- port,
- nazwę bazy,
- użytkownika,
- hasło.

Przykład bez interakcji:

```bash
php artisan svarium:make.tenant "Acme" "acme.example.com" --db-host=127.0.0.1 --db-port=3306 --db-name=acme --db-user=acme --db-password=secret
```

## Rozwiązywanie problemów

- Tenant jest zawsze `null`:
  - sprawdź `tenancy.enabled = true`,
  - sprawdź, czy domena istnieje w `domains`,
  - sprawdź, czy `tenancy.domains.enabled = true` (jeśli tenant ma być rozpoznawany po host),
  - sprawdź, czy host nie jest na liście `central_domains`.
- Brak danych w trybie `column`:
  - jeśli `strict = true`, nierozpoznany tenant daje puste wyniki,
  - sprawdź, czy model używa `BelongsToTenant`.
- Błędy DB w trybie `database`:
  - sprawdź pola połączenia DB w tabeli `tenants`,
  - sprawdź, czy istnieje `template_connection` w `database.connections`,
  - sprawdź, czy baza tenanta jest osiągalna z aplikacji.

## Zakładka domen w panelu

Do budowy zakładki domen możesz użyć bezpośrednio modeli:
- `Tenant`
- `Domain`

Modele są gotowe pod CRUD i przypisywanie domen.
