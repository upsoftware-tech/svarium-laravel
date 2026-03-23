# Komponent `Repeater`

`Repeater` służy do budowania list powtarzalnych elementów formularza.

## Tryby renderowania

- `table` - wiersze w tabeli.
- `key` - szybki tryb klucz/wartość.
- `accordion` - elementy w akordeonie.
- `grid` - elementy w siatce.
- `flex` - elementy w układzie flex.
- `custom` (lub dowolny inny) - pełna swoboda bez nagłówków tabeli.

## Podstawowe użycie

```php
use Upsoftware\Svarium\UI\Components\Repeater;
use Upsoftware\Svarium\UI\Components\Form\Input;

Repeater::make('technical')
    ->mode('table')
    ->template([
        Input::make('name')->label(__('Nazwa')),
        Input::make('value')->label(__('Wartość')),
    ]);
```

## Modal dodawania/edycji

```php
Repeater::make('rooms_data')
    ->mode('grid')
    ->modal([
        Input::make('name')->label(__('Name')),
    ])
    ->modalMaxWidth(1100)
    ->template([
        Input::make('name')->from('name'),
    ]);
```

- `->modal(true)` - włącza modal z domyślnym template.
- `->modal([...])` / `->modal(SomeForm::class)` - własny schema modala.
- `->modalTemplate(...)` - jawny template modala.
- `->modalMaxWidth(1100)` - maksymalna szerokość modala (px lub CSS string, np. `'72rem'`).
- `->modalWidth(...)` - alias dla `modalMaxWidth`.

## API

### Limity i stan pusty

```php
->minItems(0)
->maxItems(10)
->max(10) // alias maxItems
->empty(__('Brak pozycji'))
```

### Wyszukiwarka

```php
->searchable()
```

Wyszukiwarka działa dla `table`, `key`, `accordion`.

### Tabela i etykiety

```php
->showLabels(false)
->labels(__('Atrybut'), __('Wartość'))
->label(__('Kolumna 1'), __('Kolumna 2'), __('Kolumna 3')) // dla mode table
->simple(true)
```

### Separator

```php
->separator(true)
->separatorTemplate()
->separatorPosition('bottom') // top|bottom|both
```

### Styl karty elementu (tryby niestabelaryczne)

```php
->border('none')
->padding(0)
```

To steruje stylem wrappera elementu repeatera (karty pojedynczego rekordu).

### Akcje Edit/Delete

```php
->editLabel(__('Edytuj'))
->editIcon('lucide:pencil')
->editAppearance('text-sky-600 hover:text-sky-700')

->deleteLabel(__('Usuń'))
->deleteIcon('lucide:trash')
->deleteAppearance('text-red-600 hover:text-red-700')
```

Wyłączenie labela:

```php
->editLabel(false)
->deleteLabel(false)
```

Obsługiwane są też wartości `'none'` i pusty string.

### Styl wrappera grupy akcji

```php
->actionAppearance('mt-2 rounded-md border border-slate-200 bg-slate-50 p-2 gap-2')
```

Styluje box otaczający przyciski akcji (Edit/Delete).

### Styl stanu pustego

```php
->emptyAppearance('rounded-md border border-dashed border-red-200 bg-red-50 text-red-700 p-4')
```

## Aliasy kompatybilności

Ze względu na zgodność wsteczną działają też aliasy z literówką:

- `->editApperance(...)`
- `->deleteApperance(...)`
- `->actionApperance(...)`
- `->emptyApperance(...)`

## Przykład pełny

```php
Repeater::make('rooms_data')
    ->mode('grid')
    ->cols(3)
    ->modal([
        Input::make('name')->label(__('Name')),
        Input::make('area')->label(__('Area')),
    ])
    ->template([
        Input::make('name')->from('name'),
    ])
    ->empty(__('No rooms added'))
    ->emptyAppearance('rounded-md border border-dashed border-border p-4')
    ->border('none')
    ->padding(0)
    ->editLabel(false)
    ->deleteLabel(false)
    ->editIcon('lucide:pencil')
    ->deleteIcon('lucide:trash-2')
    ->actionAppearance('mt-2 rounded-md border border-slate-200 bg-slate-50 p-2');
```
