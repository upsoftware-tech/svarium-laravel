# Roadmap Tenancy (Svarium)

Data aktualizacji: 2026-03-03

Dokument porównuje kierunek Svarium względem Tenancy for Laravel i oznacza:
- co wdrażamy później,
- co zostawiamy opcjonalnie,
- co robimy po swojemu (bo lepiej pasuje do Svarium).

## Status obecny (już mamy)

- [x] Tryby tenancy: `column` i `database`
- [x] Identyfikacja po domenie (host)
- [x] Domeny z kontekstem: `is_primary`, `locale`, `theme`, `status`, `redirect_to_primary`, `force_https`
- [x] SEO dla domen: canonical + `robots` dla aliasów
- [x] Relacje M:N:
  - `model_has_tenants`
  - `model_has_domains`
- [x] Owner binding: `owner_type` + `owner_id` + mapa aliasów
- [x] Profil tenanta: `tenant_profiles`
- [x] Auto-tworzenie tenanta z modelu ownera (`OwnsTenants`)
- [x] Komendy:
  - `svarium:tenant.install`
  - `svarium:tenant.uninstall`
  - `svarium:tenant.migrate`
  - `svarium:tenant.seed`
  - `svarium:tenant.migration`
  - `svarium:make.tenant`

## Wdrażamy później (priorytet)

### Faza 1: Tenant-aware infrastruktura runtime
- [ ] Tenant-aware Cache prefixing
- [ ] Tenant-aware Queue context propagation (jobs/listeners/notifications)
- [ ] Tenant-aware Filesystem scoping
- [ ] Tenant-aware Redis scoping

Dlaczego:
- To największa luka względem dojrzałych rozwiązań tenancy.
- Bez tego łatwo o przecieki danych między tenantami poza DB.

### Faza 2: Rozszerzona identyfikacja tenanta
- [ ] Resolver po subdomenie
- [ ] Resolver po ścieżce (`/t/{tenant}/...`)
- [ ] Resolver po nagłówku (API/BFF)
- [ ] Resolver po query param (tryb pomocniczy/dev)
- [ ] Konfigurowalny priorytet resolverów

Dlaczego:
- Potrzebne dla API, white-label i hybrydowych wdrożeń.

### Faza 3: Lifecycle tenancy i extension points
- [ ] Eventy cyklu życia tenancy:
  - [ ] `TenantResolving`
  - [ ] `TenantResolved`
  - [ ] `TenancyInitializing`
  - [ ] `TenancyInitialized`
  - [ ] `TenancyTerminated`
- [ ] Rejestr bootstrappers (pipeline) dla cache/queue/fs/redis

Dlaczego:
- Ułatwia rozszerzenia projektowe bez forka paczki.

## Opcjonalne (zależnie od projektów)

- [ ] Universal routes (ten sam route central + tenant)
- [ ] Cross-domain redirect helper (np. panel <-> public)
- [ ] Impersonacja użytkownika między tenantami
- [ ] Tenant config overrides (dynamiczne config per tenant)

Decyzja:
- Wdrożyć tylko jeśli pojawi się realny use-case.

## Robimy po swojemu (lepiej dla Svarium)

Poniższe obszary zostają jako specjalizacja Svarium (nie kopiujemy 1:1):

- [x] UI-first konfiguracja (`/svarium/install`, `/svarium/configuration`)
- [x] Własny model owner/profile (powiązanie tenantu z biznesem)
- [x] Widoczność rekordów per domena (`model_has_domains`)
- [x] Integracja z modułami/panelem/route helperami Svarium
- [x] Komendy instalacyjne prowadzące użytkownika po konfiguracji

Rozszerzymy dalej:
- [ ] GUI do zarządzania owner map i profile schema
- [ ] GUI do resolverów tenantów i kolejności fallback
- [ ] GUI do tenant-aware cache/queue/filesystem

## Raczej nie wdrażamy 1:1

- [ ] Pełna zgodność API z Tenancy for Laravel

Powód:
- Svarium ma inne założenia (panel + moduły + schema-driven UI + własny installer).
- Lepsza jest kompatybilność koncepcyjna niż ścisłe odwzorowanie API.

## Proponowana kolejność wdrożeń

1. Tenant-aware Queue + Cache (Faza 1)
2. Tenant-aware Filesystem + Redis (Faza 1)
3. Resolvery subdomain/path/header + priorytet (Faza 2)
4. Lifecycle events + bootstrapper pipeline (Faza 3)
5. Opcjonalne features pod konkretne projekty

## Kryteria jakości (Definition of Done)

Każdy etap uznajemy za zamknięty tylko gdy:
- [ ] są testy integracyjne central/tenant,
- [ ] brak wycieków danych między tenantami,
- [ ] jest dokumentacja + przykład migracji projektu,
- [ ] jest flaga konfiguracyjna i bezpieczny fallback.
