# Register Layouts And Panel Config

This document covers registration configuration with focus on layouts and tree composition.

## Where to configure

Use `app/Svarium/panels.php`:

```php
<?php

use App\Svarium\Layouts\AdminLayout;
use App\Svarium\Layouts\AuthLayout;
use App\Svarium\Schemas\RegisterSchema;
use Upsoftware\Svarium\Panel\Layout;
use Upsoftware\Svarium\Panel\Panel;

return [
    Panel::make('app')
        ->noPrefix()
        ->middleware(['auth'])
        ->layout(AdminLayout::class)
        ->register(
            Layout::make(AuthLayout::class)
                ->body(RegisterSchema::class)
        ),
];
```

You can also pass an array directly via `->registration([...])`.

## Config source precedence

Registration config is merged in this order:

1. internal defaults from `RegisterController::defaultConfig()`
2. `config('upsoftware.auth.register')`
3. settings storage: `register.config`
4. panel config from `Panel::register(...)` / `Panel::registration(...)`

For `fields`, arrays are merged and normalized by field `name`.

## Main keys

Supported keys (from controller defaults and runtime handling):

- `enabled` (bool): enables register screen and submit endpoint
- `layout` (string/FQCN): root register layout component
- `layout_enabled` (bool): enable/disable root register layout node
- `skip_main_layout` (bool): semantic alias for "skip root layout node"
- `wrap` (mixed): additional wrapper(s) around final register tree
- `schema` (component class/callable/component array): register fields schema
- `component` (string): custom register component instead of default auto form
- `action` (string): submit route name, default `panel.auth.register.set`
- `title`, `subtitle`, `submitLabel`, `loginLabel`, `loginLinkLabel`, `loginLink`
- `fields` (array): field metadata for custom UI components
- `activation` (array): activation strategy (`none`, `email_code`, `email_link`, `custom`)
- `events` (array): post-registration hooks/events
- `auto_login` (bool), `redirect_to`, `redirect_route`, `login_redirect_route`

## Fluent API for register layout

`Upsoftware\Svarium\Panel\Layout` methods:

- `Layout::make(string $layoutClass)` sets register root layout
- `->body($schema)` / `->content($schema)` sets register schema
- `->enabled(bool)` enables/disables registration
- `->layoutEnabled(bool)` controls root register layout node
- `->withoutLayout()` shorthand for `layoutEnabled(false)`
- `->skipMainLayout()` skip only main register layout node
- `->wrap($wrapper)` / `->wrapComponent($wrapper)` wrap final tree
- `->config(array|Config)` merge additional nested config

## Config helper (`Upsoftware\\Svarium\\Panel\\Config`)

Use `Config` when you want to build nested options in fluent style:

```php
<?php

use Upsoftware\Svarium\Panel\Config;
use Upsoftware\Svarium\Panel\Layout;

Layout::make('AuthLayout')
    ->body(RegisterSchema::class)
    ->config(
        Config::make()
            ->set('activation.mode', 'email_code')
            ->set('events.dispatch_registered', true)
    );
```

Short one-liner form:

```php
->config(Config::add('activation.mode', 'email_link'))
```

## Layout behavior in register tree

### 1. Standard mode (`layout_enabled = true`)

Tree is built as:

- root: `layout`
- inside root slot `body`: register node (`Form` in auto mode, or custom `component`)
- then optional `wrap` wrappers are applied around resulting tree

### 2. Skip main register layout (`skip_main_layout = true`)

Root register layout node is removed.

Behavior:

- system instantiates main layout class (`layout`)
- extracts nodes from target slot (`body`)
- if extracted nodes exist, they become base tree
- if extracted nodes are empty, it falls back to register node (`Form`/custom component)
- optional `wrap` wrappers are applied after that

This mode is useful for: `CleanLayout -> (body from AuthLayout)` without keeping `AuthLayout` node in final JSON tree.

### 3. Disable register layout (`withoutLayout()` / `layout_enabled = false`)

No root `layout` node is used. Base tree starts from register node.

If main layout class has content in `body`, those nodes are used as base tree (same extraction logic as above).

## Wrap option (`wrap`)

`wrap` accepts:

- string component name, for example `'CleanLayout'`
- layout/component FQCN
- component instance
- node array, for example `['type' => 'CleanLayout', 'slot' => 'body']`
- list of values above (multiple wrappers)

Examples:

```php
Layout::make(AuthLayout::class)
    ->body(RegisterSchema::class)
    ->wrap('CleanLayout');
```

```php
Layout::make(AuthLayout::class)
    ->skipMainLayout()
    ->body(RegisterSchema::class)
    ->wrap('CleanLayout');
```

## Schema requirements

Register schema must return `Component` or `array<Component>`.

Required fields in schema:

- `Input` with name `email`
- `Input` with name `password`

If these are missing, registration throws runtime exception.

## Default schema (when `schema` is not defined)

```php
return [
    Flex::make()->direction('col')->gap(4)->children([
        Block::make()->children([
            Input::make('email')->label('Email')->required()->email()->prop('type', 'email'),
        ]),
        Block::make()->children([
            Input::make('password')->label('Password')->required()->prop('type', 'password'),
        ]),
        Block::make()->children([
            Input::make('company')->label('Company'),
        ]),
    ]),
];
```

## Example with custom Auth layout and wrapping

```php
<?php

use App\Svarium\Layouts\AdminLayout;
use App\Svarium\Layouts\AuthLayout;
use App\Svarium\Schemas\RegisterSchema;
use Upsoftware\Svarium\Panel\Layout;
use Upsoftware\Svarium\Panel\Panel;

return [
    Panel::make('app')->noPrefix()
        ->middleware(['auth'])
        ->layout(AdminLayout::class)
        ->register(
            Layout::make(AuthLayout::class)
                ->skipMainLayout()
                ->body(RegisterSchema::class)
                ->wrap('CleanLayout')
        ),
];
```

## Disable registration

When disabled, both register page and submit flow redirect to panel home (`/{prefix}` or `/`):

```php
->register(
    Layout::make(AuthLayout::class)
        ->enabled(false)
)
```

or:

```php
->registration([
    'enabled' => false,
])
```

## Related docs

- Panel-level layout API: `docs/panel-layouts.md`
