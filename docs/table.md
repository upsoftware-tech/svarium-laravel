# Tabela (`TableBuilder`) - pełna dokumentacja

Ten dokument opisuje kompletną konfigurację tabeli w Svarium Laravel:

- budowę tabeli z `TableBuilder`,
- kolumny (`Column`),
- akcje wiersza (`Action`) i akcje masowe (`BulkAction`),
- filtry (`Column::filter()` i `Filter::make()`),
- wyszukiwarkę (`InputSearch`, `DropdownSearch`),
- sticky, selekcję, paginację, tabs.

## Szybki start

```php
use App\Models\Page;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\Table\Action;
use Upsoftware\Svarium\UI\Components\Table\Column;
use Upsoftware\Svarium\UI\Components\Search\InputSearch;

public function table(): TableBuilder
{
    return TableBuilder::make(Page::query())
        ->columns([
            Column::make('id')->label('ID')->sortable(),
            Column::make('title')->label('Title')->sortable()->searchable(),
            Column::make('created_at')->label('Created')->dateTime('Y-m-d H:i'),
        ])
        ->searchbar([
            InputSearch::make('q')->placeholder(__('Search...')),
        ])
        ->actions([
            Action::view(),
            Action::edit(),
            Action::delete(),
        ])
        ->bulk('multiple');
}
```

## Globalna konfiguracja (`config/upsoftware.php`)

Sekcja:

```php
'table' => [
    'action_display' => 'inline',
    'pagination' => [
        'enabled' => true,
        'rowsPerPageOptions' => [10, 20, 30, 50, 100, 0],
        'rowsPerPage' => 50,
        'rowsPerPageLabel' => __('Rows per page'),
        'rowsPerPageAllLabel' => null,
        'paginationLabel' => null,
        'showButtonLabel' => true,
        'showFirstLabel' => true,
        'showLastLabel' => true,
        'ellipsisAfter' => 7,
        'firstButtonLabel' => __('First'),
        'previousButtonLabel' => __('Previous'),
        'nextButtonLabel' => __('Next'),
        'lastButtonLabel' => __('Last'),
    ],
    'condensed' => false,
    'searchbar' => false,
    'exported' => true,
    'imported' => true,
],
```

Znaczenie:

- `action_display`: domyślny tryb akcji (`inline` lub `dropdown`).
- `pagination`: pełna konfiguracja paginacji i etykiet.
- `condensed`: domyślna kondensacja tabel (`false` = standardowe odstępy).
- `searchbar`: automatyczne dodanie `InputSearch::make('q')` do każdej tabeli.
- `exported`: domyślna widoczność przycisku eksportu.
- `imported`: domyślna widoczność przycisku importu.

## Wyszukiwarka `q` (globalnie + per tabela)

### 1. Globalnie

Jeśli `upsoftware.table.searchbar = true`, tabela automatycznie dostaje:

```php
InputSearch::make('q')->placeholder(__('Search...'))
```

### 2. Per tabela (override)

```php
->showInputSearchInSidebar(true)  // lub false
->pokazCzyMaBycInputSearchWSidebarze(true) // alias PL
```

### 3. Brak duplikatów

Jeśli auto-searchbar jest włączony i sam zdefiniujesz `InputSearch::make('q')`, drugi nie zostanie dodany.

### 4. Parametr request

Backend wyszukiwania czyta:

1. `q` (preferowany),
2. `search` (fallback kompatybilności).

Czyli oba URL działają:

- `?q=test`
- `?search=test`

## Pełne API `TableBuilder` (fluent)

### Dane i kolumny

- `->columns(array $columns)`
- `->columnAttributes(array $attributes)` / `->columnsAttributes(...)` / `->attrs(...)`
- `->filterColumns(callable $callback)`
- `->searchable(array $columns)`
- `->searchbar(Component|array|bool $searchbar)`
- `->showInputSearchInSidebar(bool $state = true)`
- `->pokazCzyMaBycInputSearchWSidebarze(bool $state = true)` (alias)
- `->baseUri(string $uri)`

### Nagłówek i wygląd

- `->title(string $title)`
- `->description(string $description)`
- `->header(array $components)`
- `->addHeader(Component $component)`
- `->headerActions(array $actions)`
- `->appearance(string $appearance)`
- `->headerAppearance(array|Appearance $props)`
- `->bodyAppearance(array|Appearance $props)`
- `->searchAppearance(array|Appearance $props)`

### Filtry

- `->filters(array $filters)`
- `->filtersAppearance(string $appearance)` (`drawer|inline|both`)
- `->filterAppearance(string $appearance)` (alias)
- `->filtersSize(string $size)` (`xs|sm|default`)
- `->filterSize(string $size)` (alias)

### Selekcja, sticky, numerowanie, kondensacja

- `->selected(bool $state = true)`  
  Włącza/wyłącza funkcje zaznaczania kolumn/obszarów.
- `->sticky('header', 'search', 'footer')`  
  Możesz też podać tablicę.
- `->numbering(bool|string $mode = true, ?string $label = null)`  
  Tryby: `continuous`, `per_page` (oraz aliasy `reset|page|per-page`).
- `->condensed(bool $state = true)`

### Eksport

- `->exported(true)` - eksport włączony (domyślnie).
- `->exported(false)` - eksport wyłączony.
- `->exported('sql', 'csv')` - dozwolone formaty eksportu.
- `->exported(['sql', 'xml'])` - dozwolone formaty eksportu (wariant tablicowy).

Dostępne formaty: `csv`, `tsv`, `xlsx`, `xls`, `ods`, `json`, `xml`, `sql`, `pdf`.
Wartość globalna może być ustawiona w `config/upsoftware.php` pod `table.exported`.

### Import

- `->imported(true)` - import włączony (domyślnie).
- `->imported(false)` - import wyłączony.

Wartość globalna może być ustawiona w `config/upsoftware.php` pod `table.imported`.

Przycisk `ButtonImport` wysyła `POST` na bieżący URL tabeli i przekazuje:

- `_table_action=import`
- `_import_field=import_file` (lub własna nazwa pola)
- plik/pliki w polu `import_file`

Po stronie zasobu możesz obsłużyć import przez opcjonalny hook:

```php
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\Http\RedirectResult;
use Illuminate\Http\UploadedFile;

public function import(PanelContext $context, array $files, TableBuilder $builder): RedirectResult|array|string|int|bool|null
{
    /** @var UploadedFile $file */
    $file = $files[0];

    // ... własna logika importu

    return ['success' => __('Import completed.')];
}
```

Uwaga bezpieczeństwa:

- przed wywołaniem `import()` Svarium sprawdza, czy plik nie jest zabezpieczony hasłem do otwarcia;
- jeśli plik jest zaszyfrowany, import zostanie przerwany z komunikatem ostrzegawczym.

Obsługiwane zwroty z `import()`:

- `RedirectResult` - zwracany bez zmian.
- `array` - klucze: `success|info|warning|error|message|count|redirect`.
- `string` - komunikat sukcesu.
- `int|float` - liczba zaimportowanych rekordów.
- `false` - komunikat błędu.
- `null|true` - domyślny komunikat sukcesu.

### Akcje

- `->actions(array $actions)`
- `->actionDisplay(TableActionDisplay|string $mode)` (`inline|dropdown`)
- `->disableDefaultActions(array $types)`
- `->onlyDefaultActions(array $types)`
- `->withoutDefaultActions()`

### Akcje masowe (bulk)

- `->bulk(bool|string $mode = true)` (`false|true|'single'|'multiple'`)
- `->bulkActions(array $actions)`
- `->disableDefaultBulkActions(array $types)`
- `->onlyDefaultBulkActions(array $types)`
- `->withoutDefaultBulkActions()`

### Paginacja

- `->pagination(array $config)`
- `->perPage(array $options, ?string $rowsPerPageLabel = null)`
- `->rowsPerPageOptions(array $options)`
- `->rowsPerPage(int|string $rowsPerPage)`
- `->rowsPerPageLabel(?string $label)`
- `->rowsPerPageAllLabel(?string $label)`
- `->paginationLabel(?string $label)`
- `->showButtonLabel(bool $show = true)`
- `->showFirstLabel(bool $show = true)`
- `->showLastLabel(bool $show = true)`
- `->ellipsisAfter(int|string $pages)`
- `->firstButtonLabel(?string $label)`
- `->previousButtonLabel(?string $label)`
- `->nextButtonLabel(?string $label)`
- `->lastButtonLabel(?string $label)`

### Tabs i widoki

- `->tabs(array $tabs)`
- `->tabsFromViews(bool $enabled = true)`  
  Dodaje zakładki z zapisanych widoków.

Po kliknięciu zakładki widoku tabela ustawia parametr `?view=<key>` w URL.
Po stronie backendu klucz widoku nakłada zdefiniowane filtry i domyślne sortowanie zapisane w widoku.

## API kolumny (`Column`)

Najczęściej używane:

- `Column::make('field')`
- `->label('...')`
- `->sortable()`
- `->searchable()`
- `->selected(false)` - blokuje zaznaczanie tej kolumny z poziomu nagłówka.
- `->exported(bool $state = true)` - steruje, czy kolumna ma być uwzględniana w eksporcie (`true` domyślnie).
- `->hide()`
- `->state(fn ($row) => ...)`
- `->default(...)`
- `->placeholder('...')`
- `->concat('first_name', 'last_name')`
- `->type('string|number|date|...')` (dla filtrów)
- `->filter(...)`
- `->filterRule(...)`
- `->operators([...])`
- `->date('Y-m-d')`, `->dateTime('Y-m-d H:i')`, `->time('H:i')`, `->format('...')`
- `->action(Action::edit())`
- `->footer('...')`

Przykład wyłączenia kolumny z eksportu:

```php
Column::make('internal_note')
    ->label('Internal note')
    ->exported(false);
```

Dla wyglądu:

- `->headerAppearance(...)`
- `->bodyAppearance(...)`
- `->searchAppearance(...)`
- `->footerAppearance(...)`

## Filtry - 2 sposoby

### 1) Na kolumnie

```php
Column::make('status')
    ->label('Status')
    ->filter('both', 'multiple')
    ->filterRule(['=', '!=', 'is_null']);
```

### 2) Jako osobny obiekt

```php
use Upsoftware\Svarium\UI\Components\Table\Filter;

Filter::make('created_at')
    ->label('Created')
    ->type('date')
    ->multiple()
    ->operators(['=', 'before', 'after', 'between']);
```

## Akcje wiersza (`Action`)

Fabryki:

- `Action::create()`
- `Action::view()`
- `Action::edit()`
- `Action::duplicate()`
- `Action::delete()`

Dalsza konfiguracja:

- `->label('...')`, `->icon('lucide:...')`
- `->url('...')`
- `->method('GET|POST|PUT|DELETE')`
- `->confirm(true|false|array)`
- `->variant('...')`, `->size('...')`
- `->show(fn (array $row) => bool)`

## Akcje masowe (`BulkAction`)

Fabryki:

- `BulkAction::delete()`
- `BulkAction::duplicate()`
- `BulkAction::make('custom_key')`

Konfiguracja:

- `->label('...')`, `->icon('lucide:...')`
- `->variant('outline|destructive|...')`
- `->confirm(array|bool|null)`
- `->handler(fn (Builder $query, array $ids, PanelContext $context, ?Resource $resource, BulkAction $action) => int)`
- `->successMessage('...')` lub closure

## Przykład pełnej tabeli

```php
use App\Models\Patient;
use Upsoftware\Svarium\Panel\Table\BulkAction;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Appearance;
use Upsoftware\Svarium\UI\Components\Search\DropdownSearch;
use Upsoftware\Svarium\UI\Components\Search\InputSearch;
use Upsoftware\Svarium\UI\Components\Table\Action;
use Upsoftware\Svarium\UI\Components\Table\Column;

public function table(): TableBuilder
{
    return TableBuilder::make(Patient::query())
        ->title(__('Patients'))
        ->description(__('List of patients'))
        ->columns([
            Column::make('id')->label('ID')->sortable()->selected(false),
            Column::make('first_name')->label(__('First name'))->sortable()->searchable(),
            Column::make('last_name')->label(__('Last name'))->sortable()->searchable(),
            Column::make('status')
                ->label(__('Status'))
                ->filter('both', 'multiple')
                ->filterRule(['=', '!=', 'is_null']),
            Column::make('created_at')->label(__('Created'))->dateTime('Y-m-d H:i')->sortable(),
        ])
        ->searchbar([
            InputSearch::make('q')->placeholder(__('Search...')),
            DropdownSearch::make(__('Status'))
                ->column('status')
                ->options([
                    1 => __('Active'),
                    0 => __('Inactive'),
                    -1 => __('Archived'),
                ]),
        ])
        ->showInputSearchInSidebar(true)
        ->bulk('multiple')
        ->bulkActions([
            BulkAction::delete(),
            BulkAction::duplicate(),
        ])
        ->actions([
            Action::view(),
            Action::edit(),
            Action::delete(),
        ])
        ->sticky('header', 'search', 'footer')
        ->condensed(false)
        ->headerAppearance(Appearance::make()->bgColor('slate-100'))
        ->filtersAppearance('both')
        ->filtersSize('sm')
        ->rowsPerPageOptions([10, 25, 50, 100, 0]);
}
```

## Częste scenariusze

### Chcę tylko globalne proste wyszukiwanie tekstowe

1. Ustaw:

```php
// config/upsoftware.php
'table' => [
    'searchbar' => true,
]
```

2. Oznacz kolumny `->searchable()` albo podaj `->searchable(['col1', 'col2'])`.

### Chcę wyłączyć globalny searchbar w jednej tabeli

```php
->showInputSearchInSidebar(false)
```

### Chcę własny InputSearch bez duplikatu

```php
->searchbar([
    InputSearch::make('q')->placeholder(__('Search...')),
])
```

Auto `q` nie zostanie dodane drugi raz.

## Powiązane dokumenty

- [Stopka tabeli (`Column::footer()`)](./table-footer.md)
- [DropdownSearch (pełne API)](./dropdown-search.md)
