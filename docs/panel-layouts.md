# Panel Layouts

This document describes how panel layouts are configured and rendered in Svarium.

## Panel layout entry point

Configure the main panel layout in `app/Svarium/panels.php`:

```php
<?php

use App\Svarium\Layouts\AdminLayout;
use App\Svarium\Layouts\AdminHeader;
use App\Svarium\Layouts\AdminSidebar;
use Upsoftware\Svarium\Panel\Panel;

return [
    Panel::make('admin')
        ->prefix('admin')
        ->layout(AdminLayout::class)
        ->header(AdminHeader::class)
        ->sidebar(AdminSidebar::class),
];
```

`Panel::layout(...)` sets the base layout class used by operations in this panel.

## Available panel slots

Use these methods on `Panel::make(...)` to fill layout slots:

- `->header(...)` sets slot `header`
- `->sidebar(...)` sets slot `sidebar`
- `->content(...)` sets slot `body` (alias for panel body wrapper/content area)
- `->contentHeader(...)` sets slot `contentHeader`
- `->contentFooter(...)` sets slot `contentFooter`
- `->aside(...)` sets slot `aside`
- `->footer(...)` sets slot `footer`

## Dynamic layout customization

`->layoutUsing(...)` lets you mutate the resolved layout instance before slots are applied.

```php
->layoutUsing(function ($layout) {
    $layout->prop('layout', 'panel');
})
```

Use it when you want dynamic props/behavior without creating another layout class.

## Body behavior (`Panel::content(...)`)

`Panel::content(...)` is special. It is treated as a body wrapper:

- if a wrapper component is provided, the operation/page component is injected into wrapper slot `content`
- wrapper is then mounted into layout slot `body`
- if there is no wrapper, the operation/page component goes directly to layout slot `body`

## Automatic `Container` in `PanelLayout`

`PanelLayout` automatically wraps rendered panel content with `Container`.

This means:

- regular operation/page content injected into panel `Body::make()` is wrapped with `Container`
- custom panel `body` content mounted through `Panel::content(...)` is also wrapped with `Container`
- panel `contentHeader` and `contentFooter` are not wrapped
- wrapper is applied only for `PanelLayout` (not globally for every layout type)
- wrapper renders CSS classes `container app__container` (or `app__container w-full max-w-none` in `fluid` mode)

Default configuration:

```php
'panel' => [
    'container' => [
        'enabled' => true,
        'fluid' => false,
        'position' => 'center',
    ],
],
```

You can override it globally in `config/upsoftware.php` or directly on layout instance:

```php
->layoutUsing(function ($layout) {
    $layout
        ->container(true)
        ->containerFluid(false)
        ->containerPosition('center');
})
```

Disable wrapper completely:

```php
->layoutUsing(function ($layout) {
    $layout->container(false);
})
```

## Per-operation `Container` (full width / disable)

W pojedynczej `Operation` możesz sterować kontenerem przez `layoutProps(...)`:

```php
protected function layoutProps(PanelContext $context, ...$args): array
{
    return [
        'containerEnabled' => true, // false = bez Container
        'containerFluid' => true,   // true = full width
        // 'containerPosition' => 'center', // left|center|right
    ];
}
```

Uwaga:
- `containerFluid=true` działa tylko gdy `containerEnabled=true`.
- jeśli chcesz pełną szerokość, zwykle ustawiaj `containerEnabled=true` + `containerFluid=true`.

Important:

- if you manually add your own `Container` inside page content, then you will get nested containers
- if your layout is not based on `PanelLayout`, automatic wrapper is not added

## Global root layout (`CleanLayout`)

By default, Svarium wraps rendered panel layout with `CleanLayout` as the top/root node.

Configuration:

```php
// config/upsoftware.php
'panel' => [
    'root_layout' => 'CleanLayout', // default
    'definition_layout_types' => ['AuthLayout'],
],
```

Notes:
- this applies to regular operation rendering (`ComponentResult`),
- if your layout already has type `CleanLayout`, wrapper is not duplicated,
- if current layout type is listed in `definition_layout_types`, that layout node is flattened (used only as definition),
- set empty string to disable root wrapper.

## Render precedence

For regular operations (`ComponentResult`), layout composition is applied in this order:

1. `layoutUsing(...)` callback mutates layout instance.
2. Panel slots are applied (`header`, `sidebar`, etc.), excluding `body/content`.
3. Operation-level overrides are applied, excluding `body/content`.
4. Body is assembled (`Panel::content(...)` wrapper if present, otherwise operation component).
5. Named element overrides are applied for blocks marked with `->element('...')`.

This order means operation overrides can replace panel slot values for the same slot.

## Named layout elements (`Block::element`)

Możesz oznaczyć dowolny `Block` jako „punkt wstrzyknięcia”:

```php
Block::make()
    ->element('sidebar')
    ->width('280px');
```

Następnie w konkretnej `Operation` podać zawartość dla tego elementu:

```php
protected function elementSidebar(PanelContext $context): array
{
    return [
        Text::make('Dynamic sidebar'),
    ];
}
```

Mapowanie:
- `elementSidebar()` -> `element('sidebar')`
- `elementContentHeader()` -> `element('content_header')`

Możesz też użyć jawnej mapy:

```php
protected function layoutElements(PanelContext $context): array
{
    return [
        'sidebar' => [Text::make('Dynamic sidebar')],
    ];
}
```

Priorytet:
- najpierw zbierane są metody `elementXxx()`,
- potem `layoutElements()` może je nadpisać po kluczu.

## Minimal full example

```php
<?php

use App\Svarium\Layouts\AdminLayout;
use App\Svarium\Widgets\AdminFooter;
use App\Svarium\Widgets\AdminHeader;
use App\Svarium\Widgets\AdminSidebar;
use Upsoftware\Svarium\Panel\Panel;

return [
    Panel::make('admin')
        ->prefix('admin')
        ->layout(AdminLayout::class)
        ->layoutUsing(function ($layout) {
            $layout->prop('layout', 'admin');
        })
        ->header(AdminHeader::class)
        ->sidebar(AdminSidebar::class)
        ->footer(AdminFooter::class),
];
```

## PanelNavigation in layouts

If you want menu generated automatically from modules/pages (without `navigations` table), use `PanelNavigation` in panel slots:

```php
use Upsoftware\Svarium\UI\Components\PanelNavigation;

Panel::make('app')
    ->layout(AdminLayout::class)
    ->sidebar(PanelNavigation::make()->vertical())
    ->header(PanelNavigation::make()->horizontal());
```

## Component slots (`Flex`, `Block`)

Besides panel-level slots, you can now compose `Flex` and `Block` internals with slot anchors.

### Slot API

Available on every component:

```php
->slot(
    string $name,
    Component|array|string|\Closure|null $content,
    ?string $anchor = null,      // 'header' | 'footer'
    string $position = 'after',  // 'before' | 'after'
    int|string|null $priority = null
)
```

Shortcuts on `Flex` and `Block`:

- `->header($content, 'after'|'before')`
- `->body($content)`
- `->footer($content, 'after'|'before')`
- `->top($content, 'after'|'before')` (alias around header anchor)
- `->bottom($content, 'before'|'after')` (alias around footer anchor)

### Render order

Final order is deterministic:

1. `header.before`
2. `header`
3. `header.after`
4. main content (`body` slot or default children)
5. `footer.before`
6. `footer`
7. `footer.after`

### Priority and duplicates

- `priority` is applied **inside one group** (`header.before`, `header.after`, `footer.before`, `footer.after`).
- Lower priority renders earlier (`1` before `2`).
- If priorities are equal, registration order is preserved.
- Slots without priority are appended at the end of that group.
- If you register anchored slots with the same name multiple times, Svarium auto-generates unique names (`top`, `top_1`, `top_2`, ...), so all of them are rendered.

### Example

```php
Flex::make()
    ->header([Text::make('Main header')]) // base header slot
    ->slot('top', [Text::make('Top before')], 'header', 'before', 1)
    ->slot('top', [Text::make('Top after')], 'header', 'after', 1)
    ->body([Text::make('Content')])
    ->slot('bottom', [Text::make('Bottom before')], 'footer', 'before')
    ->footer([Text::make('Main footer')]);
```

In this example:
- both `top` entries are rendered (second one becomes internal `top_1`),
- `Top before` appears before main header,
- `Top after` appears after main header,
- `Bottom before` appears before main footer.

### Reusable section class in slots (`HeaderAuth::class`)

If you pass class name to component slot (for example `->header(HeaderAuth::class)`), prefer `LayoutSection` implementation that returns regular UI components.

Do not use `PanelLayout` for nested slot fragments.

```php
<?php

namespace Upsoftware\Svarium\Layouts\Auth;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Components\Flex;
use Upsoftware\Svarium\UI\Components\Text;
use Upsoftware\Svarium\UI\Contracts\LayoutSection;

class HeaderAuth implements LayoutSection
{
    public function build(): Component|array|null
    {
        return [
            Flex::make()
                ->direction('col')
                ->gap(2)
                ->children([
                    Text::make('Header')->headline('h1'),
                    Text::make('Description'),
                ]),
        ];
    }
}
```

Usage:

```php
Flex::make()
    ->header(HeaderAuth::class)
    ->slot('top', [Text::make('Top 1')], 'header', 'before');
```

### `width('...px')` and `height('...px')`

For `Appearance` size helpers:

- `->width('120px')` / `->height('48px')` / `->maxWidth('420px')` / `->maxHeight('600px')`
  are rendered as inline style (`style.width`, `style.height`, ...),
- scale values (`'full'`, `'screen'`, `'120'`, etc.) still use Tailwind classes (`w-full`, `h-screen`, ...).

Example:

```php
Block::make()
    ->width('120px')
    ->children([
        LocaleSelect::make(),
    ]);
```

Generates style equivalent to:

```html
<div style="width: 120px"></div>
```

## `Grid`

`Grid` works similarly to `Flex`, but it builds CSS grid layout and accepts responsive column and row definitions.

## `Container`

`Container` jest opisany osobno tutaj:

- [Komponent `Container`](./container-component.md)

```php
use Upsoftware\Svarium\UI\Components\Grid;

Grid::make()
    ->cols(3)
    ->gap(4)
    ->children([
        // ...
    ]);
```

### Columns

You can define columns in three ways:

```php
Grid::make()->cols(3);
Grid::make()->columns(3);
Grid::make()->cols([
    'xs' => 1,
    'md' => 2,
    'lg' => 4,
    '2xl' => 6,
]);
```

Breakpoints supported:

- `xs` (base/default, no Tailwind prefix)
- `sm`
- `md`
- `lg`
- `xl`
- `2xl`

Alias `xxl` is also accepted and mapped to `2xl`.

### Incremental breakpoint helpers

Instead of passing one full array, you can register breakpoints one by one:

```php
Grid::make()
    ->col('xs', 1)
    ->col('md', 2)
    ->col('lg', 4);
```

Shortcuts:

- `->colXs(1)`
- `->colSm(2)`
- `->colMd(3)`
- `->colLg(4)`
- `->colXl(5)`
- `->col2xl(6)`

### Rows

Rows follow the same convention:

```php
Grid::make()
    ->rows([
        'xs' => 1,
        'lg' => 2,
    ]);
```

Or:

```php
Grid::make()
    ->row('xs', 1)
    ->row('lg', 2);
```

Shortcuts:

- `->rowXs(...)`
- `->rowSm(...)`
- `->rowMd(...)`
- `->rowLg(...)`
- `->rowXl(...)`
- `->row2xl(...)`

### Gaps

`Grid` supports the same gap helpers as `Flex`:

```php
Grid::make()
    ->cols(3)
    ->gap(4)
    ->gapX(6)
    ->gapY(2);
```

### Child span helpers

Grid children can use span helpers through `Appearance` shortcuts available on components:

```php
Block::make()
    ->colSpan(6)
    ->rowSpan(2);
```

Supported values:

- integer span, for example `->colSpan(6)`
- `full`, for example `->colSpan('full')`
- `auto`, for example `->rowSpan('auto')`

### Example

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
        Block::make('D')->rowSpan(2),
    ]);
```

## Related docs

- Registration page layout options: `docs/register-panel-config.md`
- Runtime menu registration: `docs/menu-registration.md`
