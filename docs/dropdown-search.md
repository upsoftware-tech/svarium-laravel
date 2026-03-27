# DropdownSearch (`Search\DropdownSearch`)

`DropdownSearch` służy do filtrowania po słowniku/wartościach dyskretnych w tabeli.

Komponent wspiera:

- źródło opcji z bazy (`column()`),
- ręczne opcje (`options()`),
- ograniczanie opcji do aktywnych/zdefiniowanych,
- wyszukiwarkę wewnątrz dropdowna,
- podsumowanie zaznaczeń w buttonie,
- utrzymanie zaznaczenia na podstawie URL po odświeżeniu i po przejściu między widokami,
- liczniki opcji liczone bez zawężania dropdowna przez jego własny filtr,
- globalne kolory (`color`, `iconColor`) z nadpisaniem per opcja,
- pozycjonowanie ikony i licznika (`left`, `right`, `end`).

Uwaga:
- `->options([...])` działa także bez `->column(...)` (dropdown renderuje wtedy podane opcje statycznie).

## Pełne API (PHP)

```php
DropdownSearch::make(?string $name = '')
    ->column(string $column)
    ->items(array $items)
    ->source(string $url)
    ->mapUsing(callable $callback)
    ->relation(string $relation, string $labelColumn)
    ->options(array $options)
    ->showOnlyActive(bool $state = true)
    ->showOnlyDefined(bool $state = true)
    ->searchable(string|bool|null $placeholder = null, ?int $minItems = null)
    ->visibleItems(?int $count = 2)
    ->color(string|array|null $color)
    ->iconColor(string|array|null $color)
    ->iconPosition(string $position = 'left') // left|right|end
    ->counter(bool $enabled = true)
    ->counterPosition(string $position = 'right') // left|right|end
    ->triggerIcon(?string $icon = 'lucide:plus')
    ->showTriggerIcon(bool $enabled = true)
    ->hideTriggerIcon(bool $hidden = true)
    ->counterPosution(string $position = 'right'); // alias (literówka, kompatybilność)
```

Jeśli podasz `make('is_enabled')`, ten klucz zostanie użyty jako parametr request, np.:

```text
?is_enabled[]=1
```

Od teraz `make('is_enabled')` automatycznie ustawia też `column('is_enabled')`.
Jeśli chcesz inny parametr URL niż kolumna SQL, nadpisz to jawnie:

```php
DropdownSearch::make('status')
    ->name('state')
    ->column('status');
```

Po wejściu ponownie na stronę komponent odczyta zaznaczenia z URL i odtworzy stan checkboxów.

## Kolory

### Globalnie (na cały dropdown)

- `->color(...)` ustawia kolor etykiet opcji
- `->iconColor(...)` ustawia kolor ikon opcji
- jeśli `iconColor` nie jest ustawiony, ikona używa `color`

### Nadpisanie w `options()`

Jeśli w konkretnej opcji podasz `color` albo `iconColor`, to ta wartość ma priorytet nad globalnymi ustawieniami.

Przykład:

```php
DropdownSearch::make(__('Status'))
    ->color('slate-600')
    ->iconColor('slate-500')
    ->options([
        1 => [
            'label' => __('Active'),
            'icon' => 'lucide:check',
            'color' => 'green',
        ],
        0 => [
            'label' => __('Inactive'),
            'icon' => 'lucide:x',
            'color' => ['#ef4444', '#f87171'],
        ],
    ]);
```

### Dozwolone formaty kolorów

1. HEX/CSS, np. `#ef4444`, `rgb(239,68,68)`, `var(--color-danger)`
2. Tailwind token:
   - `green` -> `text-green-500`
   - `green-700` -> `text-green-700`
3. Tablica `[light, dark]`:
   - `['#ef4444', '#ef4443']` (light/dark)
   - `['green-600', 'green-400']` (light/dark)

## `iconPosition()` i `counterPosition()`

Pozycje:

- `left` – po checkboxie, przed etykietą
- `right` – po prawej stronie, przed sekcją `end`
- `end` – na samym końcu po prawej

Domyślnie:

- `iconPosition('left')`
- `counter(true)`
- `counterPosition('right')`

## `visibleItems()`

`visibleItems()` steruje liczbą badge’y pokazanych w buttonie po wybraniu opcji:

- domyślnie `2`
- po przekroczeniu limitu pokazuje jeden badge: `wybrano X`

Przykład:

```php
->visibleItems(4)
```

## `searchable()`

Włącza wyszukiwarkę opcji w dropdownie.

Przykłady:

```php
->searchable()
->searchable(__('Search'), 2)
->searchable(false)
```

- parametr 1: placeholder
- parametr 2: minimalna liczba opcji, od której pokazuje się pole wyszukiwania

## Ikona triggera

Domyślnie przycisk ma ikonę `lucide:plus`.

Wyłączenie:

```php
->showTriggerIcon(false)
// lub:
->hideTriggerIcon()
```

Zmiana ikony:

```php
->triggerIcon('lucide:filter')
```

## Kompletny przykład

```php
use Upsoftware\Svarium\UI\Components\Search\DropdownSearch;

DropdownSearch::make(__('Status'))
    ->column('status')
    ->searchable(__('Search'), 2)
    ->visibleItems(4)
    ->color('slate-600')
    ->iconColor(['slate-500', 'slate-300'])
    ->iconPosition('right')
    ->counter(true)
    ->counterPosition('end')
    ->options([
        1 => [
            'label' => __('Active'),
            'icon' => 'lucide:check',
            'color' => 'green',
        ],
        0 => [
            'label' => __('Inactive'),
            'icon' => 'lucide:x',
            'iconColor' => 'red-600',
        ],
        -1 => [
            'label' => __('Archived'),
            'icon' => 'lucide:archive',
            'color' => ['#64748b', '#94a3b8'],
        ],
    ]);
```
