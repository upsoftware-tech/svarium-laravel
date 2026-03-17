# Komponent `Container`

`Container` działa jak `Block`, ale domyślnie dodaje klasy:

```php
container app__container
```

Nadaje się do klasycznego ograniczania szerokości treści w layoucie strony lub sekcji.

## Podstawowe użycie

```php
use Upsoftware\Svarium\UI\Components\Container;
use Upsoftware\Svarium\UI\Components\Text;

Container::make()
    ->children([
        Text::make('Treść'),
    ]);
```

Skrót dla prostego tekstu:

```php
Container::make('Treść');
```

## Pozycjonowanie

Domyślnie `Container` jest wyśrodkowany:

```php
->position('center')
```

Dostępne wartości:

- `left`
- `right`
- `center`

Przykłady:

```php
Container::make()->position('left')
Container::make()->position('right')
Container::make()->position('center')
```

Dostępne są też skróty:

```php
Container::make()->left()
Container::make()->right()
Container::make()->center()
```

Mapowanie klas:

- `left` -> `ml-0 mr-auto`
- `right` -> `ml-auto mr-0`
- `center` -> `mx-auto`

## Tryb `fluid`

Domyślnie:

```php
->fluid(false)
```

Jeśli włączysz:

```php
->fluid()
```

albo:

```php
->fluid(true)
```

to `Container` dostanie:

- `w-full`
- `max-w-none`

czyli rozciągnie się na pełną szerokość zamiast trzymać standardową szerokość `container`.

## Przykład

```php
Container::make()
    ->left()
    ->fluid()
    ->padding(6)
    ->children([
        Text::make('Sekcja'),
    ]);
```

## Uwagi

- `Container` dziedziczy po `Block`, więc wspiera też:
  - `children`
  - `header`
  - `footer`
  - `top`
  - `bottom`
  - `appearance`
  - helpery typu `padding`, `margin`, `bg`, `rounded`
- `PanelLayout` może automatycznie owijać panelowy `body/content` komponentem `Container`
- automatyczny i ręcznie dodany `Container` zawsze renderuje klasę `app__container`, więc możesz go stylować własnym CSS niezależnie od klasy Tailwinda `container`
- to zachowanie kontrolujesz przez `config('upsoftware.panel.container.*')` albo metody layoutu:
  - `->container(true|false)`
  - `->containerFluid(true|false)`
  - `->containerPosition('left'|'center'|'right')`
