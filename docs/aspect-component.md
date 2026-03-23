# Komponent `Aspect`

`Aspect` tworzy kontener o stałych proporcjach (`aspect-ratio`), np. dla miniatur, video, preview.

## Podstawowe użycie

```php
use Upsoftware\Svarium\UI\Components\Aspect;
use Upsoftware\Svarium\UI\Components\Text;

Aspect::make('square')
    ->children([
        Text::make('Preview'),
    ]);
```

## Wartości proporcji

```php
Aspect::make('square') // 1 / 1
Aspect::make('video')  // 16 / 9
Aspect::make('auto')   // auto
Aspect::make('4/3')
Aspect::make('4:3')
Aspect::make('1.777')
```

Możesz też użyć:

```php
->ratio('16/10')
->custom('3/2')
->square()
->video()
->auto()
```

## Dzieci i content

`Aspect` wspiera:

- `->children([...])`
- `->child(...)`
- drugi argument w `make(...)`:

```php
Aspect::make('square', [
    Text::make('ABC'),
]);
```

## Flex i centrowanie

Dodane skróty dla wygodnego wyrównywania:

```php
Aspect::make('square')
    ->flex('center'); // flex + items-center + justify-center
```

Dostępne też:

```php
->flex()
->align('center')   // start|center|end|baseline|stretch
->justify('center') // start|center|end|between|around|evenly
```
