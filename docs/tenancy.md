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
        'central_domains' => ['app.example.com'],
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
- `tenancy.seeders.namespace`: bazowy namespace klas seederów tenant.
- `tenancy.domains.central_domains`: domeny, które nie powinny rozpoznawać tenanta.
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

## Tryb `database` (osobna baza per tenant)

Tenant jest rozpoznawany po domenie, a następnie Svarium przełącza połączenie na `tenancy.database.tenant_connection`.

Dane dostępowe do bazy tenanta są brane z pól rekordu tenanta:
- `tenancy_db_host`
- `tenancy_db_port`
- `tenancy_db_name`
- `tenancy_db_username`
- `tenancy_db_password`

## Modele tenant i domen

Wbudowane modele:
- `Upsoftware\Svarium\Models\Tenant`
- `Upsoftware\Svarium\Models\TenantDomain`

Domyślne tabele:
- `tenants`
- `tenant_domains`

Tworzone przez migracje:
- `2030_02_02_000001_create_tenants_table.php`
- `2030_02_02_000002_create_tenant_domains_table.php`

## Przykładowa konfiguracja danych

Tworzenie tenanta i domeny:

```php
use Upsoftware\Svarium\Models\Tenant;
use Upsoftware\Svarium\Models\TenantDomain;

$tenant = Tenant::create([
    'name' => 'ACME',
    'slug' => 'acme',
]);

TenantDomain::create([
    'tenant_id' => $tenant->id,
    'domain' => 'acme.example.test',
    'is_primary' => true,
]);
```

## Helpery

Dostępne helpery runtime:
- `tenant($key = null, $default = null)`
- `svarium_tenancy_enabled()`
- `svarium_tenancy_mode()`
- `svarium_tenancy_column_mode()`
- `svarium_tenancy_database_mode()`
- `central_connection()`

Przykład:

```php
$tenant = tenant();
$tenantId = tenant('id');

if (svarium_tenancy_column_mode()) {
    // logika tenant_id dla konkretnych operacji
}
```

## Integracja z auth/panelem

Logowanie/reset i sprawdzanie ról w Svarium korzystają z helperów tenancy:
- w trybie `column` sprawdzanie jest filtrowane po `tenant_id`,
- w trybie `database` dane centralne używają `central_connection()`.

## Komenda init

`php artisan svarium:init` wspiera tenancy i:
- pyta, czy włączyć multi-tenant Svarium,
- pyta o tryb (`column` lub `database`),
- zapisuje klucze w `config/upsoftware.php`,
- tworzy foldery:
  - `app/Svarium/Tenancy/Migrations`
  - `app/Svarium/Tenancy/Seeders`

## Komendy migracji tenant

Tworzenie migracji tenant w folderze z konfiguracji:

```bash
php artisan svarium:make.migrate create_invoices_table --create=invoices
```

## Instalacja połączeń DB dla tenancy

Aby automatycznie dopisać połączenia `central` i `tenant` do `config/database.php`, użyj:

```bash
php artisan svarium:install:tenant
```

Komenda:
- kopiuje wskazane połączenie bazowe (domyślnie `database.default`),
- tworzy/aktualizuje połączenia `central` i `tenant`,
- zapisuje referencje do ENV (np. `SVARIUM_CENTRAL_DB_HOST`, `SVARIUM_TENANT_DB_HOST`),
- aktualizuje klucze:
  - `upsoftware.tenancy.database.central_connection`
  - `upsoftware.tenancy.database.tenant_connection`
  - `upsoftware.tenancy.database.template_connection`

Opcje:

```bash
php artisan svarium:install:tenant --central=central --tenant=tenant --template=mysql
```

Po wykonaniu:

```bash
php artisan optimize:clear
```

Uruchomienie migracji tenant:

```bash
php artisan svarium:tenant.migrate
```

Uruchomienie z resetem (`migrate:fresh`):

```bash
php artisan svarium:tenant.migrate --fresh
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
  - sprawdź, czy domena istnieje w `tenant_domains`,
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
- `TenantDomain`

Modele są gotowe pod CRUD i przypisywanie domen.
