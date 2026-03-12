# Komponent InputFile (PHP + Vue)

`InputFile` to pole uploadu plików z opcjonalnym auto-uploadem, postępem i podglądem.

## Lokalizacja

- PHP: `Upsoftware\Svarium\UI\Components\Form\InputFile`
- Vue: `packages/svarium-npm/src/components/input/InputFile.vue`

## Podstawowe użycie

```php
use Upsoftware\Svarium\UI\Components\Form\InputFile;

InputFile::make('attachments')
    ->label(__('Files'))
    ->multiple()
    ->extensions(['pdf', 'jpg', 'png'])
    ->maxFile(5);
```

## API (PHP)

`InputFile` dziedziczy po `FieldComponent`, więc wspiera też standardowe:

- `->label(...)`
- `->hint(...)`
- `->required()`, `->rules(...)`, itp.

Dodatkowe metody:

- `->autostart(bool $enabled = true)`  
  Jeśli `true`, plik jest wysyłany od razu po wyborze.

- `->extensions(array|string $extensions)`  
  Dozwolone rozszerzenia, np. `['pdf', 'jpg']`.

- `->fileType(array|string $type)`  
  Typy plików (filtr `accept`), np.:
  - `image`, `video`, `audio`, `pdf`, `document`, `spreadsheet`, `archive`, `any`
  - albo własne MIME, np. `application/json`

- `->uploadUrl(string $url)`  
  Endpoint do autostart uploadu (`POST`).

- `->panelHref(string $path = '', ?string $panel = null)`  
  Skrót do generacji URL uploadu wewnątrz panelu.

- `->afterUpload(string|array $afterUpload, mixed $payload = null)`  
  Akcja po udanym uploadzie:
  - event (np. `upload.finished`)
  - redirect (URL)

- `->afterUploadEvent(string|array $event, mixed $payload = null, string $target = 'window')`
- `->afterUploadRedirect(string $url)`
- `->afterUploadPanelHref(string $path = '', ?string $panel = null)`

- `->multiple(bool $enabled = true)`  
  Wiele plików.

- `->progress(bool $enabled = true)`  
  Pasek postępu uploadu pod polem.

- `->preview(bool|array $config = true)`  
  Włącza preview (lista/grid).

- `->previewLayout(string $layout)`  
  `list` albo `grid`.

- `->previewPosition(string $position)`  
  `top` albo `bottom` (czyli np. preview nad treścią strony/tabelą).

- `->previewColumns(int|string $columns)`  
  Liczba kolumn dla `grid`.

- `->previewImportTile(bool $enabled = true)`  
  Dodatkowy kafelek „Add file” w preview.

- `->previewImportTilePosition(string $position)`  
  `first` albo `last`.

- `->maxFile(int|string $count)`  
  Maksymalna liczba plików.

## Konfiguracja preview

Możesz użyć skrótów fluent (`->previewLayout()`, `->previewPosition()`, …) albo jednego obiektu:

```php
InputFile::make('gallery')
    ->preview([
        'enabled' => true,
        'layout' => 'grid',
        'position' => 'top',
        'columns' => 4,
        'importTile' => true,
        'importTilePosition' => 'first',
    ]);
```

## `afterUpload`: event i redirect

### 1) Event

```php
InputFile::make('documents')
    ->autostart()
    ->uploadUrl('/admin/media/upload')
    ->afterUploadEvent('svarium.upload.finished', ['source' => 'documents']);
```

Na froncie event jest emitowany na `window` (lub `document`), a w `detail` dostajesz:

- `field`
- `payload`
- `uploaded`
- `response`

### 2) Redirect

```php
InputFile::make('avatar')
    ->autostart()
    ->uploadUrl('/admin/media/upload')
    ->afterUploadRedirect('/profile');
```

## Przykład pełny

```php
use Upsoftware\Svarium\UI\Components\Form\InputFile;

InputFile::make('media')
    ->label(__('Media files'))
    ->hint(__('Upload JPG/PNG/PDF files'))
    ->autostart()
    ->uploadUrl('/admin/media/upload')
    ->fileType(['image', 'pdf'])
    ->extensions(['jpg', 'jpeg', 'png', 'pdf'])
    ->multiple()
    ->maxFile(8)
    ->progress(true)
    ->preview(true)
    ->previewLayout('grid')
    ->previewColumns(4)
    ->previewPosition('top')
    ->previewImportTile(true)
    ->previewImportTilePosition('last')
    ->afterUploadEvent('media.uploaded');
```

## Uwaga o backendzie uploadu

Dla `autostart()` endpoint (`uploadUrl`) powinien przyjmować `multipart/form-data` i zwracać JSON.  
Komponent obsługuje elastycznie odpowiedź (`data.files`, `files`, `data`, `items`, `result`).

