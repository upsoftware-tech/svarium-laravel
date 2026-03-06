# Komendy CLI Svarium

Ten dokument opisuje wszystkie komendy Artisan rejestrowane przez paczkę `svarium-laravel`.

## Szybka lista

| Komenda | Opis |
|---|---|
| `svarium:app.init` | Interaktywna inicjalizacja aplikacji pod Svarium. |
| `svarium:app.layout` | Interaktywna konfiguracja layoutu panelu. |
| `svarium:app.colors` | Podmienia neutralną tonację OKLCH w `app.css` (`:root` i `.dark`). |
| `svarium:panel.add` | Dodaje panel do `app/Svarium/panels.php`. |
| `svarium:menu.add` | Dodaje pozycję menu. |
| `svarium:permission` | Tworzy bazowe role/uprawnienia. |
| `svarium:user.add` | Dodaje użytkownika, przypisuje rolę i tenanty. |
| `svarium:auth.socials.install` | Konfigurator logowania social (Google/Facebook/Apple itd.). |
| `svarium:make.resource` | Generator zasobu (resource). |
| `svarium:make.module` | Generator modułu. |
| `svarium:make.layout` | Generator klasy layoutu. |
| `svarium:make.plugin` | Generator szkieletu pluginu. |
| `svarium:make.tenant` | Tworzy tenant + domenę główną (oraz dane DB w trybie `database`). |
| `svarium:make.tenant.migration` | Tworzy migrację tenantową. |
| `svarium:make.tenant.seeder` | Tworzy seeder tenantowy. |
| `svarium:lang.add` | Dodaje język do systemu. |
| `svarium:lang.prepare` | Buduje JSON tłumaczeń z plików PHP (`messages.php`). |
| `svarium:lang.merge` | Scala tłumaczenia paczki z tłumaczeniami aplikacji. |
| `svarium:lang.sort` | Sortuje języki w ustawieniach. |
| `svarium:tenant.install` | Konfigurator tenancy + połączeń central/tenant. |
| `svarium:tenant.uninstall` | Wyłącza tenancy i czyści konfigurację/migracje tenancy. |
| `svarium:tenant.install.owner` | Włącza/wyłącza owner binding tenancy. |
| `svarium:tenant.install.profile` | Włącza/wyłącza profil tenantu. |
| `svarium:tenant.migrate` | Uruchamia migracje tenancy (column/database mode). |
| `svarium:tenant.migrate.rollback` | Rollback migracji tenancy. |
| `svarium:tenant.seed` | Uruchamia seedery tenancy (column/database mode). |

## 1) Konfiguracja aplikacji i panelu

### `svarium:app.init`

Inicjalizacja aplikacji Svarium (panel, auth routes prefix, i18n, API, tenancy, role/admin itd.).

```bash
php artisan svarium:app.init
```

Najczęściej używane:

```bash
php artisan svarium:app.init
```

### `svarium:app.layout`

Interaktywna konfiguracja layoutu panelu.

```bash
php artisan svarium:app.layout
```

### `svarium:app.colors`

Interaktywnie zmienia neutralną tonację kolorów w `resources/css/app.css`.
Komenda podmienia tokeny CSS w sekcjach `:root` i `.dark` (m.in. `--muted`, `--secondary`, `--accent`, `--border`, `--ring`, `--sidebar-*`).

Dostępne tonacje:

- `slate`
- `gray`
- `zinc`
- `neutral`
- `stone`
- `taupe`
- `mauve`
- `mist`
- `olive`

Składnia:

```bash
php artisan svarium:app.colors {--file=resources/css/app.css} {--tone=}
```

Przykłady:

```bash
php artisan svarium:app.colors
php artisan svarium:app.colors --tone=slate
php artisan svarium:app.colors --tone=mauve --file=resources/css/app.css
```

### `svarium:panel.add`

Dodaje panel do `app/Svarium/panels.php`.

Składnia:

```bash
php artisan svarium:panel.add {name?} {--prefix=} {--no-prefix}
```

Przykłady:

```bash
php artisan svarium:panel.add admin --prefix=admin
php artisan svarium:panel.add app --no-prefix
```

### `svarium:menu.add`

Interaktywne dodawanie pozycji menu.

```bash
php artisan svarium:menu.add
```

### `svarium:permission`

Interaktywne tworzenie bazowych ról/uprawnień.

```bash
php artisan svarium:permission
```

### `svarium:user.add`

Interaktywnie tworzy użytkownika:

- nazwa,
- e-mail,
- hasło (własne lub losowe),
- rola (z tabeli `roles`),
- tenanty (wielokrotny wybór z tabeli `tenants`).

Komenda przypisuje rolę w `model_has_roles` oraz mapuje tenanty w `model_has_tenants` (jeśli tabela istnieje).
Rola i minimum jeden tenant są wymagane.

Zasady walidacji:

- e-mail musi mieć poprawny format,
- e-mail musi być unikalny (jeśli istnieje, komenda zwraca do ponownego podania),
- hasło ręczne musi mieć minimum 8 znaków.
- rola jest wymagana (brak wyboru = ponowne pytanie),
- przynajmniej jeden tenant jest wymagany (brak wyboru = ponowne pytanie).

Flow hasła (interaktywnie):

- prompt: `Wpisz hasło lub zostaw puste aby wygenerować losowe.`
- puste hasło lub krótsze niż 8 znaków = błąd i ponowne pytanie,
- hasło nie wymaga drugiego pola potwierdzenia,
- losowe hasło generujesz flagą `--random-password`.

Składnia:

```bash
php artisan svarium:user.add \
  {--name=} {--email=} {--password=} {--random-password} \
  {--role=} {--tenant=*}
```

Przykłady:

```bash
php artisan svarium:user.add
php artisan svarium:user.add --name="Jan Kowalski" --email="jan@example.com" --random-password
php artisan svarium:user.add --email="anna@example.com" --role=1 --tenant=tenant_01 --tenant=tenant_02
php artisan svarium:user.add --email="user@example.com" --password="haslo1234"
```

### `svarium:auth.socials.install`

Interaktywna konfiguracja providerów social login.

```bash
php artisan svarium:auth.socials.install
```

## 2) Generatory (`make`)

### `svarium:make.resource`

Generator resource (struktura katalogów + stuby).

Składnia:

```bash
php artisan svarium:make.resource {resource?}
```

Przykład:

```bash
php artisan svarium:make.resource Pages
```

### `svarium:make.module`

Generator modułu (module/resource/model/table/form/operation/translations/menu).

Składnia:

```bash
php artisan svarium:make.module {name?}
```

Przykłady:

```bash
php artisan svarium:make.module Patient
php artisan svarium:make.module
```

### `svarium:make.layout`

Generator klasy layoutu w `app/Svarium/Layouts`.

Składnia:

```bash
php artisan svarium:make.layout {name}
```

Przykład:

```bash
php artisan svarium:make.layout Auth
```

### `svarium:make.plugin`

Generator szkieletu pluginu.

Składnia:

```bash
php artisan svarium:make.plugin {name?}
```

Przykład:

```bash
php artisan svarium:make.plugin Billing
```

### `svarium:make.tenant`

Tworzy tenant oraz domenę główną.
W trybie `database` pozwala od razu podać dane połączenia DB tenantu.

Składnia:

```bash
php artisan svarium:make.tenant {name?} {domain?} {--slug=} {--inactive} {--db-host=} {--db-port=} {--db-name=} {--db-user=} {--db-password=}
```

Przykłady:

```bash
php artisan svarium:make.tenant "Acme" acme.test
php artisan svarium:make.tenant "Acme" acme.test --slug=acme --inactive
php artisan svarium:make.tenant "Acme" acme.test --db-host=127.0.0.1 --db-port=3306 --db-name=acme_db --db-user=acme --db-password=secret
```

### `svarium:make.tenant.migration`

Tworzy migrację tenantową w katalogu migracji tenant.

Składnia:

```bash
php artisan svarium:make.tenant.migration {name} {--create=} {--table=} {--path=} {--fullpath}
```

Przykłady:

```bash
php artisan svarium:make.tenant.migration create_orders_table --create=orders
php artisan svarium:make.tenant.migration add_status_to_orders --table=orders
```

### `svarium:make.tenant.seeder`

Tworzy seeder tenantowy.

Składnia:

```bash
php artisan svarium:make.tenant.seeder {name} {--path=} {--namespace=}
```

Przykłady:

```bash
php artisan svarium:make.tenant.seeder DemoTenantSeeder
php artisan svarium:make.tenant.seeder Billing/InvoiceSeeder
```

## 3) Tłumaczenia

### `svarium:lang.add`

Dodaje język do systemu i uruchamia przygotowanie/scalanie tłumaczeń.

Składnia:

```bash
php artisan svarium:lang.add {lang?*}
```

Przykłady:

```bash
php artisan svarium:lang.add pl
php artisan svarium:lang.add en de
php artisan svarium:lang.add
```

### `svarium:lang.prepare`

Konwertuje tłumaczenia PHP (`messages.php`) do JSON (`pl.json`, `en.json`) i bierze pod uwagę także tłumaczenia modułów.

Składnia:

```bash
php artisan svarium:lang.prepare {lang?}
```

Przykłady:

```bash
php artisan svarium:lang.prepare
php artisan svarium:lang.prepare pl
```

### `svarium:lang.merge`

Scala tłumaczenia z paczki do tłumaczeń aplikacji (`lang/*.json`).

Składnia:

```bash
php artisan svarium:lang.merge {lang?}
```

Przykłady:

```bash
php artisan svarium:lang.merge
php artisan svarium:lang.merge en
```

### `svarium:lang.sort`

Sortuje wpisy językowe w ustawieniach globalnych.

```bash
php artisan svarium:lang.sort
```

## 4) Tenancy i domeny

### `svarium:tenant.install`

Główna komenda instalacyjna tenancy:

- konfiguruje `config/database.php` (`central`, `tenant`),
- ustawia tryb tenancy (`database`/`column`),
- ustawia domeny tenancy,
- opcjonalnie uruchamia migracje.

Składnia:

```bash
php artisan svarium:tenant.install \
  {--central=central} {--tenant=tenant} {--template=} {--mode=} \
  {--enable-tenancy=} {--enable-domains=} \
  {--owner-enabled=} {--owner-map=} \
  {--profile-enabled=} {--profile-table=} {--profile-foreign-key=} {--profile-model=} \
  {--migrate-tenancy} {--migrate-domains} {--force}
```

Przykłady:

```bash
php artisan svarium:tenant.install
php artisan svarium:tenant.install --mode=database --migrate-tenancy --migrate-domains
php artisan svarium:tenant.install --enable-tenancy=true --enable-domains=true --owner-enabled=true
```

### `svarium:tenant.uninstall`

Wyłącza tenancy, czyści konfigurację i wycofuje/domyka elementy tenancy.

Składnia:

```bash
php artisan svarium:tenant.uninstall {--path=*} {--force}
```

Przykłady:

```bash
php artisan svarium:tenant.uninstall
php artisan svarium:tenant.uninstall --path=/abs/path/to/tenant/migrations
```

### `svarium:tenant.install.owner`

Włącza/wyłącza powiązanie ownera tenantu i opcjonalnie uruchamia migrację owner.

Składnia:

```bash
php artisan svarium:tenant.install.owner {--enable=} {--migrate=} {--force}
```

Przykłady:

```bash
php artisan svarium:tenant.install.owner --enable=true --migrate=true
php artisan svarium:tenant.install.owner --enable=false
```

### `svarium:tenant.install.profile`

Włącza/wyłącza profil tenantu i opcjonalnie uruchamia migrację profilu.

Składnia:

```bash
php artisan svarium:tenant.install.profile {--enable=} {--migrate=} {--force}
```

Przykłady:

```bash
php artisan svarium:tenant.install.profile --enable=true --migrate=true
php artisan svarium:tenant.install.profile --enable=false
```

### `svarium:tenant.migrate`

Uruchamia migracje tenancy.
Obsługuje:

- `--fresh`,
- `--rollback`,
- `--step`,
- `--seed` + `--seeder`,
- `--tenant=*` (w trybie `database`),
- `--path=*` (nadpisanie ścieżek).

Składnia:

```bash
php artisan svarium:tenant.migrate {--tenant=*} {--fresh} {--rollback} {--step=1} {--seed} {--seeder=*} {--path=*} {--force}
```

Przykłady:

```bash
php artisan svarium:tenant.migrate
php artisan svarium:tenant.migrate --tenant=1 --tenant=2
php artisan svarium:tenant.migrate --fresh --seed
php artisan svarium:tenant.migrate --rollback --step=2
```

### `svarium:tenant.migrate.rollback`

Skrót rollbacku dla migracji tenancy.

Składnia:

```bash
php artisan svarium:tenant.migrate.rollback {--tenant=*} {--step=1} {--path=*} {--force}
```

Przykłady:

```bash
php artisan svarium:tenant.migrate.rollback
php artisan svarium:tenant.migrate.rollback --tenant=5 --step=3
```

### `svarium:tenant.seed`

Uruchamia seedery tenancy.
W trybie `database` może siać wskazane tenanty, w `column` działa na połączeniu centralnym.

Składnia:

```bash
php artisan svarium:tenant.seed {--tenant=*} {--seeder=*} {--path=} {--namespace=} {--force}
```

Przykłady:

```bash
php artisan svarium:tenant.seed
php artisan svarium:tenant.seed --seeder=DemoTenantSeeder
php artisan svarium:tenant.seed --tenant=1 --tenant=2 --seeder=Billing\\InvoiceSeeder
```

## Uwagi

- Komendy są auto-odkrywane z katalogu `src/Console/Commands`.
- Aby wyświetlić aktualnie dostępne komendy w projekcie:

```bash
php artisan list | grep svarium:
```
