# Wbudowane moduły paczki

Svarium ładuje gotowe moduły paczki:

- `User`
- `Role`
- `MyProfile`
- `Otp`
- `ActivityLog`
- `SystemMailbox`
- `SystemMailTemplate`
- `Languages`
- `Translation`

Są rejestrowane automatycznie z paczki, ale moduł aplikacyjny o tej samej nazwie nadpisuje moduł paczkowy.

To znaczy:
- paczka daje działający domyślny panel,
- klient może skopiować moduł do `app/Svarium/Modules/...` i przejąć pełną kontrolę.

## Włączanie i wyłączanie modułów paczki

W konfiguracji możesz wskazać, które wbudowane moduły mają być ładowane:

```php
'modules' => [
    'builtin' => [
        'media' => true,
        'user' => true,
        'role' => true,
        'dictionary' => true,
    ],
],
```

Aktualnie gotowe i używane przez paczkę są:

- `user`
- `role`
- `my_profile`
- `otp`
- `activity_log`
- `system_mailboxes`
- `system_mail_templates`
- `languages`
- `translation`

Pozostałe klucze:

- `media`
- `dictionary`

Jeśli ustawisz:

```php
'user' => false,
```

to paczka nie załaduje wbudowanego modułu `User`, ale nadal możesz mieć własny moduł:

```php
app/Svarium/Modules/User/UserModule.php
```

## Gdzie pojawi się moduł (menu placement)

Dla modułów wbudowanych możesz ustawić miejsce rejestracji wpisu menu:

- `main_menu` - główna nawigacja panelu,
- `sidebar_user` - dropdown komponentu `SidebarUser`,
- `none` - bez wpisu.

Przykład:

```php
'modules' => [
    'placements' => [
        'my_profile' => [
            'target' => 'sidebar_user',
            'order' => 10,
            'icon' => 'lucide:user-round',
        ],
        'system_mail_templates' => [
            'target' => 'main_menu',
            'path' => ['Ustawienia'],
            'path_ids' => ['settings'],
            'order' => 50,
            'icon' => 'lucide:mail-open',
        ],
        'languages' => [
            'target' => 'main_menu',
            'path' => ['Ustawienia'],
            'path_ids' => ['settings'],
            'order' => 60,
            'icon' => 'lucide:languages',
        ],
        'translation' => [
            'target' => 'main_menu',
            'path' => ['Ustawienia'],
            'path_ids' => ['settings'],
            'order' => 70,
            'icon' => 'lucide:book-text',
        ],
    ],
],
```

## Szybka zmiana etykiet w projekcie

Jeśli chcesz tylko zmienić nazwy wbudowanych modułów bez przepisywania ich logiki, dodaj plik:

```php
app/Svarium/labels.php
```

Przykład:

```php
<?php

return [
    'modules' => [
        'user' => [
            'singular' => 'Employee',
            'plural' => 'Employees',
        ],
        'role' => [
            'singular' => 'Permission group',
            'plural' => 'Permission groups',
        ],
    ],
];
```

Aktualnie paczka używa tych etykiet w:

- menu modułów wbudowanych,
- tytułach list,
- tytułach create / edit / duplicate / preview / import.

Możesz też podać tablicę per locale:

```php
<?php

return [
    'modules' => [
        'user' => [
            'singular' => [
                'en' => 'Employee',
                'pl' => 'Pracownik',
            ],
            'plural' => [
                'en' => 'Employees',
                'pl' => 'Pracownicy',
            ],
        ],
    ],
];
```

## Co daje moduł `User`

Domyślnie:
- lista użytkowników,
- create / edit / delete,
- podstawowe pola użytkownika (`name`, `first_name`, `last_name`, `email`, `password`) jeśli istnieją w tabeli,
- przypisywanie ról.

Model użytkownika brany jest z:

```php
config('upsoftware.models.user')
```

czyli standardowo:

```php
\Upsoftware\Svarium\Models\User::class
```

## Co daje moduł `Role`

Domyślnie:
- lista ról,
- create / edit / delete,
- edycja tłumaczeń nazwy roli per locale,
- wybór guarda,
- edycja `role_key` jeśli kolumna istnieje,
- przypisywanie permissionów,
- dodatkowe sekcje ustawień roli, np. języki.

Model roli brany jest z:

```php
config('upsoftware.models.role')
```

oraz z konfiguracji Spatie:

```php
config('permission.models.role')
```

## Permissiony dla modułów i operacji

Moduł `Role` korzysta z katalogu permissionów budowanego runtime.

Domyślnie generowane są permissiony zasobów:

```text
resource.user.list
resource.user.create
resource.user.edit
resource.user.preview
resource.user.duplicate
resource.user.delete
resource.user.import
```

analogicznie dla innych zasobów, np.:

```text
resource.role.list
resource.page.edit
resource.patient.delete
```

Dodatkowo katalog zbiera własne operation spoza auth/resource i pokazuje je w osobnej grupie `Operations`.

Permissiony są automatycznie `firstOrCreate()` przy renderze formularza roli.

## Domyślny bypass dostępu

Wbudowane moduły `User` i `Role` przepuszczają role:

- `admin`
- `superadmin`

oraz dodatkowe role z:

```php
config('upsoftware.auth.tenant_bypass_role_keys', [])
```

To pozwala wejść do tych modułów od razu po instalacji, zanim rozpiszesz własną siatkę permissionów.

## Rozszerzanie ustawień roli z modułów

Każdy moduł może dopisać własne sekcje ustawień roli przez:

```php
public function roleParameters(): array
{
    return [
        'languages' => [
            'label' => __('Languages'),
            'description' => __('Access to selected languages'),
            'options' => [
                ['value' => 'pl', 'label' => 'Polski'],
                ['value' => 'en', 'label' => 'English'],
            ],
        ],
        'locations' => [
            'label' => __('Locations'),
            'description' => __('Access to selected locations'),
            'options' => [
                ['value' => 'krk', 'label' => 'Kraków'],
                ['value' => 'waw', 'label' => 'Warszawa'],
            ],
        ],
    ];
}
```

Paczka zapisuje te dane w `settings` powiązanych z rolą.

Odczyt:

```php
$role->getSetting('languages', []);
$role->getSetting('locations', []);
```

Zapis:

```php
$role->setSetting('languages', ['pl', 'en']);
```

## Nadpisanie modułu paczkowego przez aplikację

Jeśli chcesz przejąć cały moduł `User` albo `Role`, utwórz w aplikacji moduł o tej samej nazwie:

```php
app/Svarium/Modules/User/UserModule.php
app/Svarium/Modules/Role/RoleModule.php
```

Przykład:

```php
<?php

namespace App\Svarium\Modules\User;

use App\Svarium\Modules\User\Panel\UserResource;
use Upsoftware\Svarium\Modules\Module;

class UserModule extends Module
{
    public function name(): string
    {
        return 'User';
    }

    public function register(): void
    {
        $this->registerResource(UserResource::class);
    }
}
```

Ponieważ moduły aplikacyjne są ładowane po modułach paczki, wpis o tej samej nazwie nadpisze wersję wbudowaną.

## Co warto zrobić w projekcie klienta

Najczęściej:

1. zostawić wbudowany `User` i dopisać tylko własne pola / relacje,
2. zostawić wbudowany `Role` i dodać `roleParameters()` dla języków, placówek, lokalizacji,
3. dopiero gdy logika jest mocno niestandardowa, nadpisać cały moduł w `app/Svarium/Modules`.
