# Komendy CLI Svarium

Ten dokument opisuje wszystkie komendy Artisan rejestrowane przez paczkę `svarium-laravel`.

## Szybka lista

| Komenda | Opis |
|---|---|
| `svarium:app:install` | Instaluje Svarium w istniejącej aplikacji Laravel i uruchamia interaktywny init. |
| `svarium:app.init` | Interaktywna inicjalizacja aplikacji pod Svarium. |
| `svarium:native install` | Uruchamia instalację natywną (`native:install`) i (opcjonalnie) ide-helper. |
| `svarium:app.layout` | Interaktywna konfiguracja layoutu panelu. |
| `svarium:app.colors` | Tworzy/nadpisuje `app.css` ze stuba (PRIMARY/PRIMARY_DARK) i podmienia neutralną tonację OKLCH (`:root`, `.dark`). |
| `svarium:panel.add` | Dodaje panel do `app/Svarium/panels.php`. |
| `svarium:menu.add` | Dodaje pozycję menu. |
| `svarium:menu.map` | Pokazuje mapę runtime menu (drzewo + ID/path_id). |
| `svarium:route:list` | Pokazuje trasy operation i aliasy nazwanych tras Svarium (z filtrami). |
| `svarium:attribute.add` | Dodaje atrybut pola globalnie lub do wskazanego modułu. |
| `svarium:attribute.move` | Przenosi atrybut pola między plikiem globalnym i modułami. |
| `svarium:attribute.remove` | Usuwa atrybut pola z globalnego pliku lub modułu. |
| `svarium:translation.add` | Dodaje tłumaczenie globalnie lub do wskazanego modułu. |
| `svarium:translation.move` | Przenosi tłumaczenia między globalnym plikiem i modułami. |
| `svarium:translation.remove` | Usuwa tłumaczenia z globalnego pliku lub modułu. |
| `svarium:permission` | Tworzy bazowe role/uprawnienia. |
| `svarium:permission.sync` | Synchronizuje permissiony Svarium z zasobów i operation. |
| `svarium:role.module` | Przypisuje/odbiera wszystkie permissiony wskazanego modułu do roli. |
| `svarium:user.add` | Dodaje użytkownika, przypisuje rolę i tenanty. |
| `svarium:user.access` | Przypisuje/odbiera użytkownikowi role lub bezpośrednie uprawnienia. |
| `svarium:diagnose.database.connection` | Diagnozuje połączenie DB dla wybranego modelu (`upsoftware.models`). |
| `svarium:auth.socials.install` | Konfigurator logowania social (Google/Facebook/Apple itd.). |
| `svarium:make.resource` | Generator zasobu (resource). |
| `svarium:make.module` | Generator modułu. |
| `svarium:make.notification` | Generator Notification (globalnie lub w module). |
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

### `svarium:app:install`

To jest preferowana komenda po instalacji pakietu do istniejącego projektu Laravel:

```bash
composer require upsoftware/svarium-laravel
php artisan svarium:app:install
```

Komenda działa jako prosty instalator wejściowy i deleguje cały interaktywny flow do:

```bash
php artisan svarium:app.init
```

Dzięki temu masz jeden spójny proces konfiguracji, ale prostszą ścieżkę startu po `composer require`.

### `svarium:app.init`

Inicjalizacja aplikacji Svarium (panel, auth routes prefix, i18n, API, tenancy, role/admin itd.).

Najważniejsze zachowanie:

- na starcie komenda sprawdza połączenie domyślne `database.default`,
- wymagany jest driver `mysql`,
- wykonywany jest test połączenia (`SELECT 1`),
- jeśli check nie przejdzie, init kończy się błędem i nie uruchamia dalszych kroków,
- komenda pyta, czy uruchomić `php artisan svarium:native install`,
- podczas przygotowania plików `init` uruchamia `php artisan svarium:app.colors --initialize`,
- w sekcji ról pyta o bypass tenancy jako wybór z listy ról (również tych dodanych ręcznie) i zapisuje wynik do `upsoftware.auth.tenant_bypass_role_keys`,
- tworzenie konta jest ogólne (`Czy utworzyć konto?`) i pozwala przypisać jednocześnie wiele ról (`Wybierz role`), które trafiają do `model_has_roles`,
- `ide-helper:*` nie jest już uruchamiany bezpośrednio w `init`.

```bash
php artisan svarium:app.init
```

Najczęściej używane:

```bash
php artisan svarium:app.init
```

### `svarium:native install`

Uruchamia instalację natywną i helpery IDE:

- `ide-helper:generate` (jeśli dostępne),
- `ide-helper:models --nowrite` (jeśli dostępne),
- `ide-helper:meta` (jeśli dostępne),
- `native:install` (wymagane).

Składnia:

```bash
php artisan svarium:native install {--without-ide-helper}
```

Przykłady:

```bash
php artisan svarium:native install
php artisan svarium:native install --without-ide-helper
```

### `svarium:app.layout`

Interaktywna konfiguracja layoutu panelu.

```bash
php artisan svarium:app.layout
```

### `svarium:app.colors`

Komenda obsługuje trzy kroki:

1. `--initialize`:
- tworzy/nadpisuje `resources/css/app.css` na bazie stuba `stubs/app.css.stub`,
- pyta o kolor `PRIMARY` (light) i `PRIMARY_DARK` (dark),
- zapisuje podstawowy plik CSS.

2. tonacja neutralna:
- podmienia tokeny CSS w sekcjach `:root` i `.dark` (m.in. `--muted`, `--secondary`, `--accent`, `--border`, `--ring`, `--sidebar-*`).
- domyślna tonacja jest pobierana z `config('upsoftware.colors.tone')`.

3. `PRIMARY` / `PRIMARY_DARK` bez `--initialize`:
- przy zwykłym uruchomieniu komenda pyta, czy zmienić `PRIMARY`,
- możesz wskazać kolory/odcienie interaktywnie lub opcjami CLI,
- domyślne wartości kolorów/odcieni są pobierane z `config('upsoftware.colors.primary.*')`,
- aby pominąć ten krok użyj `--skip-primary`.
- po wykonaniu komendy wybrane wartości są zapisywane do `config/upsoftware.php` (`colors.*`) i używane jako domyślne przy kolejnym uruchomieniu.

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
php artisan svarium:app.colors {--file=} {--initialize} {--force} {--skip-primary} {--primary-color=} {--primary-shade=} {--primary-dark-color=} {--primary-dark-shade=} {--tone=}
```

Przykłady:

```bash
php artisan svarium:app.colors
php artisan svarium:app.colors --initialize
php artisan svarium:app.colors --initialize --force
php artisan svarium:app.colors --primary-color=amber --primary-shade=500 --primary-dark-color=amber --primary-dark-shade=600
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

### `svarium:menu.map`

Wyświetla pełną mapę runtime menu z technicznymi identyfikatorami:

- `id` (wewnętrzny identyfikator węzła),
- `menu_key`,
- `path_id` / `path_ids` (stabilne ID gałęzi),
- `url`, `icon`.

Przydatne, gdy chcesz łatwo dopisać nowe elementy przez `parent('...')` i `pathId('...')`.

Składnia:

```bash
php artisan svarium:menu.map {--navigation=} {--json}
```

Przykłady:

```bash
php artisan svarium:menu.map
php artisan svarium:menu.map --navigation=sidebar_user
php artisan svarium:menu.map --json
```

### `svarium:route:list`

Pokazuje dwie sekcje:

- `Operation routes` (panel, moduł, metody, URI, klasa operation, bazowa nazwa aliasu),
- `Named aliases` (wszystkie nazwane aliasy Laravel wskazujące na `SvariumHttpKernel`).

W sekcji `Operation routes` komenda pokazuje też:

- `Permission` - permission wymagany do wejścia na operation,
- `Access levels` - role, które mają ten permission (zawsze zawiera `superadmin` jako bypass).

To samo jest pokazywane w sekcji `Named aliases` (dla aliasów, które mapują się do operation w panelu).

Przydatne do debugowania nazw tras typu:

- `module:ksef.documents`,
- `module:admin.ksef.documents`,
- aliasów metod (`.get`, `.post`) i akcji (`.index`, `.store`, `.update`, `.delete`).

Składnia:

```bash
php artisan svarium:route:list {--panel=} {--module=} {--name=} {--json}
```

Przykłady:

```bash
php artisan svarium:route:list
php artisan svarium:route:list --panel=admin
php artisan svarium:route:list --module=ksef
php artisan svarium:route:list --name=module:ksef
php artisan svarium:route:list --json
```

### `svarium:attribute.add`

Dodaje atrybut pola:

- globalnie do `app/Svarium/attributes.php`,
- albo do metody `fieldAttributes()` w wybranym module.

Flow interaktywny:

- pyta o nazwę pola,
- pyta o etykietę,
- pyta, czy dodać atrybut do modułu,
- jeśli tak: pokazuje listę modułów i zapisuje wpis w wybranym module,
- jeśli nie: zapisuje wpis do pliku globalnego.
- po zapisie pyta, czy od razu dodać tłumaczenia etykiety.

Obsługiwane flagi:

- `--field=` - nazwa pola, np. `first_name`
- `--label=` - etykieta, np. `First name`
- `--module=` - szybki wybór modułu, np. `Patient`
- `--translations` - po zapisaniu atrybutu uruchamia od razu interaktywną pętlę tłumaczeń dla wszystkich locale w lokalizacji globalnej lub modułowej; pusta wartość oznacza `klucz = wartość domyślna`.

Składnia:

```bash
php artisan svarium:attribute.add {--field=} {--label=} {--module=} {--translations}
```

Przykłady:

```bash
php artisan svarium:attribute.add
php artisan svarium:attribute.add --field=first_name --label="First name"
php artisan svarium:attribute.add --field=first_name --label="First name" --module=Patient
php artisan svarium:attribute.add --field=first_name --label="First name" --module=Patient --translations
```

Wynik:

```php
'first_name' => 'First name',
```

Jeśli wpis już istnieje w pliku globalnym albo w module, komenda przerwie działanie i zwróci błąd.

### `svarium:attribute.move`

Przenosi atrybut pola między:

- `app/Svarium/attributes.php` (globalny plik),
- modułami (`app/Svarium/Modules/*/*Module.php`, metoda `fieldAttributes()`).

Flow interaktywny:

- `Skąd chcesz przenieść`,
- `Dokąd chcesz przenieść` (bez miejsca wybranego jako źródło),
- wybór jednego lub wielu pól,
- `Czy usunąć z pliku źródłowego`.

Flagi:

- `--from=` - `global` albo nazwa modułu,
- `--to=` - `global` albo nazwa modułu,
- `--field=*` - nazwa pola (można podać wiele razy),
- `--delete-source` - usuwa wpis ze źródła po zapisaniu celu.

Składnia:

```bash
php artisan svarium:attribute.move {--from=} {--to=} {--field=*} {--delete-source}
```

Przykłady:

```bash
php artisan svarium:attribute.move
php artisan svarium:attribute.move --from=Patient --to=global --field=last_name --delete-source
php artisan svarium:attribute.move --from=global --to=Patient --field=email
php artisan svarium:attribute.move --from=Patient --to=global --field=last_name --field=email --delete-source
```

### `svarium:attribute.remove`

Usuwa atrybut pola z:

- `app/Svarium/attributes.php` (globalny plik),
- modułu (`fieldAttributes()`).

Flow interaktywny:

- `Skąd chcesz usunąć`,
- `Wybierz pola do usunięcia`,
- potwierdzenie usunięcia.

Flagi:

- `--from=` - `global` albo nazwa modułu,
- `--module=` - alias dla `--from` (tylko moduł),
- `--field=*` - nazwa pola (można podać wiele razy),
- `--force` - usuwa bez pytania o potwierdzenie.

Składnia:

```bash
php artisan svarium:attribute.remove {--from=} {--module=} {--field=*} {--force}
```

Przykłady:

```bash
php artisan svarium:attribute.remove
php artisan svarium:attribute.remove --from=global --field=email
php artisan svarium:attribute.remove --module=Patient --field=last_name --force
php artisan svarium:attribute.remove --module=Patient --field=last_name --field=email --force
```

### `svarium:translation.add`

Dodaje tłumaczenie do:

- globalnego pliku: `app/Svarium/Lang/{locale}/messages.php`,
- albo modułu: `app/Svarium/Modules/{Module}/Lang/{locale}/messages.php`.

Po zapisie komenda automatycznie uruchamia:

- `svarium:lang.prepare {locale}`
- `svarium:lang.merge {locale}`

Flow interaktywny (bez `--locale`):

1. `Gdzie dodać tłumaczenie`
2. `Podaj klucz`
3. Jeśli klucz istnieje: pytanie o nadpisanie
4. Pętla po wszystkich językach:
`Wprowadź tłumaczenie ({key}) dla PL/DE/EN/...`

Jeśli wartość dla danego języka zostanie pusta, komenda zapisze jako wartość sam klucz.

Flagi:

- `--locale=` - kod języka, np. `pl` (gdy podany, komenda działa tylko dla tego locale)
- `--module=` - nazwa modułu (jeśli brak, możesz wybrać global/moduł interaktywnie)
- `--key=` - klucz tłumaczenia
- `--value=` - wartość tłumaczenia

Składnia:

```bash
php artisan svarium:translation.add {--locale=} {--module=} {--key=} {--value=}
```

Przykłady:

```bash
php artisan svarium:translation.add
php artisan svarium:translation.add --locale=pl --key="Create account" --value="Utwórz konto"
php artisan svarium:translation.add --locale=pl --module=Patient --key="Patient list" --value="Lista pacjentów"
```

### `svarium:translation.move`

Przenosi tłumaczenia między lokalizacjami (global/moduł) dla wybranego locale.

Flow interaktywny:

- `Wybierz język`,
- `Skąd chcesz przenieść tłumaczenie`,
- `Dokąd chcesz przenieść tłumaczenie` (bez źródła),
- `Wybierz klucze do przeniesienia`,
- `Czy usunąć z miejsca źródłowego`.

Po zapisie komenda automatycznie uruchamia:

- `svarium:lang.prepare {locale}`
- `svarium:lang.merge {locale}`

Flagi:

- `--locale=` - kod języka, np. `pl`
- `--from=` - `global` albo nazwa modułu
- `--to=` - `global` albo nazwa modułu
- `--key=*` - klucz tłumaczenia (można podać wiele razy)
- `--delete-source` - usuwa przeniesione wpisy ze źródła

Składnia:

```bash
php artisan svarium:translation.move {--locale=} {--from=} {--to=} {--key=*} {--delete-source}
```

Przykłady:

```bash
php artisan svarium:translation.move
php artisan svarium:translation.move --locale=pl --from=Patient --to=global --key="Patient list" --delete-source
php artisan svarium:translation.move --locale=pl --from=global --to=Patient --key="Create account" --key="Edit"
```

### `svarium:translation.remove`

Usuwa tłumaczenia z globalnego pliku lub modułu dla wybranego locale.

Flow interaktywny:

- `Wybierz język`,
- `Skąd chcesz usunąć tłumaczenia`,
- `Wybierz klucze do usunięcia`,
- potwierdzenie usunięcia.

Po zapisie komenda automatycznie uruchamia:

- `svarium:lang.prepare {locale}`
- `svarium:lang.merge {locale}`

Flagi:

- `--locale=` - kod języka, np. `pl`
- `--from=` - `global` albo nazwa modułu
- `--module=` - alias dla `--from` (tylko moduł)
- `--key=*` - klucz tłumaczenia (można podać wiele razy)
- `--force` - usuwa bez potwierdzenia

Składnia:

```bash
php artisan svarium:translation.remove {--locale=} {--from=} {--module=} {--key=*} {--force}
```

Przykłady:

```bash
php artisan svarium:translation.remove
php artisan svarium:translation.remove --locale=pl --from=global --key="Create account"
php artisan svarium:translation.remove --locale=pl --module=Patient --key="Patient list" --key="Edit" --force
```

### `svarium:permission`

Interaktywne tworzenie bazowych ról/uprawnień.

```bash
php artisan svarium:permission
```

### `svarium:permission.sync`

Synchronizuje permissiony Svarium na podstawie:

- zasobów (`Resource`)
- zwykłych operation (`Operation`)

Komenda:

- tworzy brakujące rekordy w tabeli `permissions`,
- pomija resource operations i auth operations,
- czyści cache permissionów Spatie po synchronizacji.

Składnia:

```bash
php artisan svarium:permission.sync {--guard=*}
```

Przykłady:

```bash
php artisan svarium:permission.sync
php artisan svarium:permission.sync --guard=web
php artisan svarium:permission.sync --guard=web --guard=api
```

To jest właściwa komenda do rejestracji katalogu permissionów po:

- dodaniu nowych modułów,
- dodaniu nowych operation,
- zmianie wbudowanych modułów `User` / `Role`.

### `svarium:role.module`

Przypisuje wszystkie permissiony modułu do roli lub je odbiera.

Jak działa:

- zbiera permissiony modułu z:
  - resource permissionów (`resource.{resource}.{action}`),
  - operation permissionów (`operation.{...}`),
- działa na wybranym guardzie,
- ma tryb podglądu (`--list`) bez zmian w bazie.

Składnia:

```bash
php artisan svarium:role.module {--target=} {--role=} {--module=} {--guard=} {--action=} {--revoke} {--list}
```

Flow interaktywny (bez flag):

1. Dodaj/Odbierz dostęp
2. Cel dostępu: `Rola` albo `Użytkownik`
3. Dla `Rola`: wybór roli i modułu:
- dla `Dodaj` tylko moduły, których rola jeszcze nie ma,
- dla `Odbierz` tylko moduły już przypisane do roli.
4. Dla `Użytkownik`: komenda przełącza flow na `svarium:user.access`.

Przykłady:

```bash
# podgląd permissionów modułu
php artisan svarium:role.module --module=patient --list

# przypisanie wszystkich permissionów modułu patient do roli admin
php artisan svarium:role.module --role=admin --module=patient --guard=web

# odebranie permissionów modułu patient od roli admin
php artisan svarium:role.module --role=admin --module=patient --revoke

# uruchomienie flow użytkownika z tej komendy
php artisan svarium:role.module --target=user
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

### `svarium:user.access`

Przypisuje albo odbiera użytkownikowi:

- role (`role`)
- bezpośrednie uprawnienia (`permission`)

Flow interaktywny (bez flag):

1. Dodaj/Odbierz dostęp
2. Rola/Uprawnienie
3. Guard
4. Użytkownik (`ID` albo `email`)
5. Wybór elementów do przypisania/odebrania

Dla typu `Uprawnienie` lista zawiera także grupy modułowe (wildcard), np.:

- `resource.customer.*`
- `operation.customer.*`

Wybranie wildcardu przypisuje/odbiera cały zestaw uprawnień z tej grupy.
Wildcard działa także dla nowych akcji dodanych później (np. nowe route/operation w module) bez ponownej edycji przypisań.

Składnia:

```bash
php artisan svarium:user.access \
  {--user=} {--action=} {--type=} {--guard=} \
  {--role=*} {--permission=*}
```

Przykłady:

```bash
# interaktywnie
php artisan svarium:user.access

# przypisz role użytkownikowi
php artisan svarium:user.access --user=1 --action=add --type=role --guard=web --role=admin

# odbierz bezpośrednie uprawnienia użytkownikowi
php artisan svarium:user.access --user=jan@example.com --action=revoke --type=permission --permission=operation.patient.index
```

### `svarium:diagnose.database.connection`

Diagnozuje połączenie DB dla wybranego modelu:

- możesz wskazać model przez `--model={key}` (np. `user`) albo pełną klasę modelu,
- bez `--model` komenda działa interaktywnie i pokazuje listę modeli z `config('upsoftware.models')`,
- wynik zawiera m.in.:
  - klucz modelu,
  - klasę modelu,
  - tabelę,
  - nazwę połączenia z modelu i nazwę finalnie rozwiązaną,
  - `driver`, `host`, `port`, `database`, `username`, `password`.

Składnia:

```bash
php artisan svarium:diagnose.database.connection {--model=}
```

Przykłady:

```bash
php artisan svarium:diagnose.database.connection
php artisan svarium:diagnose.database.connection --model=user
php artisan svarium:diagnose.database.connection --model="Upsoftware\\Svarium\\Models\\User"
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

### `svarium:make.notification`

Tworzy nową klasę Notification w jednym z dwóch miejsc:

- globalnie: `app/Svarium/Notifications`
- modułowo: `app/Svarium/Modules/{Module}/Notifications`

Interaktywnie komenda pyta, gdzie zapisać plik (katalog ogólny Svarium lub wybrany moduł).

Składnia:

```bash
php artisan svarium:make.notification {name?} {--module=} {--force}
```

Przykłady:

```bash
php artisan svarium:make.notification UserRegisteredNotification
php artisan svarium:make.notification SendCodeNotification --module=Patient
php artisan svarium:make.notification SendCodeNotification --module=Patient --force
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
- domyślny filtr tenantów po `APP_ENV` (kolumna `tenants.env`),
- `--all` (pomija filtr środowiska i uruchamia dla wszystkich tenantów),
- `--seed` + `--seeder`,
- `--tenant=*` (w trybie `database`),
- `--path=*` (nadpisanie ścieżek).

Składnia:

```bash
php artisan svarium:tenant.migrate {--tenant=*} {--fresh} {--rollback} {--step=1} {--all} {--seed} {--seeder=*} {--path=*} {--force}
```

Przykłady:

```bash
php artisan svarium:tenant.migrate
php artisan svarium:tenant.migrate --tenant=1 --tenant=2
php artisan svarium:tenant.migrate --fresh --seed
php artisan svarium:tenant.migrate --rollback --step=2
php artisan svarium:tenant.migrate --all
```

### `svarium:tenant.migrate.rollback`

Skrót rollbacku dla migracji tenancy.

Składnia:

```bash
php artisan svarium:tenant.migrate.rollback {--tenant=*} {--step=1} {--all} {--path=*} {--force}
```

Przykłady:

```bash
php artisan svarium:tenant.migrate.rollback
php artisan svarium:tenant.migrate.rollback --tenant=5 --step=3
php artisan svarium:tenant.migrate.rollback --all
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
