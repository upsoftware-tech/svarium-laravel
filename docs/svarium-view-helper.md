# Helper `svarium_view()`

`svarium_view()` pozwala wyrenderować komponenty Svarium bezpośrednio z klasycznego kontrolera Laravel, podobnie jak `return view(...)`.

## Sygnatura

```php
svarium_view(
    \Upsoftware\Svarium\UI\Component|array|string $content,
    ?string $layout = null,
    array $props = [],
    array $layoutProps = [],
    ?string $view = null,
    array $meta = []
): \Symfony\Component\HttpFoundation\Response
```

## Przykłady

### 1) Jeden komponent

```php
use Upsoftware\Svarium\UI\Components\Text;

return svarium_view(
    Text::make(__('Hello from controller'))
);
```

### 2) Tablica komponentów

```php
use Upsoftware\Svarium\UI\Components\Text;
use Upsoftware\Svarium\UI\Components\Form\Input;

return svarium_view([
    Text::make(__('Project page')),
    Input::make('name')->label(__('Name')),
]);
```

### 3) Własny layout + propsy layoutu

```php
use App\Svarium\Layouts\AppLayout;
use Upsoftware\Svarium\UI\Components\Text;

return svarium_view(
    Text::make(__('Custom content')),
    layout: AppLayout::class,
    props: ['customFlag' => true],
    layoutProps: [
        'containerEnabled' => true,
        'containerFluid' => false,
    ],
    meta: [
        'source' => 'controller',
    ],
);
```

### 4) Fallback do Inertia view (string)

Jeśli pierwszy argument jest stringiem, helper zachowuje się jak `inertia(...)`.

```php
return svarium_view('Dashboard', props: ['foo' => 'bar']);
```

