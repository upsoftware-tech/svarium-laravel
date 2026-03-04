# Komponent Link (PHP + Vue)

Komponent `Link` w Svarium jest wrapperem na `Link` z Inertia i jest dostępny:
- po stronie PHP: `Upsoftware\\Svarium\\UI\\Components\\Link`
- po stronie Vue: `Link` (z paczki `@upsoftware_tech/svarium`)

## Domyślne działanie

- domyślny tag: `Link` (Inertia)
- docelowy adres możesz podać przez:
  - `->route('nazwa.trasy', [...])`
  - `->href('/url-lub-zewnetrzny-link')`
- `->appearance(...)` działa jak w innych komponentach

## API PHP

```php
use Upsoftware\Svarium\UI\Components\Link;

Link::make()
    ->route('panel.auth.login')
    ->appearance('text-blue-500 hover:underline')
    ->children([
        Text::make('Zaloguj się'),
    ]);
```

### Skrót dla tras panelu

```php
Link::make('Zaloguj')
    ->panelRoute('login');
```

To odpowiada:

```php
Link::make('Zaloguj')
    ->route(panel_route_name('login'));
```

Dostępne helpery globalne:

```php
panel_route_name('login');      // panel.auth.login
panel_route_name('auth.reset'); // panel.auth.reset
route_panel('login');           // URL do panel.auth.login
panel_href('auth/login');       // URL po ścieżce, z prefixem panelu
```

### Skrót panelHref w komponencie

```php
Link::make('Logowanie')
    ->panelHref('auth/login');
```

Dla panelu z prefiksem `admin` wygeneruje `/admin/auth/login`, a dla `noPrefix()` -> `/auth/login`.

### Otwieranie w nowym oknie

```php
Link::make()
    ->href('https://example.com')
    ->newWindow();
```

To ustawia `target="_blank"` oraz domyślnie `rel="noopener noreferrer"` (jeśli `rel` nie zostało nadpisane).

### Wybór taga

```php
Link::make()->tag('Link'); // domyślnie
Link::make()->tag('a');    // zwykły <a>
```

## Uwagi

- `href` ma priorytet nad `route`.
- `route(...)` korzysta z resolvera trasy (`route()`) po stronie frontendu.
- Stub `components.ts` został podmieniony: `Link` jest importowany z `@upsoftware_tech/svarium`, a nie bezpośrednio z `@inertiajs/vue3`.
