# Table footer (`Column::footer()`)

Mechanizm `Column::footer()` pozwala zdefiniować, co ma pojawić się w komórce stopki tabeli dla konkretnej kolumny.

Obsługiwane są:

- agregaty z bieżącej strony,
- agregaty z całego zapytania (`total_*`),
- szablony tekstowe z placeholderami,
- grupowanie sum po polu (`sum_by(field)`, `total_sum_by(field)`).
- warunkowa suma po polu (`sum_where(field,value)`, `total_sum_where(field,value)`).

## Szybki przykład

```php
use Upsoftware\Svarium\UI\Components\Table\Column;

Column::make('amount')->label('Kwota')->footer('sum');
Column::make('amount')->label('Całość')->footer('total_sum');
Column::make('created_at')->label('Zakres')->footer('between');
```

## API

```php
Column::make('amount')->footer(?string $definition)
```

`$definition` może być tokenem (np. `sum`), wyrażeniem grupującym (np. `sum_by(currency)`) albo dowolnym tekstem z placeholderami.

## Zakres obliczeń

## 1) Agregaty strony (aktualna paginacja)

- `sum`
- `count`
- `average`
- `min`
- `max`
- `between`

Przykład:

```php
Column::make('amount')->footer('sum');
Column::make('amount')->footer('average');
Column::make('created_at')->footer('between');
```

## 2) Agregaty całego zapytania

- `total_sum`
- `total_count`
- `total_average`
- `total_min`
- `total_max`
- `total_between`

Przykład:

```php
Column::make('amount')->footer('total_sum');
Column::make('amount')->footer('total_average');
Column::make('created_at')->footer('total_between');
```

`total_*` działają po wszystkich aktywnych filtrach/sortowaniu query, ale nie są ograniczone do bieżącej strony paginacji.

## Szablony tekstowe z placeholderami

Możesz składać własny tekst i wstawiać placeholdery `:token`.

Przykład:

```php
Column::make('amount')->footer('Suma strony: :sum');
Column::make('amount')->footer('Strona: :sum | Całość: :total_sum');
Column::make('amount')->footer('MIN: :min | MAX: :max | AVG: :average');
```

### Dostępne placeholdery

- `:sum`, `:count`, `:average`, `:min`, `:max`, `:between`
- `:total_sum`, `:total_count`, `:total_average`, `:total_min`, `:total_max`, `:total_between`
- `:sum_by(field)`, `:total_sum_by(field)`
- `:sum_where(field,value)`, `:total_sum_where(field,value)`

Jeśli `footer()` zawiera tekst bez placeholderów i bez znanych tokenów, zostaje wyrenderowany literalnie.

## Grupowanie sum: `sum_by(field)`

To rozszerzenie pozwala zwrócić sumę wartości kolumny rozbitą na grupy po wskazanym polu.

Przykład:

```php
Column::make('amount')->footer('sum_by(currency)');
```

Wynik może wyglądać tak:

```text
123,00 PLN 300,00 EUR
```

### Wariant globalny

```php
Column::make('amount')->footer('total_sum_by(currency)');
```

### Grupowanie we własnym tekście

```php
Column::make('amount')->footer('Strona: :sum_by(currency) | Całość: :total_sum_by(currency)');
```

## Warunkowa suma: `sum_where(field,value)`

Jeżeli chcesz policzyć sumę kolumny tylko dla rekordu spełniającego warunek, użyj:

```php
Column::make('amount')->footer('sum_where(currency,EUR)');
```

Wariant globalny (całe query):

```php
Column::make('amount')->footer('total_sum_where(currency,EUR)');
```

Warianty z placeholderami:

```php
Column::make('amount')->footer('EUR: :sum_where(currency,EUR) | USD: :sum_where(currency,USD)');
Column::make('amount')->footer('EUR total: :total_sum_where(currency,EUR)');
```

Możesz też użyć wartości w cudzysłowie:

```php
Column::make('amount')->footer('sum_where(currency,\"EUR\")');
```

## Wiele warunków/agregatów w jednej komórce

Jeśli chcesz „sumowanie dwóch warunków” lub więcej zestawów danych w jednej komórce, składaj je placeholderami:

```php
Column::make('amount')->footer('Waluty: :sum_by(currency) | Statusy: :sum_by(status)');
Column::make('amount')->footer('Statusy globalnie: :total_sum_by(status) | Suma: :total_sum');
Column::make('amount')->footer('EUR: :sum_where(currency,EUR) | PLN: :sum_where(currency,PLN)');
```

To jest obecnie najprostsza i wspierana forma łączenia wielu podsumowań w jednej komórce stopki.

## Pełny przykład kolumn

```php
protected static function columns(): array
{
    return [
        Column::make('id')
            ->label('ID')
            ->sortable()
            ->footer('count'),

        Column::make('amount')
            ->label('Kwota')
            ->sortable()
            ->footer('Strona: :sum | Globalnie: :total_sum'),

        Column::make('amount')
            ->label('Kwota wg walut')
            ->footer('sum_by(currency)'),

        Column::make('created_at')
            ->label('Zakres dat')
            ->footer('between'),
    ];
}
```

## Zasady obliczeń

- `sum` i `average` biorą pod uwagę tylko wartości numeryczne.
- `count` liczy tylko wartości niepuste (pomija `null` i puste stringi).
- `between` jest budowane jako `min - max`.
- `min`/`max` obsługują liczby, daty i stringi; przy mieszanych typach porównanie jest tekstowe.
- `sum_by(field)` ignoruje rekordy, w których wartość kolumny nie jest numeryczna.
- Wynik `sum_by(field)` jest sortowany alfabetycznie po kluczu grupy.

## Wydajność

- Dla prostych kolumn bazodanowych (np. `amount`, `id`) agregaty `total_*` są liczone SQL-em (`sum`, `count`, `min`, `max`, `avg`).
- Dla kluczy zagnieżdżonych (np. `data.price.net`) system liczy `total_*` z pełnej listy rekordów w pamięci.

Dla dużych datasetów rekomendowane jest:

- używanie prostych kolumn SQL do agregacji,
- albo przygotowanie aliasów/kolumn pomocniczych po stronie query.

## Przykład użycia w Resource

```php
public function table(): TableBuilder
{
    return TableBuilder::make(Page::query())
        ->columns([
            Column::make('title')->label('Tytuł'),
            Column::make('amount')->label('Kwota')->footer('Strona: :sum | Wszystkie: :total_sum'),
            Column::make('amount')->label('Waluty')->footer('sum_by(currency)'),
        ])
        ->bulk(true)
        ->actions([
            Action::view(),
            Action::edit(),
            Action::duplicate(),
            Action::delete(),
        ]);
}
```

## Debug checklist

Jeśli footer nie renderuje się poprawnie:

1. Sprawdź, czy kolumna jest widoczna (access/table visibility).
2. Sprawdź, czy przekazujesz `->footer('...')` na tej samej kolumnie, której wartość ma być liczona.
3. Przy `sum`/`average` upewnij się, że dane są numeryczne.
4. Przy `sum_by(field)` upewnij się, że pole grupujące istnieje w rekordzie (`field` albo `data.path`).
5. Sprawdź, czy w tabeli frontendowej jest renderowany `tfoot` i prop `footer`.

## Powiązane miejsca w kodzie

- `Upsoftware\Svarium\UI\Components\Table\Column::footer()`
- `Upsoftware\Svarium\Panel\Table\TableBuilder::resolveFooterValues()`
- `Upsoftware\Svarium\Panel\Table\TableBuilder::renderFooterValue()`
- `Upsoftware\Svarium\Panel\Table\TableBuilder::resolveGroupedSumBy()`
