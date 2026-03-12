# Komponent `Repeater`

`Repeater` służy do dodawania wielu pozycji jednego pola formularza i budowania powtarzalnych bloków danych.

Obsługuje 3 tryby:

- `table` - szablon renderowany w tabeli.
- `key` - szybki tryb klucz/wartość (`Atrybut` / `Wartość`).
- dowolny własny tryb - render bez tabeli i bez nagłówków (pełna swoboda layoutu).

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

## API

### `->showLabels(bool $enabled = true)`

Włącza lub wyłącza nagłówki tabeli dla `mode('table')` i `mode('key')`.

```php
->showLabels(false)
```

### `->max(int $count)`

Skrót do `->maxItems(...)`. Ogranicza maksymalną liczbę wierszy.

```php
->max(10)
```

### `->empty(string $text)`

Ustawia tekst pustego stanu, gdy repeater nie ma pozycji.

```php
->empty(__('Brak pozycji'))
```

### `->searchable(bool $enabled = true)`

Włącza wyszukiwarkę w repeaterze.

Ważne:

- działa tylko dla `mode('table')` i `mode('key')`,
- dla pozostałych trybów wyszukiwarka nie jest renderowana.

```php
->searchable()
```

## Tryb `key` (klucz/wartość)

Dla `mode('key')` domyślne etykiety to:

- `Atrybut`
- `Wartość`

```php
Repeater::make('additional_configuration')
    ->mode('key')
    ->searchable()
    ->max(10)
    ->empty(__('Brak pozycji'));
```

## Przykład pełny

```php
Repeater::make('additional_configuration')
    ->mode('table')
    ->showLabels(false)
    ->searchable()
    ->max(10)
    ->empty(__('Brak pozycji'))
    ->template([
        Input::make('key')->label(__('Atrybut')),
        Input::make('value')->label(__('Wartość')),
    ]);
```

## Uwagi

- `->max(0)` oznacza brak limitu (jak w `maxItems`).
- `->empty(...)` to alias na wewnętrzne ustawienie pustego stanu.
- W trybach innych niż `table` i `key` repeater renderuje pozycje bez struktury tabeli.
