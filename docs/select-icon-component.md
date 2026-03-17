# Komponent `SelectIcon` (PHP + Vue)

`SelectIcon` to picker ikon oparty o popover, wyszukiwarkę i kolekcje ikon.

Komponent jest dostępny z namespace:

- PHP: `Upsoftware\Svarium\UI\Components\Form\SelectIcon`
- Vue: `@/components/select` (nie `@/components/select-icon`)

## Podstawowe użycie (PHP)

```php
use Upsoftware\Svarium\UI\Components\Form\SelectIcon;

SelectIcon::make('icon')
    ->label(__('Icon'))
    ->placeholder(__('Select icon'))
    ->searchable(true)
    ->clear(true);
```

## Własne kolekcje per pole

```php
SelectIcon::make('icon')
    ->collections(['solar', 'carbon']);
```

## Własna lista ikon per pole

```php
SelectIcon::make('icon')
    ->icons([
        'lucide:user',
        'lucide:settings',
        'solar:home-2-bold',
    ]);
```

## Domyślna konfiguracja globalna

W `config/upsoftware.php`:

```php
'components' => [
    'select_icon' => [
        'collections' => ['lucide'],
        'icons' => [],
    ],
],
```

`icons` obsługuje dwa formaty:

1. Lista pełnych nazw:

```php
'icons' => ['lucide:user', 'mdi:account'],
```

2. Mapa kolekcji:

```php
'icons' => [
    'mdi' => ['account', 'cog'],
    'solar' => ['home-2-bold', 'user-bold'],
],
```

## Wydajność (progressive loading)

`SelectIcon` używa progresywnego ładowania, żeby nie zamulać UI:

- kolekcje są ładowane lazy (on-demand),
- lista jest renderowana partiami,
- przy scrollu dociągane są kolejne partie.

Aktualnie wbudowane lazy kolekcje:

- `lucide`
- `mdi`
- `carbon`
- `solar`

## API komponentu PHP

```php
SelectIcon::make(?string $name = null)
    ->icons(array $icons)
    ->collections(array $collections)
    ->placeholder(string $placeholder)
    ->clear(bool $enabled = true)
    ->searchable(bool $enabled = true);
```
