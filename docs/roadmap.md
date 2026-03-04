# Roadmap Svarium (ogólny)

Data aktualizacji: 2026-03-04

To jest główny roadmap produktu. Zawiera wszystkie obszary rozwoju:
- panel i UI schema,
- resource/table/forms,
- auth i onboarding,
- tenancy,
- DX i jakość.

## Jak czytać roadmap

- `[x]` gotowe
- `[~]` w toku
- `[ ]` planowane

## 1) Fundament platformy

- [x] Panel runtime + moduły + operacje
- [x] Layouty PHP + render dynamicznego drzewa komponentów Vue
- [x] Buildery UI (`Flex`, `Block`, `Text`, `Title`, `Sidebar`, `Navigation`)
- [x] Konfiguracja przez UI (`/svarium/install`, `/svarium/configuration`)
- [ ] Stabilne API wersjonowane dla komponentów PHP (`@since`, deprecations)
- [ ] SemVer policy dla `svarium-laravel` + `svarium-npm`

## 2) Resource / Table / Forms

- [x] Rozbudowana tabela (sticky, selekcja, multi-select, dark mode, filtry w headerze)
- [x] InputSearch rozmiary (`xs`, `sm`, `default`) + obsługa operatorów bez pola wartości
- [x] Preview w resource + `onlyOn(['create','edit'])`
- [ ] Presety kolumn i filtrów per użytkownik (persist)
- [ ] Eksport/import CSV/XLSX z uwzględnieniem filtrów i selekcji
- [ ] Wbudowane masowe akcje (bulk actions) z rollback/preview
- [ ] Historia zmian rekordów (audit trail UI)

## 3) Auth i onboarding

- [x] Konfigurowalna rejestracja (schema/layout/events)
- [x] Możliwość nadpisania layoutu auth (`Layout::make(...)->body(...)`)
- [ ] Locale w walidacji formularzy auth: po zmianie języka bez refresh komunikaty błędów mają być od razu w nowym locale (bez utraty stanu formularza i bez znikania oznaczeń błędów)
- [ ] Spójny flow login/register/reset jako jedna warstwa konfiguracyjna (bez duplikacji)
- [ ] 2FA (TOTP + recovery codes) w core
- [ ] Passwordless (magic link) jako tryb auth
- [ ] Rate limits i anty-automatyzacja (captcha/honeypot) w auth actions

## 4) Tenancy (skrót)

Szczegółowy plan tenancy:
- [Roadmap tenancy](./tenancy-roadmap.md)

Najważniejsze cele globalne:
- [x] `column` i `database`
- [x] owner/profile + domeny + SEO
- [ ] tenant-aware cache/queue/filesystem/redis
- [ ] multi-resolver tenantów (subdomain/path/header/query)

## 5) To, co warto wdrożyć ponad paczkę tenancy

Te elementy nie są „must-have” w klasycznych paczkach, ale dają przewagę Svarium:

- [ ] **UI Tenant Studio**  
  Zarządzanie tenantami, domenami, owner map, profile schema i resolverami z panelu (bez edycji configów).
- [ ] **Tenant Policy Engine**  
  Reguły widoczności danych per tenant/per domena/per rola (warstwa policy deklaratywna).
- [ ] **Tenant Feature Flags**  
  Włączanie modułów/funkcji per tenant i per plan (SaaS tiers).
- [ ] **Tenant Blueprints**  
  Szablony konfiguracji nowego tenantu (role, moduły, menu, ustawienia, seed startowy).
- [ ] **Tenant Diagnostics**  
  Ekran diagnostyczny: aktywny tenant, resolver source, DB connection, cache prefix, domena primary.
- [ ] **Tenant-safe async**  
  Guardy dla jobów/notification/listeners, które blokują wykonanie bez kontekstu tenant.
- [ ] **Data portability**  
  Eksport/import tenantu (backup logiczny) + anonimizacja danych testowych.

## 6) Developer Experience (DX)

- [x] Generatory (`svarium:init`, `svarium:make.tenant`, `svarium:tenant.migration`)
- [ ] `svarium:doctor` (diagnoza konfiguracji i braków)
- [ ] `svarium:upgrade` (asystent migracji wersji)
- [ ] Snapshot tests dla renderowanego drzewa komponentów
- [ ] Playground schema-driven UI (hot reload + inspector props)

## 7) Jakość i operacyjność

- [ ] Matryca testów e2e (auth/resource/tenancy)
- [ ] Testy kontraktowe dla API komponentów
- [ ] Observability: log correlation per tenant/request/job
- [ ] SLO/SLA checklist dla wdrożeń SaaS
- [ ] Hardening security checklist (CSP, headers, session, impersonation audit)

## Priorytety na najbliższe iteracje

1. Tenant-aware queue + cache + diagnostics (`Tenant Diagnostics`).
2. Persystencja widoków tabeli per użytkownik + eksport CSV/XLSX.
3. `svarium:doctor` + testy kontraktowe komponentów.
4. Tenant Feature Flags + Tenant Blueprints.

## Decyzje architektoniczne

- Nie kopiujemy 1:1 żadnej zewnętrznej paczki tenancy.
- Utrzymujemy kompatybilność koncepcyjną, ale interfejs projektujemy pod Svarium:
  - schema-driven UI,
  - panel-first UX,
  - konfiguracja przez UI + komendy.
