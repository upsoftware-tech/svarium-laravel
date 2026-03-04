# Komponent Logo (PHP + Vue)

Komponent `Logo` renderuje logo aplikacji z obsługą:
- wariantu (`default` / `small`),
- trybu koloru (`auto` / `light` / `dark`),
- fallbacków, gdy brakuje konkretnego wariantu lub trybu.

## Klasa PHP

- `Upsoftware\Svarium\UI\Components\Logo`

### API

```php
use Upsoftware\Svarium\UI\Components\Logo;

Logo::make()              // wariant domyślny: default, tryb: auto
    ->small()             // wariant small
    ->light()             // wymuszony jasny
    ->dark()              // wymuszony ciemny
    ->auto()              // automatyczny wg motywu
    ->default()           // alias do wariantu default
    ->alt('My App')
    ->title('My App')
    ->appearance('h-8 w-auto');
```

Uwagi:
- `->default()` jest obsługiwane jako alias i ustawia wariant `default`.
- `->appearance(...)` działa standardowo jak w innych komponentach.

## Definicja źródeł logo

Logo jest pobierane z konfiguracji współdzielonej (`setting.logo`), np.:

```php
[
    'logo' => [
        'default' => [
            'light' => '/images/logo-light.svg',
            'dark' => '/images/logo-dark.svg',
        ],
        'small' => [
            'light' => '/images/logo-small-light.png',
            'dark' => '/images/logo-small-dark.png',
        ],
    ],
]
```

Możesz też podać prostsze formaty:
- `default` jako string,
- `small` opcjonalnie jako string lub obiekt `light/dark`.

## Kolejność fallbacków

Komponent stosuje fallbacki w dwóch osiach:

1. Wariant:
- gdy `variant=small`: `small` -> `default`
- gdy `variant=default`: `default` -> `small`

2. Tryb koloru:
- gdy `mode=auto`: aktualny motyw (`light`/`dark`) -> drugi tryb
- gdy `mode=light`: `light` -> `dark`
- gdy `mode=dark`: `dark` -> `light`

To oznacza, że gdy brakuje np. `small.dark`, komponent spróbuje kolejno:
- `small.light`,
- potem `default.dark`,
- potem `default.light`.

## Przykłady użycia

```php
Logo::make()->appearance('h-8 w-auto');
```

```php
Logo::make()->small()->appearance('h-10 w-10 rounded');
```

```php
Logo::make()
    ->dark()
    ->title('Panel admina')
    ->alt('Logo panelu');
```

