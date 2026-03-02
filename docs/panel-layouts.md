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

## Render precedence

For regular operations (`ComponentResult`), layout composition is applied in this order:

1. `layoutUsing(...)` callback mutates layout instance.
2. Panel slots are applied (`header`, `sidebar`, etc.), excluding `body/content`.
3. Operation-level overrides are applied, excluding `body/content`.
4. Body is assembled (`Panel::content(...)` wrapper if present, otherwise operation component).

This order means operation overrides can replace panel slot values for the same slot.

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

## Related docs

- Registration page layout options: `docs/register-panel-config.md`
