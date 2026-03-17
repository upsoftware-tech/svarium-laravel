# Rejestracja menu (moduły i strony)

Svarium pozwala rejestrować pozycje menu bezpośrednio w kodzie modułów i stron (Operation).

Najważniejsze:
- jeden moduł może dodać wiele wpisów menu,
- można budować drzewo wielopoziomowe przez `path`,
- można budować stabilne drzewo po identyfikatorach przez `pathId` / `parent` (niezależnie od tłumaczeń),
- wpisy są rejestrowane runtime (bez ręcznego klikania w panelu),
- menu panelu może działać bez tabeli `navigations` przez komponent `PanelNavigation`.

## PanelNavigation (bez tabeli `navigations`)

`PanelNavigation` buduje drzewo wyłącznie z wpisów zarejestrowanych w modułach/stronach.

Przykład (sidebar, pionowo):

```php
use Upsoftware\Svarium\UI\Components\PanelNavigation;

PanelNavigation::make()->vertical();
```

Przykład (górna belka, poziomo):

```php
PanelNavigation::make()->horizontal();
```

Przykład (sidebar, pionowo, konkretna gałąź po kluczu):

```php
PanelNavigation::make()->vertical('main_menu');
```

Możesz też wskazać konkretny root navigation id:

```php
PanelNavigation::make()->vertical()->navigationId(1);
```

Komponent automatycznie renderuje:
- `NavigationVertical` albo
- `NavigationHorizontal`.

## Gdzie wstawić w panelu

Najczęściej:
- sidebar: `->sidebar(PanelNavigation::make()->vertical())`
- header/topbar: `->header(PanelNavigation::make()->horizontal())`

Przykład w `app/Svarium/panels.php`:

```php
<?php

use App\Svarium\Layouts\AdminLayout;
use Upsoftware\Svarium\Panel\Panel;
use Upsoftware\Svarium\UI\Components\PanelNavigation;

return [
    Panel::make('app')
        ->noPrefix()
        ->layout(AdminLayout::class)
        ->sidebar(
            PanelNavigation::make()->vertical()
        )
        ->header(
            PanelNavigation::make()->horizontal()
        ),
];
```

## Źródło danych menu

`PanelNavigation` korzysta z wpisów rejestrowanych runtime przez:
- `Module::menu()`
- `Operation::menu()`
- helper `register_menu([...])`

Tabela `navigations` nie jest wymagana dla tego komponentu.

## Kiedy użyć `NavigationVertical`, a kiedy `PanelNavigation`

- `NavigationVertical::make($navigationId)`:
  - używa istniejącego drzewa nawigacji (np. z `navigation_id`),
  - scenariusz: chcesz czytać menu z zapisanej konfiguracji.

- `PanelNavigation::make()`:
  - buduje menu automatycznie z modułów/stron,
  - scenariusz: panel ma być deklaratywny i utrzymywany w kodzie.

## Struktura drzewa

Drzewo budujesz przez `path`:

```php
['Menu 1', 'Podmenu 1.1', 'Podmenu 1.1.1']
```

albo skrótem tekstowym:

```php
'Menu 1/Podmenu 1.1/Podmenu 1.1.1'
```

## Stabilne ID gałęzi (zalecane)

Jeśli etykiety są tłumaczone (`Ustawienia` / `Settings` / `Einstellungen`), budowanie tylko po `path` może tworzyć duplikaty gałęzi.

Dlatego zalecane jest użycie identyfikatorów technicznych:

- `->id('ksef')` - alias do `->pathId('ksef')`,
- `->pathId('ksef')` - ID bieżącego węzła,
- `->parent('ksef')` - rodzic po ID (bez zależności od label),
- `->pathIds(['settings', 'ksef'])` - pełna ścieżka ID,
- `->path([...])` zostaje do etykiet i prezentacji.

Przykład:

```php
MenuItem::make()
    ->id('ksef')
    ->label('KSeF')
    ->icon('lucide:file-check')
    ->order(36);

MenuItem::make('Połączenie i certyfikaty')
    ->parent('ksef')
    ->pathId('connection_certificates')
    ->url(panel_href('ksef/connection-certificates'))
    ->order(1);
```

Jeśli używasz:

```php
MenuItem::make()
    ->label('Rentals United')
    ->id('rentalsunited')
```

to w menu pokazany zostanie literalny label `Rentals United`.
`id('rentalsunited')` pozostaje technicznym identyfikatorem węzła i nie nadpisuje etykiety.

Przykład z `pathIds`:

```php
MenuItem::make('Szablony mailowe')
    ->path([__('Ustawienia')])
    ->pathIds(['settings', 'mail_templates'])
    ->url(panel_href('system-mail-templates'));
```

Ważne:
- `pathId`/`parent`/`pathIds` są opcjonalne.
- Dotychczasowe `path([...])` działa bez zmian.
- ID powinny być techniczne: małe litery, bez tłumaczeń, np. `settings`, `ksef`, `mail_templates`.

## Moduł: `menu(): array`

W module dodaj metodę `menu()`:

```php
<?php

namespace App\Svarium\Modules\Page;

use Upsoftware\Svarium\Menu\MenuItem;
use Upsoftware\Svarium\Modules\Module;

class PageModule extends Module
{
    public function name(): string
    {
        return 'Page';
    }

    public function menu(): array
    {
        return [
            MenuItem::make('Pages')
                ->url('/pages')
                ->icon('lucide:file')
                ->path(['Menu 1', 'Podmenu 1.1']),

            // ten sam moduł w drugiej gałęzi menu
            MenuItem::make('Pages')
                ->url('/pages')
                ->path(['Menu 2']),
        ];
    }
}
```

## Strona (Operation): `public static function menu(): array`

W klasie operation możesz dodać wpisy tak samo:

```php
public static function menu(): array
{
    return [
        \Upsoftware\Svarium\Menu\MenuItem::make('Dashboard')
            ->url('/dashboard')
            ->path(['Menu 1']),
    ];
}
```

## Typy wpisów

- `item` (domyślny): klikana pozycja,
- `label`: etykieta grupy,
- `separator`: separator.

Przykład:

```php
use Upsoftware\Svarium\Menu\MenuItem;

return [
    MenuItem::labelItem('Sekcja CMS')->path(['Menu 1']),
    MenuItem::separator()->path(['Menu 1']),
    MenuItem::make('Pages')->url('/pages')->path(['Menu 1']),
];
```

## Rejestracja przez helper

Możesz też rejestrować pozycje ręcznie:

```php
register_menu([
    ['label' => 'Pages', 'url' => '/pages', 'path' => ['Menu 1', 'Podmenu 1.1']],
]);
```

Wariant z przypisaniem do konkretnej nawigacji:

```php
register_menu([
    ['label' => 'Pages', 'url' => '/pages', 'path' => ['CMS']],
], navigationId: 1, source: 'custom');
```

## API `MenuItem`

- `MenuItem::make('Label')`
- `MenuItem::make()` + `->label('Label')`
- `->routeName('route.name')` albo `->url('/path')`
- `->icon('lucide:home')`
- `->path([...])` / `->under([...])`
- `->id('ksef')` (alias do `pathId`)
- `->pathId('ksef')` / `->pathKey('ksef')` (ID bieżącego węzła)
- `->parent('ksef')` (rodzic po technicznym ID)
- `->pathIds([...])` / `->pathKeys([...])` (ścieżka technicznych ID)
- `->order(10)`
- `->permission('resource.patient.list')` (opcjonalnie, wymusza widoczność wg konkretnego permission)
- `->navigation(1)` (opcjonalnie przypisanie do konkretnego root navigation)
- `->menu('main_menu')` (alias do `navigation`, wygodny dla kluczy tekstowych)
- `->children([...])` (zagnieżdżanie)

Przykład przypisania wpisu do konkretnego klucza menu:

```php
MenuItem::make('Lista pacjentów')
    ->url(module_route('patient'))
    ->path(['Pacjenci'])
    ->menu('main_menu');
```

## Rejestracja modułu z zewnętrznej wtyczki

Jeśli tworzysz moduł w osobnym pakiecie, najwygodniej zarejestrować go przez `SvariumPluginServiceProvider`.

Przykład:

```php
<?php

namespace Vendor\Package\Providers;

use Upsoftware\Svarium\Providers\SvariumPluginServiceProvider;
use Vendor\Package\Modules\Regions\RegionsModule;

class PackageServiceProvider extends SvariumPluginServiceProvider
{
    public function boot(): void
    {
        $this->registerSvariumModule(RegionsModule::class);
    }
}
```

Helper:

- tworzy instancję modułu,
- rejestruje go w `ModuleRegistry`,
- odpala `register()` / `boot()`,
- rejestruje menu modułu,
- rejestruje operacje z katalogu `Panel`.

## Widoczność wg uprawnień

Menu runtime jest automatycznie filtrowane po dostępie użytkownika:

- jeśli `MenuItem` ma `routeName(...)`, Svarium sprawdza docelową operation i jej `authorize(...)`,
- jeśli `MenuItem` ma `permission(...)`, sprawdzany jest bezpośrednio ten permission,
- jeśli użytkownik nie ma dostępu, wpis nie jest renderowany,
- puste grupy (bez widocznych dzieci) są usuwane automatycznie.

Przykład:

```php
MenuItem::make('Użytkownicy')
    ->routeName('module:user')
    ->permission('resource.user.list');
```

## Debug mapy menu

Do podglądu całej struktury (z ID i path_id) użyj:

```bash
php artisan svarium:menu.map
```

Warianty:

```bash
# tylko konkretna nawigacja (np. dropdown użytkownika)
php artisan svarium:menu.map --navigation=sidebar_user

# wygodny format do kopiowania / automatyzacji
php artisan svarium:menu.map --json
```
