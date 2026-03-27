# Komponent `MenuBar` (PHP + Vue)

`MenuBar` jest komponentem poziomego menu opartym o `shadcn-vue` (`reka-ui` Menubar).

## Klasa PHP

- `Upsoftware\Svarium\UI\Components\MenuBar`

## Podstawowe użycie

```php
use Upsoftware\Svarium\UI\Components\MenuBar;

MenuBar::make('main_actions')
    ->align('start')
    ->items([
        [
            'label' => 'File',
            'icon' => 'lucide:file',
            'children' => [
                ['label' => 'New', 'value' => 'new', 'shortcut' => '⌘N'],
                ['label' => 'Open', 'value' => 'open', 'shortcut' => '⌘O'],
                ['type' => 'separator'],
                ['label' => 'Settings', 'href' => '/app/settings'],
            ],
        ],
        [
            'label' => 'Edit',
            'children' => [
                ['label' => 'Undo', 'value' => 'undo', 'shortcut' => '⌘Z'],
                ['label' => 'Redo', 'value' => 'redo', 'shortcut' => '⇧⌘Z'],
            ],
        ],
    ]);
```

## Przykład `MenuBar::make()`

```php
use Upsoftware\Svarium\UI\Components\MenuBar;

MenuBar::make('workspace_menu')
    ->align('start')
    ->default('profile')
    ->items([
        [
            'label' => __('Workspace'),
            'icon' => 'lucide:briefcase',
            'children' => [
                [
                    'label' => __('Profile'),
                    'value' => 'profile',
                    'icon' => 'lucide:user',
                ],
                [
                    'label' => __('Billing'),
                    'value' => 'billing',
                    'icon' => 'lucide:credit-card',
                ],
                ['type' => 'separator'],
                [
                    'label' => __('Notifications'),
                    'type' => 'checkbox',
                    'checked' => true,
                    'value' => 'notifications',
                    'icon' => 'lucide:bell',
                ],
                [
                    'label' => __('More'),
                    'type' => 'submenu',
                    'children' => [
                        [
                            'label' => __('Audit log'),
                            'href' => '/app/audit-log',
                            'icon' => 'lucide:scroll-text',
                        ],
                        [
                            'label' => __('Open docs'),
                            'href' => 'https://www.shadcn-vue.com/docs/components/menubar',
                            'target' => '_blank',
                            'icon' => 'lucide:book-open',
                        ],
                    ],
                ],
            ],
        ],
        [
            'label' => __('Actions'),
            'icon' => 'lucide:zap',
            'children' => [
                [
                    'label' => __('Refresh widgets'),
                    'value' => 'refresh_widgets',
                    'event' => [
                        'name' => 'workspace:refresh-widgets',
                        'payload' => ['source' => 'menubar'],
                        'target' => 'window',
                    ],
                    'icon' => 'lucide:refresh-cw',
                ],
                [
                    'label' => __('Sign out'),
                    'href' => '/logout',
                    'method' => 'post',
                    'icon' => 'lucide:log-out',
                ],
            ],
        ],
    ]);
```

## API (PHP)

- `->items(array|Arrayable $items)` – lista pozycji menu.
- `->menus(array|Arrayable $items)` – alias do `items()`.
- `->menu(string $label, array $children = [], array $props = [])` – dodanie jednego top-level menu.
- `->fromSidebar(bool $applyOverrides = true)` – zasilenie MenuBar z menu sidebara (`main_menu`).
- `->fromMenu(string|int|null $navigationId = 'main_menu', bool $applyOverrides = true)` – zasilenie MenuBar z dedykowanego menu (np. z `register_menu('setting', ...)`).
- `->align('start'|'center'|'end')` – wyrównanie contentu.
- `->default(mixed $value)` – wartość domyślna.
- `->value(mixed $value)` – aktualna wartość.
- `->modelValue(mixed $value)` – model value do bindowania.

## Struktura pozycji (`items`)

Każda pozycja może mieć:

- `label` / `name` / `title`
- `value`
- `icon`
- `shortcut`
- `type`: `item`, `separator`, `label`, `checkbox`, `submenu`
- `children` (lub `items` / `options`) – submenu
- `disabled`
- `checked` (dla `checkbox`)
- `href`/`url`, `target`, `method` (`get|post|put|patch|delete`)
- `event` lub `onSelect`:
  - string (nazwa eventu) albo
  - obiekt `{ name, payload, target: 'window'|'document' }`

## Vue

Komponent jest zarejestrowany globalnie jako:

- `UMenuBar` (plugin prefix domyślnie `U`)

## Generowanie z gotowego menu (`sidebar` / `register_menu`)

### 1) Z sidebara (`main_menu`)

```php
use Upsoftware\Svarium\UI\Components\MenuBar;

MenuBar::make('top_navigation')
    ->fromSidebar()
    ->align('start');
```

### 2) Z dedykowanego menu (np. `register_menu('setting', ...)`)

```php
use Upsoftware\Svarium\UI\Components\MenuBar;

MenuBar::make('settings_navigation')
    ->fromMenu('setting')
    ->align('start');
```

### 3) Bez apply overrides

```php
MenuBar::make('raw_navigation')
    ->fromMenu('setting', false);
```
