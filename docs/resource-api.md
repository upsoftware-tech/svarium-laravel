# Resource CRUD API + ReDoc

Ten mechanizm pozwala włączyć API CRUD bez pisania osobnych operation dla każdego modułu.

## 1. Włącz API dla Resource

W klasie resource dodaj:

```php
public static function api(): bool|array
{
    return true;
}
```

Albo użyj twardego przełącznika (nadrzędnego) w klasie:

```php
protected static bool $api = true;
```

`$api` ma pierwszeństwo nad `api()`:
- `protected static bool $api = false;` -> API wyłączone (nawet jeśli `api()` zwraca `true`/array),
- `protected static bool $api = true;` -> API włączone domyślnie,
- brak `$api` -> używane jest `api()`.

To automatycznie rejestruje endpointy (domyślnie pod `upsoftware.api.prefix`, np. `api/v1`):

- `GET /api/v1/{resource-slug}` – lista
- `POST /api/v1/{resource-slug}` – create
- `GET /api/v1/{resource-slug}/{id}` – show
- `PUT|PATCH /api/v1/{resource-slug}/{id}` – update
- `DELETE /api/v1/{resource-slug}/{id}` – delete

## 2. Konfiguracja zaawansowana

```php
public static function api(): bool|array
{
    return [
        'enabled' => true,
        'uri' => 'catalog/countries',
        'prefix' => true, // dokleja upsoftware.api.prefix
        'group' => 'MSIG API V2', // opcjonalnie: grupa w ReDoc (x-tagGroups)
        'middleware' => ['auth:sanctum'],
        'only' => ['index', 'show'], // opcjonalnie: tylko wybrane operacje
        // 'except' => ['delete'],    // opcjonalnie: wyklucz operacje
    ];
}
```

Domyślnie, gdy nie podasz `only` ani `except`, rejestrowane są wszystkie operacje CRUD.

`only` i `except` akceptują aliasy:
- `list` -> `index`
- `create` -> `store`
- `edit` -> `update`
- `destroy` / `remove` -> `delete`

Możesz też łączyć `only` i `except` (najpierw `only`, potem odjęcie `except`).

## 2a. Opisy operation i parametrów (ReDoc)

W `api()` możesz dodać sekcję `docs` per operacja CRUD:

```php
public static function api(): bool|array
{
    return [
        'enabled' => true,
        'group' => 'Ustawienia',
        'docs' => [
            'index' => [
                'summary' => 'GET /patient',
                'description' => 'Pobiera listę pacjentów z możliwością filtrowania i sortowania.',
                'parameters' => [
                    'q' => [
                        'description' => 'Fraza wyszukiwania po danych pacjenta.',
                        'example' => 'kowalski',
                    ],
                    'per_page' => [
                        'description' => 'Liczba rekordów na stronę.',
                        'default' => 50,
                        'minimum' => 1,
                        'maximum' => 500,
                    ],
                    'stream' => [
                        'description' => 'Czy odpowiedź ma być streamowana.',
                        'type' => 'string',
                        'default' => '_json',
                        'example' => '_json',
                        'options' => [
                            ['value' => 'none', 'description' => 'Brak streamowania (pełny JSON).'],
                            ['value' => '_json', 'description' => 'Stream JSON.'],
                            ['value' => 'ndjson', 'description' => 'Stream NDJSON (newline-delimited).'],
                        ],
                    ],
                ],
            ],
            'show' => [
                'summary' => 'GET /patient/{id}',
                'description' => 'Zwraca szczegóły pacjenta.',
            ],
            'store' => [
                'summary' => 'POST /patient',
                'description' => 'Tworzy nowego pacjenta.',
            ],
            'update' => [
                'summary' => 'PATCH /patient/{id}',
                'description' => 'Aktualizuje dane pacjenta.',
            ],
            'delete' => [
                'summary' => 'DELETE /patient/{id}',
                'description' => 'Usuwa pacjenta.',
            ],
        ],
    ];
}
```

`parameters[*]` wspiera też klucze schemy: `format`, `minimum`, `maximum`, `minLength`, `maxLength`, `minItems`, `maxItems`, `pattern`, `enum`, `enumDescriptions`.

`options` automatycznie buduje `enum` + `x-enumDescriptions`, dzięki czemu ReDoc pokazuje sekcję „Pokaż możliwe opcje”.

## 2c. Metadane OpenAPI na polach formularza

Dla `FieldComponent` możesz ustawić metadane schemy OpenAPI bezpośrednio fluent API:

```php
Input::make('published_at')
    ->apiFormat('date')
    ->example('2026-03-21')
    ->description('Data publikacji.');

Input::make('count')
    ->apiMinimum(1)
    ->apiMaximum(100)
    ->default(1);

Select::make('stream')
    ->options([
        ['value' => 'none', 'label' => 'No stream'],
        ['value' => '_json', 'label' => 'JSON stream'],
        ['value' => 'ndjson', 'label' => 'NDJSON stream'],
    ])
    ->apiOptions([
        ['value' => 'none', 'description' => 'Brak streamowania.'],
        ['value' => '_json', 'description' => 'Stream JSON.'],
        ['value' => 'ndjson', 'description' => 'Stream NDJSON.'],
    ])
    ->example('_json');

Input::make('stream_mode')
    ->default('_json')
    ->example('_json')
    ->description('Tryb streamowania odpowiedzi.')
    ->options([
        ['value' => 'none', 'description' => 'Pełny JSON bez streamu'],
        ['value' => '_json', 'description' => 'JSON stream'],
        ['value' => 'ndjson', 'description' => 'NDJSON stream'],
    ]);
```

Dostępne metody na polach:

- `default(mixed $value)` / `apiDefault(...)`
- `example(mixed $value)` / `apiExample(...)`
- `description(string $text)`
- `apiFormat(string $format)` (`schemaFormat(...)` alias)
- `apiMinimum(...)`, `apiMaximum(...)`
- `apiMinLength(...)`, `apiMaxLength(...)`
- `apiMinItems(...)`, `apiMaxItems(...)`
- `apiPattern(...)`
- `apiEnum(array $values)`
- `apiOptions(array $options)` / `possibleOptions(...)`
- `options(array $options)` (alias do `apiOptions` dla komponentów, które nie mają własnego `options`, np. `Input`)

Analogiczne metody (`apiDefault`, `apiExample`, `apiFormat`, `apiOptions`, itd.) są też dostępne na `Table\Column` do spójnej deklaracji metadanych.

## 2b. Opis API bezpośrednio w klasie Operation

Jeśli masz klasy:

- `app/Svarium/Modules/{Module}/Panel/Operations/{Module}ListOperation`
- `...CreateOperation`, `...EditOperation`, `...DeleteOperation` itd.

to możesz ustawić opis bezpośrednio tam:

```php
class PatientListOperation extends ResourceListOperation
{
    public static function apiSummary(): ?string
    {
        return 'GET /patient';
    }

    public static function apiDescription(): ?string
    {
        return 'Pobiera listę pacjentów z filtrowaniem i sortowaniem.';
    }
}
```

Priorytet źródeł opisu:

1. `Resource::api()['docs'][operation]` (najwyższy)
2. `Operation::apiSummary()` / `Operation::apiDescription()`
3. domyślny opis generowany automatycznie

## 3. Jakie dane są używane z `table()` i `form()`

### Lista (`GET`)
- używa buildera z `table()`,
- respektuje `q/search`, `sort`, `view`, `per_page`, `page`,
- respektuje filtrowanie i sortowanie skonfigurowane w tabeli.

### Create/Update (`POST`, `PUT`, `PATCH`)
- używa schematu z `createForm()/editForm()` lub `form()`,
- przy tabach uwzględnia pola z `formTabs/createTabs/editTabs`,
- walidacja bierze reguły z komponentów formularza.

## 4. OpenAPI / ReDoc

Po włączeniu API w resource:

```bash
php artisan svarium:api.docs
```

Domyślnie:
- ReDoc: `/api/docs`
- Spec JSON: `/api/openapi.json`

Generator OpenAPI:
- wykrywa automatyczne trasy CRUD resource,
- dodaje `requestBody` dla create/update na podstawie pól formularza,
- dodaje query parametry listy (`q`, `sort`, `view`, `per_page`, `page`).
