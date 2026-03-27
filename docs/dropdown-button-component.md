# Komponent `DropdownButton` (PHP + Vue)

`DropdownButton` to lekki komponent wyboru opcji w formie przycisku z dropdownem.

## Lokalizacje

- PHP: `Upsoftware\Svarium\UI\Components\DropdownButton`
- PHP alias: `Upsoftware\Svarium\UI\Components\DropdownAction` (dziedziczy po `DropdownButton`)
- Vue: `packages/svarium-npm/src/components/button/DropdownButton.vue`

## Przykład

```php
use Upsoftware\Svarium\UI\Components\DropdownButton;

DropdownButton::make('status')
    ->label('Status')
    ->icon('lucide:plus')
    ->options([
        ['value' => 'draft', 'label' => 'Draft'],
        ['value' => 'published', 'label' => 'Published'],
    ])
    ->default('draft');
```

## API (PHP)

- `->options(array $options)` — lista opcji (`value`, `label`, opcjonalnie `icon`).
- `->default(mixed $value)` — domyślna wartość.
- `->label(string $label)` — etykieta przycisku (fallback, gdy brak wybranej opcji).
- `->icon(string $icon)` — ikona po lewej stronie etykiety przycisku.
- `->size(string $size)` — rozmiar przycisku (`sm`, `default`, `lg`, ...).
- `->variant(string $variant)` — wariant przycisku (`outline`, `default`, `ghost`, ...).
- `->align('start'|'center'|'end')` — wyrównanie dropdownu.
- `->clear(bool $enabled = true)` — pokazuje opcję czyszczenia wyboru.
- `->prop('submitOnChange', true)` — po wyborze opcji komponent wysyła od razu `POST`.
- `->prop('submitUrl', '...')` — URL dla `POST` przy `submitOnChange`.

`size` i `variant` są opcjonalne: jeśli ich nie ustawisz, `DropdownButton` nie wymusza tych propsów na przycisku.

## `DropdownAction`

Możesz używać `DropdownAction` jako semantycznego aliasu (np. dla akcji kontekstowych), z identycznym API:

```php
use Upsoftware\Svarium\UI\Components\DropdownAction;

DropdownAction::make('menu_action')
    ->icon('lucide:circle-plus')
    ->options([
        ['value' => 'label', 'label' => __('Dodaj etykietę')],
        ['value' => 'separator', 'label' => __('Dodaj separator')],
    ])
    ->prop('submitOnChange', true)
    ->prop('submitUrl', panel_href('system/menu-manager?menu=main_menu'));
```

## Zapis wartości

Jeśli komponent ma nazwę (`make('pole')`), renderuje ukryty input i wysyła wybraną wartość jako:

```text
pole=<value>
```
