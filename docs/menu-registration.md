# Rejestracja menu (moduły i strony)

Svarium pozwala rejestrować pozycje menu bezpośrednio w kodzie modułów i stron (Operation).

Najważniejsze:
- jeden moduł może dodać wiele wpisów menu,
- można budować drzewo wielopoziomowe przez `path`,
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

Możesz też wskazać konkretny root navigation id:

```php
PanelNavigation::make('vertical', 1);
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
- `->routeName('route.name')` albo `->url('/path')`
- `->icon('lucide:home')`
- `->path([...])` / `->under([...])`
- `->order(10)`
- `->navigation(1)` (opcjonalnie przypisanie do konkretnego root navigation)
- `->children([...])` (zagnieżdżanie)
