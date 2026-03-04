# Tenancy Per Domena

Ten dokument opisuje ograniczanie widoczności rekordów do wybranych domen w ramach jednego tenanta.

## Kiedy używać

Użyj tego mechanizmu gdy:
- masz 1 tenanta z wieloma domenami,
- rekord (np. apartament) ma być widoczny tylko na części domen.

Przykład:
- tenant `tenant_01` ma 16 domen,
- apartament `X` ma być widoczny tylko na 4 domenach.

## Tabele

Wykorzystywane są dwie tabele mapujące:

- `model_has_tenants`  
  mapowanie `model ↔ tenant`
- `model_has_domains`  
  mapowanie `model ↔ domain` (per domena)

Pola:

`model_has_tenants`
- `tenant_id`
- `model_type`
- `model_id`

`model_has_domains`
- `domain_id`
- `model_type`
- `model_id`

## Jak działa scope

Trait `Upsoftware\Svarium\Tenancy\Concerns\BelongsToTenant` filtruje rekordy tak:

1. Najpierw scope tenantowy:
- kolumna `tenant_id` w rekordzie lub
- wpis w `model_has_tenants` lub
- wpis w `model_has_domains`.

2. Potem scope domenowy:
- jeśli rekord ma wpisy w `model_has_domains`, to musi mieć wpis dla aktualnej domeny,
- jeśli nie ma żadnego wpisu domenowego, jest traktowany jako globalny dla tenanta.

To oznacza:
- możesz mieć rekordy „globalne tenantowo”,
- możesz mieć rekordy ograniczone tylko do części domen.

## Wymagania w modelu

Model musi używać traitu:

```php
use Upsoftware\Svarium\Tenancy\Concerns\BelongsToTenant;

class Apartment extends Model
{
    use BelongsToTenant;
}
```

## API w modelu

Dostępne metody:
- `$model->attachTenant(string $tenantId)`
- `$model->syncTenants(array $tenantIds)`
- `$model->attachTenantDomain(int|string $domainId, ?string $tenantId = null)`
- `$model->syncTenantDomains(array $domainIds, ?string $tenantId = null)`

## Scenariusz: 16 domen, rekord na 4 domenach

```php
// 1) rekord należy do tenant_01
$apartment->attachTenant('tenant_01');

// 2) rekord ma być widoczny tylko na domenach o ID: 3, 7, 11, 15
$apartment->syncTenantDomains([3, 7, 11, 15]);
```

## Konfiguracja

`config/upsoftware.php`:

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

## Aktualna domena w runtime

Możesz pobrać aktualnie rozpoznaną domenę helperem:

```php
$domain = tenant_domain();
$domainId = tenant_domain('id');
```

## Migracje

Po wdrożeniu uruchom:

```bash
php artisan migrate
```

Uwagi:
- jeśli istniała stara tabela `model_has_tenant`, migracja zmieni jej nazwę na `model_has_tenants`,
- jeśli istniały stare tabele `tenant_domains` i `model_has_domain_tenants`, migracja upgrade przeniesie je do `domains` i `model_has_domains`,
- migracje tenancy są ładowane tylko gdy `upsoftware.tenancy.enabled=true`.
