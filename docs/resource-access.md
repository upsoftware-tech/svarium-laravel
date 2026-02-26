# Resource field access (`access()`)

Mechanizm `access()` pozwala sterować widocznością i edycją pól formularza per user.

## Gdzie definiować

W klasie Resource, np. `App\Svarium\Modules\Page\Panel\PageResource`:

```php
public function access(): array
{
    return [
        'title' => [
            'default' => 'hidden',
            'view' => 'perm:page.field.title.view',
            'edit' => 'perm:page.field.title.edit',
            'table' => 'perm:page.field.title.view',
        ],
        'slug' => [
            'default' => ['perm:page.field.slug.view', 'role:1', 'role:admin', 'user:10', 'group:client_premium'],
            'edit' => 'perm:page.field.slug.edit',
            'table' => [
                'default' => false,
                'view' => ['perm:page.field.slug.view', 'role:admin'],
            ],
        ],
        'content' => [
            'default' => 'hidden',
            'view' => true,
            'edit' => 'perm:page.field.content.edit',
        ],
    ];
}
```

## Tryby pola

Każde pole kończy w jednym z trybów:

- `edit` - pole jest widoczne i edytowalne.
- `view` - pole jest widoczne, ale ustawione jako `readonly` i `disabled`.
- `hidden` - pole jest ukryte (nie trafia do schemy renderowanej).

## Dostęp tabeli (`table`)

Dla każdego pola możesz opcjonalnie dodać sekcję `table`, która steruje widocznością kolumny w tabeli.

Jeśli `table` nie jest zdefiniowane, tabela dziedziczy reguły pola (`view/edit/default`).

Dopuszczalne wartości `table`:

- `true` / `false`
- `'view'`, `'edit'`, `'hidden'`
- token (`perm:...`, `role:...`, `user:...`, `group:...`)
- tablica reguł (OR), `any`, `all`
- pełna definicja trybów:
  - `['default' => false, 'view' => 'perm:...', 'edit' => 'perm:...']`

Przykład:

```php
'title' => [
    'default' => 'hidden',
    'view' => 'perm:page.field.title.view',
    'edit' => 'perm:page.field.title.edit',
    'table' => [
        'default' => false,
        'view' => 'perm:page.field.title.view',
    ],
],
```

## Priorytet reguł

Dla każdego pola obowiązuje stała kolejność:

1. jeśli `edit` przejdzie -> `edit`
2. w przeciwnym razie jeśli `view` przejdzie -> `view`
3. w przeciwnym razie używany jest `default`

`default` może być:

- jawny tryb: `'edit' | 'view' | 'hidden'`
- reguła logiczna (`string`, `array`, `true/false`) - gdy przejdzie, wynik to `view`, gdy nie przejdzie, wynik to `hidden`

## Dozwolone formaty reguł

Reguła może być:

- `true` / `false`
- `string`
- `array` (OR)
- `['any' => [...]]` (OR)
- `['all' => [...]]` (AND)

### Tokeny string

- `perm:permission.name` albo `permission:permission.name`
- `role:1` (id roli) lub `role:admin` (nazwa roli)
- `user:10` (id usera) lub `user:john` (name/username/email)
- `group:client_premium` (nazwa grupy)
- bez prefiksu, np. `page.field.title.edit` -> traktowane jako permission

## Jak system sprawdza usera

User pobierany jest z:

1. `$context->request()->user()`
2. fallback: `auth()->user()`

Sprawdzanie permission:

- preferowane: `$user->can(...)`
- fallback: `$user->hasPermissionTo(...)`

Sprawdzanie roli:

- preferowane: `$user->hasRole(...)`
- fallback: kolekcja `$user->roles` (`id`/`name`)

Sprawdzanie grupy:

- metody: `hasGroup`, `inGroup`, `hasAnyGroup`, `inAnyGroup`
- fallback: kolekcja `$user->groups` (`id`/`name`)

Dodatkowo `user:client_premium` ma fallback do grupy dla kompatybilności.

## Gdzie jest egzekwowane

Mechanizm działa w `Operation::filterByOperation()` i wpływa na 3 etapy:

1. Render formularza
2. Walidacja
3. Zapis (`fill()`/save)

To oznacza:

- pola `hidden` nie są renderowane i nie są zapisywane
- pola `view` są renderowane, ale nie są walidowane ani zapisywane
- tylko pola `edit` mogą przejść walidację i zostać zapisane
- kolumny tabeli są automatycznie filtrowane wg `table` (lub wg reguł pola, jeśli `table` brak)

Dzięki temu backend wymusza dostęp niezależnie od tego, co użytkownik wyśle ręcznie w request.

## Dobre praktyki

- Używaj prefiksów (`perm:`, `role:`, `user:`, `group:`) dla czytelności.
- Trzymaj `default` głównie jako stan (`hidden` lub `view`), a warunki w `view`/`edit`.
- Najlepiej utrzymywać nazewnictwo permission per pole, np.:
  - `page.field.title.view`
  - `page.field.title.edit`

## Powiązane miejsca w kodzie

- `Upsoftware\Svarium\Panel\Resource::access()`
- `Upsoftware\Svarium\Panel\Operation::filterByOperation()`
- `Upsoftware\Svarium\Panel\Operation::collectRules()`
- `Upsoftware\Svarium\Panel\Operation::collectFieldNames()`
