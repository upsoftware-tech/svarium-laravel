# Komponent `Grid`

`Grid` działa podobnie do `Flex`, ale buduje układ oparty o CSS grid.

Komponent jest dostępny po stronie:

- PHP: `Upsoftware\Svarium\UI\Components\Grid`
- Vue: `Grid`

## `Grid` vs `Flex`

Oba komponenty służą do budowania layoutu, ale rozwiązują różne problemy.

### Kiedy używać `Flex`

`Flex` wybieraj, gdy:

- układ jest jednowymiarowy
- chcesz ustawiać elementy w rzędzie albo kolumnie
- ważniejsze są:
  - `justify-*`
  - `items-*`
  - `wrap`
  - szybkie wyrównanie w jednej osi

Typowe przypadki:

- toolbar
- header
- wiersz przycisków
- układ formularza jeden pod drugim
- dwa bloki obok siebie z prostym wyrównaniem

Przykład:

```php
Flex::make()
    ->justify('between')
    ->items('center')
    ->gap(4)
    ->children([
        Title::make('Nagłówek'),
        Button::make('Zapisz'),
    ]);
```

### Kiedy używać `Grid`

`Grid` wybieraj, gdy:

- układ jest dwuwymiarowy
- chcesz kontrolować kolumny i wiersze
- układ ma zachowywać się responsywnie per breakpoint
- pojedyncze elementy mają zajmować więcej miejsca przez `colSpan()` albo `rowSpan()`

Typowe przypadki:

- dashboard widgets
- lista kart
- sekcje formularza w wielu kolumnach
- układ 12-kolumnowy
- kafelki i panele administracyjne

Przykład:

```php
Grid::make()
    ->colXs(1)
    ->colMd(2)
    ->colLg(4)
    ->gap(4)
    ->children([
        Block::make('A')->colSpan(2),
        Block::make('B'),
        Block::make('C'),
        Block::make('D'),
    ]);
```

### Skrót decyzji

- `Flex` = jedna oś
- `Grid` = kolumny + wiersze

Jeśli najpierw myślisz:

- "ustaw obok siebie" -> `Flex`
- "rozłóż w siatce" -> `Grid`

## Podstawowe użycie

```php
use Upsoftware\Svarium\UI\Components\Grid;
use Upsoftware\Svarium\UI\Components\Block;

Grid::make()
    ->cols(3)
    ->gap(4)
    ->children([
        Block::make('A'),
        Block::make('B'),
        Block::make('C'),
    ]);
```

Domyślnie `Grid` zawsze dodaje klasę `grid`.

## Kolumny

Możesz ustawić liczbę kolumn na 3 sposoby.

### 1. Jedna wartość

```php
Grid::make()->cols(3);
Grid::make()->columns(3);
```

### 2. Tablica breakpointów

```php
Grid::make()->cols([
    'xs' => 1,
    'md' => 2,
    'lg' => 4,
    '2xl' => 6,
]);
```

### 3. Dopisywanie breakpointów pojedynczo

```php
Grid::make()
    ->col('xs', 1)
    ->col('md', 2)
    ->col('lg', 4);
```

## Skróty breakpointów dla kolumn

```php
Grid::make()
    ->colXs(1)
    ->colSm(2)
    ->colMd(3)
    ->colLg(4)
    ->colXl(5)
    ->col2xl(6);
```

## Breakpointy

Obsługiwane breakpointy:

- `xs` = baza / default
- `sm`
- `md`
- `lg`
- `xl`
- `2xl`

Dodatkowo akceptowany jest alias:

- `xxl` = `2xl`

Czyli:

```php
Grid::make()->cols([
    'xs' => 1,
    'md' => 2,
    'xxl' => 6,
]);
```

zadziała poprawnie.

## Wiersze

`Grid` wspiera także liczbę wierszy.

### Jedna wartość

```php
Grid::make()->rows(2);
```

### Tablica breakpointów

```php
Grid::make()->rows([
    'xs' => 1,
    'lg' => 2,
]);
```

### Pojedyncze dopisywanie

```php
Grid::make()
    ->row('xs', 1)
    ->row('lg', 2);
```

## Skróty breakpointów dla wierszy

```php
Grid::make()
    ->rowXs(1)
    ->rowSm(2)
    ->rowMd(3)
    ->rowLg(4)
    ->rowXl(5)
    ->row2xl(6);
```

## Odstępy

`Grid` wspiera te same helpery co `Flex`:

```php
Grid::make()
    ->cols(3)
    ->gap(4)
    ->gapX(6)
    ->gapY(2);
```

## Dzieci grida: `colSpan()` i `rowSpan()`

Dzieci grida ustawiasz przez standardowe helpery `Appearance`, dostępne na komponentach przez magiczne metody.

Przykład:

```php
use Upsoftware\Svarium\UI\Components\Block;

Block::make('Karta')
    ->colSpan(6)
    ->rowSpan(2);
```

Obsługiwane wartości:

- liczba:
  - `->colSpan(6)`
  - `->rowSpan(2)`
- `full`:
  - `->colSpan('full')`
- `auto`:
  - `->rowSpan('auto')`

## Przykład pełny

```php
use Upsoftware\Svarium\UI\Components\Grid;
use Upsoftware\Svarium\UI\Components\Block;

Grid::make()
    ->colXs(1)
    ->colMd(2)
    ->colLg(4)
    ->gap(4)
    ->children([
        Block::make('A')->colSpan(2),
        Block::make('B'),
        Block::make('C'),
        Block::make('D')->rowSpan(2),
    ]);
```

## Uwagi

- `xs` traktowane jest jako baza bez prefixu Tailwind.
- Jeśli nie ustawisz żadnych kolumn, frontend doda domyślne `grid-cols-1`.
- `Grid` obsługuje `header`, `body`, `footer`, `top`, `bottom` tak samo jak `Flex` i `Block`.
