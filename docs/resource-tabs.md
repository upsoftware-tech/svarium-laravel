# Taby formularza w `Resource`

Svarium wspiera zakładki formularza dla ekranów:

- `create`
- `edit`

Zakładki mogą działać w dwóch trybach:

- lokalne: zmieniają tylko content w DOM, bez zmiany URL,
- routowane: mają własny URL, np. `/users/{id}/edit/basic`.

Ten dokument opisuje cały flow: od najprostszego wdrożenia do gotowego modułu, przez wspólne taby `create/edit`, aż po routed tabs z własną `Operation`.

## 1. Kiedy używać tabów

Taby warto wprowadzić, gdy:

- formularz zaczyna być długi,
- chcesz podzielić pola na sekcje,
- chcesz osobno grupować logikę formularza,
- część sekcji ma mieć osobne uprawnienia albo osobny URL.

Jeśli formularz ma 2-4 pola, zwykle taby nie są potrzebne.

## 2. Dwa tryby tabów

### Taby lokalne

Tab lokalny:

- nie zmienia URL,
- przełącza content po stronie Vue,
- działa jak klasyczne zakładki,
- nadaje się do zwykłego formularza `edit/create`,
- używa `->schema(...)` lub `->content(...)`.

### Taby routowane

Tab routowany:

- zmienia URL,
- może mieć własną `Operation`,
- może mieć własne `authorize()`, `schema()`, `rules()`, `save()`,
- nadaje się do dużych formularzy administracyjnych,
- używa `->operation(...)` albo `->url(...)`.

## 3. API w `Resource`

W klasie zasobu możesz dodać:

```php
use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Resource\ResourceFormTab;

public function formTabs(PanelContext $context, ?Model $record = null): array
{
    return [];
}

public function createTabs(PanelContext $context): array
{
    return [];
}

public function editTabs(PanelContext $context, Model $record): array
{
    return [];
}

public function formTabPosition(PanelContext $context, ?Model $record = null): string
{
    return 'top'; // lub 'left'
}
```

Możesz też użyć pełniejszej konfiguracji:

```php
public function formConfig(PanelContext $context, ?Model $record = null): array
{
    return [
        'tab' => [
            'position' => 'left',
            'variant' => 'simple',
            'title' => true,
        ],
        'language' => [
            'display' => 'select',
            'multiple' => true,
            'showIcon' => true,
            'showLabel' => true,
        ],
    ];
}
```

Obsługiwane pozycje:

- `top`
- `right`
- `bottom`
- `left`
- `vertical` = alias `left`
- `horizontal` = alias `top`

Obsługiwane warianty:

- `default` - obecny domyślny wygląd
- `simple` - uproszczone zakładki

Opcja `tab.title`:

- `true` - pokazuje stały tytuł nad tabami,
- `false` - ukrywa automatyczny tytuł nad tabami.

Gdy `tab.title = true`, tytuł bierze się domyślnie z:

- `createTitle()` dla `create`
- `editTitle()` dla `edit`

Konfiguracja `language` jest używana przez zasoby, które renderują wybór języków jako część formularza, np. wbudowany moduł `Role`:

- `display = inline` - języki jako lista inline/checklist
- `display = select` - języki jako `Select`
- `multiple = true|false` - dla `display = select` i `display = inline`
- `showIcon = true|false` - pokazuje flagę/ikonę w `LocaleInline` (inline)
- `showLabel = true|false` - pokazuje etykietę języka w `LocaleInline` (inline)

## 4. Rekomendowany wzorzec: `formTabs()`

Najprostszy i rekomendowany wariant:

- definiujesz zakładki raz w `formTabs(...)`,
- Svarium automatycznie użyje ich w `create` i `edit`,
- jeśli potrzebujesz różnicy tylko dla jednego ekranu, nadpisujesz `createTabs()` albo `editTabs()`.

### Domyślne zachowanie

W rdzeniu `Resource` działa teraz tak:

```php
public function formTabs(PanelContext $context, ?Model $record = null): array
{
    return [];
}

public function createTabs(PanelContext $context): array
{
    return $this->formTabs($context);
}

public function editTabs(PanelContext $context, Model $record): array
{
    return $this->formTabs($context, $record);
}
```

Czyli:

- jeśli zdefiniujesz tylko `formTabs(...)`, masz te same taby w `create` i `edit`,
- możesz używać `$record === null` jako sygnału, że jesteś na `create`.

## 5. Najprostsze wdrożenie do gotowego modułu

Załóżmy, że masz już gotowy resource:

- `App\Svarium\Modules\Patient\Panel\PatientResource`

### Krok 1. Dodaj importy

```php
use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Resource\ResourceFormTab;
use Upsoftware\Svarium\UI\Components\Form\Input;
use Upsoftware\Svarium\UI\Components\Form\Textarea;
```

### Krok 2. Dodaj `formTabs(...)`

```php
public function formTabs(PanelContext $context, ?Model $record = null): array
{
    return [
        ResourceFormTab::make('basic')
            ->label(__('Basic'))
            ->default()
            ->schema([
                Input::make('title')->label(__('Title'))->required(),
                Input::make('slug')->label(__('Slug'))->required(),
            ]),

        ResourceFormTab::make('details')
            ->label(__('Details'))
            ->schema([
                Textarea::make('content')->label(__('Content')),
            ]),
    ];
}
```

### Krok 3. Opcjonalnie ustaw pozycję zakładek

```php
public function formTabPosition(PanelContext $context, ?Model $record = null): string
{
    return 'top';
}
```

### Krok 4. Gotowe

Jeśli nie nadpisujesz `createTabs()` ani `editTabs()`, to:

- `create` użyje `formTabs($context)`
- `edit` użyje `formTabs($context, $record)`

## 6. Jak zrobić te same taby w `create` i `edit`

To jest właśnie przypadek dla `formTabs(...)`.

Przykład:

```php
public function formTabs(PanelContext $context, ?Model $record = null): array
{
    return [
        ResourceFormTab::make('basic')
            ->label(__('Basic'))
            ->default()
            ->schema([
                Input::make('title')->label(__('Title'))->required(),
                Input::make('slug')->label(__('Slug'))->required(),
            ]),

        ResourceFormTab::make('details')
            ->label(__('Details'))
            ->schema([
                Textarea::make('content')->label(__('Content')),
            ]),
    ];
}
```

To da identyczne zakładki na:

- `create`
- `edit`

## 7. Jak zrobić prawie te same taby, ale różne dla `create` albo `edit`

### Wariant A: użyj warunku w `formTabs(...)`

```php
public function formTabs(PanelContext $context, ?Model $record = null): array
{
    return [
        ResourceFormTab::make('basic')
            ->label(__('Basic'))
            ->default()
            ->schema([
                Input::make('title')->label(__('Title'))->required(),
                Input::make('slug')->label(__('Slug'))->required(),
            ]),

        ResourceFormTab::make('details')
            ->label(__('Details'))
            ->schema([
                Textarea::make('content')->label(__('Content')),
                Input::make('updated_at')
                    ->label(__('Updated at'))
                    ->disabled()
                    ->value(optional($record?->updated_at)->format('Y-m-d H:i:s') ?? '')
                    ->if($record !== null),
            ]),
    ];
}
```

### Wariant B: nadpisz tylko `editTabs()`

```php
public function editTabs(PanelContext $context, Model $record): array
{
    $tabs = $this->formTabs($context, $record);

    $tabs[] = ResourceFormTab::make('history')
        ->label(__('History'))
        ->schema([
            Text::make(__('Only on edit view')),
        ]);

    return $tabs;
}
```

### Wariant C: nadpisz tylko `createTabs()`

```php
public function createTabs(PanelContext $context): array
{
    $tabs = $this->formTabs($context);

    $tabs[] = ResourceFormTab::make('intro')
        ->label(__('Intro'))
        ->schema([
            Text::make(__('Only on create view')),
        ]);

    return $tabs;
}
```

## 8. `ResourceFormTab` - pełne API

Najważniejsze metody:

- `ResourceFormTab::make('basic')`
- `->label('Podstawowe')`
- `->title('Dane podstawowe')`
- `->subtitle('Główne pola rekordu')`
- `->action(Button::make('Dodaj'))`
- `->icon('lucide:user')`
- `->badge('3')`
- `->default()`
- `->schema([...])`
- `->content([...])` - alias dla `schema(...)`
- `->operation(EditBasicTabOperation::class)` - routed tab z delegacją do operation
- `->url('/custom/path')` - routed tab pod własny URL
- `->routed(true|false)`
- `->visible(fn (PanelContext $context, ...$args) => true|false)`

`title()`, `subtitle()` i `action()` są opcjonalne. Są używane tylko przez wrapper contentu zakładki formularza. Jeśli ich nie ustawisz, tab działa normalnie, ale bez nagłówka wewnątrz contentu.

`action()` renderuje prawą część nagłówka taba. Może przyjąć:

- komponent, np. `Button::make(...)`
- tablicę komponentów
- string, wtedy zostanie zamieniony na `Button::make('...')`
- closure zwracające jedną z powyższych wartości

### `->default()`

Oznacza aktywną zakładkę domyślną, jeśli URL nie wskazuje konkretnej.

Jeśli żadna zakładka nie ma `->default()`, aktywna będzie pierwsza.

## 9. Taby lokalne

Przykład:

```php
public function editTabs(PanelContext $context, Model $record): array
{
    return [
        ResourceFormTab::make('basic')
            ->label(__('Podstawowe'))
            ->title(__('Dane podstawowe'))
            ->subtitle(__('Główne pola rekordu'))
            ->action(Button::make(__('Dodaj')))
            ->default()
            ->schema([
                Input::make('name')->label(__('Nazwa')),
                Input::make('email')->label(__('E-mail')),
            ]),

        ResourceFormTab::make('settings')
            ->label(__('Ustawienia'))
            ->schema([
                Toggle::make('active')->label(__('Aktywny')),
            ]),
    ];
}
```

W tym trybie:

- URL zostaje standardowy, np. `/users/{id}/edit`,
- zakładki przełączają content lokalnie,
- nie ma `router.visit()`,
- submit zapisuje bieżący formularz jak zwykle.

### Kiedy używać local tabs

Używaj, gdy:

- formularz jest jeden logicznie,
- zakładki są tylko podziałem wizualnym,
- nie potrzebujesz osobnych URL,
- nie potrzebujesz osobnych permissionów per zakładka.

## 10. Taby routowane

Przykład:

```php
public function editTabs(PanelContext $context, Model $record): array
{
    return [
        ResourceFormTab::make('basic')
            ->label(__('Podstawowe'))
            ->default()
            ->operation(EditUserBasicTabOperation::class),

        ResourceFormTab::make('permissions')
            ->label(__('Uprawnienia'))
            ->operation(EditUserPermissionsTabOperation::class),
    ];
}

public function formTabPosition(PanelContext $context, ?Model $record = null): string
{
    return 'left';
}
```

W tym trybie Svarium automatycznie rejestruje:

- `/{slug}/create/{tab}`
- `/{slug}/{id}/edit/{tab}`

Czyli przykładowo:

- `/users/create/basic`
- `/users/abc123/edit/basic`
- `/users/abc123/edit/permissions`

### Kiedy używać routed tabs

Używaj, gdy:

- formularz jest duży,
- każda zakładka ma osobną logikę,
- potrzebujesz osobnych permissionów,
- chcesz mieć deep-link do zakładki,
- chcesz budować podsekcje jako osobne `Operation`.

## 11. Delegacja do `Operation`

Jeśli tab ma `->operation(...)`, Svarium deleguje do tej operacji:

- `authorize()`
- `schema()`
- `rules()`
- komunikaty walidacji
- atrybuty walidacji
- `save()`

To pozwala budować duże formularze jako zestaw mniejszych, samodzielnych podoperacji.

Przykład:

```php
namespace App\Svarium\Modules\User\Panel\Tabs;

use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\UI\Components\Form\Input;

class EditUserBasicTabOperation extends Operation
{
    public function execution(): ExecutionMode
    {
        return ExecutionMode::FORM;
    }

    protected function schema(PanelContext $context, Model $record): array
    {
        return [
            Input::make('name')->label(__('Name'))->required(),
            Input::make('email')->label(__('E-mail'))->required()->email(),
        ];
    }

    protected function save(PanelContext $context, Model $record): RedirectResult
    {
        $record->fill($context->validated())->save();

        return RedirectResult::to(route('module:user.edit.tab', [
            'id' => $record->getKey(),
            'tab' => 'basic',
        ]))->success(__('Saved'));
    }
}
```

## 12. Pozycja tabów

```php
public function formTabPosition(PanelContext $context, ?Model $record = null): string
{
    return 'top';
}
```

Dozwolone wartości:

- `top`
- `left`

### Rekomendacja

- `top`:
  - krótsze formularze,
  - 2-4 zakładki,
  - typowe CRUD
- `left`:
  - duże formularze,
  - wiele sekcji,
  - administracja i konfiguracja

## 13. Linki do routed tabów

Jeśli chcesz zbudować link do routed taba, możesz użyć:

```php
module_route('user', 'edit/basic', $record->getKey())
module_route('user', 'create/basic')
```

To wygeneruje odpowiednio:

- `admin/users/{id}/edit/basic`
- `admin/users/create/basic`

albo bez prefixu panelu, jeśli panel ma `->noPrefix()`.

Jeśli masz zarejestrowany alias trasy modułu, możesz też użyć:

```php
route('module:user.edit.tab', [
    'id' => $record->getKey(),
    'tab' => 'basic',
])
```

## 14. Co dzieje się przy błędach

- jeśli routed tab wskazuje nieistniejący slug:
  - `404`
- jeśli routed tab nie przejdzie `authorize()`:
  - `403`
- jeśli nie zdefiniujesz tabów:
  - `Resource` działa jak dotychczas

## 15. Wdrożenie krok po kroku do gotowego modułu

### Minimalny plan wdrożenia

1. Otwórz istniejący `Resource`
2. Dodaj import `ResourceFormTab`
3. Dodaj metodę `formTabs(...)`
4. Przenieś pola do `->schema([...])`
5. Dodaj `formTabPosition(...)`
6. Sprawdź ekran `create`
7. Sprawdź ekran `edit`
8. Jeśli potrzebujesz różnic:
   - nadpisz `createTabs()` albo `editTabs()`
9. Jeśli potrzebujesz URL i osobnej logiki:
   - zamień wybraną zakładkę na `->operation(...)`

### Najbezpieczniejsza ścieżka

Najpierw:

- wdrażasz local tabs przez `formTabs(...)`

Dopiero potem:

- wybrane sekcje przenosisz do routed tabs

To minimalizuje ryzyko regresji w istniejącym formularzu.

## 16. Gotowy przykład w projekcie

Masz wdrożony moduł demonstracyjny:

- [DemoTabsResource.php](/Volumes/Workspace/Projekty/upsoftware-tech/packages/laravel/svarium/app/Svarium/Modules/DemoTabs/Panel/DemoTabsResource.php)
- [EditDemoTabsBasicOperation.php](/Volumes/Workspace/Projekty/upsoftware-tech/packages/laravel/svarium/app/Svarium/Modules/DemoTabs/Panel/Tabs/EditDemoTabsBasicOperation.php)
- [EditDemoTabsDetailsOperation.php](/Volumes/Workspace/Projekty/upsoftware-tech/packages/laravel/svarium/app/Svarium/Modules/DemoTabs/Panel/Tabs/EditDemoTabsDetailsOperation.php)

Ten przykład pokazuje oba tryby:

- `create`: local tabs
- `edit`: routed tabs

## 17. Podsumowanie rekomendacji

Najlepszy standard dla modułów:

1. zacznij od `formTabs(...)`
2. używaj local tabs jako domyślnego podziału formularza
3. przechodź na routed tabs tylko tam, gdzie naprawdę jest osobna logika
4. dla dużych ekranów ustaw `left`
5. dla zwykłych CRUD ustaw `top`
