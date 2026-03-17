# Warunkowa widoczność pól formularza

Svarium wspiera warunkowe pokazywanie komponentów formularza bez pisania własnego JS.

Najważniejsze metody:

- `->showWhen(...)`
- `->visibleWhen(...)` – alias do `showWhen(...)`

## Podstawowe użycie

```php
use Upsoftware\Svarium\UI\Components\Form\Input;
use Upsoftware\Svarium\UI\Components\Form\Select;

Select::make('auth_type')
    ->label(__('Auth type'))
    ->options([
        ['value' => 'password', 'label' => __('Password')],
        ['value' => 'token', 'label' => __('Token')],
    ]);

Input::make('username')
    ->showWhen('auth_type', 'password');

Input::make('key')
    ->showWhen('auth_type', 'token');
```

Wariant skrócony:

```php
Input::make('username')->showWhen('auth_type', 'password');
```

jest równoważny:

```php
Input::make('username')->showWhen('auth_type', '=', 'password');
```

## Obsługiwane operatory

```php
->showWhen('field')                  // truthy
->showWhen('field', 'value')         // =
->showWhen('field', '=', 'value')
->showWhen('field', '!=', 'value')
->showWhen('field', 'in', ['a', 'b'])
->showWhen('field', 'notIn', ['a', 'b'])
->showWhen('field', 'empty')
->showWhen('field', 'notEmpty')
->showWhen('field', 'truthy')
->showWhen('field', 'falsy')
```

## Alias `visibleWhen`

Jeśli w projekcie lepiej brzmi nazwa biznesowa:

```php
Input::make('username')
    ->visibleWhen('auth_type', 'password');
```

to zachowanie jest identyczne jak przy `showWhen(...)`.

## Działa nie tylko na polach

Warunek możesz przypiąć do różnych komponentów UI, nie tylko do `Input`.

Przykłady:

```php
Block::make()
    ->showWhen('status', 'active');

Grid::make()
    ->showWhen('mode', 'advanced');

Select::make('region')
    ->showWhen('country', 'pl');
```

## Walidacja backendowa

`showWhen(...)` wpływa nie tylko na UI.

Jeśli komponent:

- ma warunek `showWhen(...)`,
- i warunek nie jest spełniony,

to pole jest automatycznie wykluczane z walidacji Laravel przez `Rule::excludeIf(...)`.

Dzięki temu taki przykład działa poprawnie:

```php
Input::make('username')
    ->showWhen('auth_type', 'password')
    ->required();
```

Zachowanie:

- `auth_type = password` – pole jest widoczne i wymagane,
- `auth_type = token` – pole jest ukryte i nie blokuje walidacji.

## Debug formularza

Możesz tymczasowo włączyć debug formularza w `config/upsoftware.php`:

```php
'debug' => [
    'form' => true,
],
```

Po włączeniu zobaczysz:

- `Form visibility store debug`
- `Form errors debug`
- `showWhen debug` przy komponentach z warunkiem

Debug pokazuje m.in.:

- `field`
- `operator`
- `expected`
- `actual`
- `currentValue`
- `visible`
- `store`

To ułatwia diagnozę sytuacji, gdy pole sterujące zmienia wartość, ale komponent nadal się nie pokazuje.

## Przykład pełny

```php
Select::make('auth_type')
    ->label(__('Auth type'))
    ->options([
        ['value' => 'password', 'label' => __('Password')],
        ['value' => 'token', 'label' => __('Token')],
    ])
    ->value('password');

Input::make('username')
    ->label(__('Username'))
    ->showWhen('auth_type', 'password')
    ->required();

Input::make('key')
    ->label(__('API key'))
    ->showWhen('auth_type', 'token')
    ->required();
```

## Uwagi

- warunek porównuje po nazwie pola formularza (`name`)
- jeśli korzystasz z dynamicznych wrapperów formularza, `showWhen(...)` nadal działa, bo stan formularza jest współdzielony przez `Form`
- `vIf(...)` nie jest zamiennikiem dla `showWhen(...)` w formularzach reaktywnych – do zależności między polami używaj `showWhen(...)`
