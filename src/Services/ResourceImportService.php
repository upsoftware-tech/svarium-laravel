<?php

namespace Upsoftware\Svarium\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use SplFileObject;
use Throwable;
use ZipArchive;

class ResourceImportService
{
    public function previewAndStore(object $resource, UploadedFile $file, int $limit = 25): array
    {
        $stored = $this->storeTemporaryFile($file);
        $storedFile = $this->uploadedFileFromState($stored);

        if (! $storedFile instanceof UploadedFile) {
            return [
                'error' => __('Import failed.'),
            ];
        }

        $preview = $this->preview($resource, $storedFile, $limit);

        if (isset($preview['error']) || isset($preview['warning'])) {
            $this->deleteStoredFile($stored);
        }

        return [
            ...$preview,
            'state' => $stored,
        ];
    }

    public function importFromStored(object $resource, array $state): array
    {
        $file = $this->uploadedFileFromState($state);
        if (! $file instanceof UploadedFile) {
            return [
                'error' => __('Import failed.'),
            ];
        }

        try {
            return $this->import($resource, [$file]);
        } finally {
            $this->deleteStoredFile($state);
        }
    }

    public function preview(object $resource, UploadedFile $file, int $limit = 25): array
    {
        $meta = $this->resolveModelMeta($resource);
        if ($meta === null) {
            return [
                'error' => __('Import is not configured for this resource.'),
            ];
        }

        ['columns' => $columns, 'columnMap' => $columnMap] = $meta;

        try {
            $rows = $this->extractRows($file);
        } catch (Throwable) {
            return [
                'error' => __('Import failed.'),
            ];
        }

        if ($rows === []) {
            return [
                'warning' => __('No records were imported.'),
                'headers' => [],
                'rows' => [],
                'totalRows' => 0,
                'importableRows' => 0,
                'previewRows' => 0,
            ];
        }

        $headers = $this->extractPreviewHeaders($rows);
        $previewRows = [];
        $importableRows = 0;
        $previewLimit = max(1, $limit);

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $mapped = $this->mapRowToColumns($row, $columns, $columnMap);
            if ($mapped !== []) {
                $importableRows++;
            }

            if (count($previewRows) >= $previewLimit) {
                continue;
            }

            $normalized = [];
            foreach ($headers as $header) {
                $value = $row[$header] ?? '';
                if (is_scalar($value) || $value === null) {
                    $normalized[$header] = $value === null ? '' : (string) $value;
                    continue;
                }

                $normalized[$header] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            }

            $previewRows[] = $normalized;
        }

        return [
            'headers' => $headers,
            'rows' => $previewRows,
            'totalRows' => count($rows),
            'importableRows' => $importableRows,
            'previewRows' => count($previewRows),
        ];
    }

    public function import(object $resource, array $files): array
    {
        $meta = $this->resolveModelMeta($resource);
        if ($meta === null) {
            return [
                'error' => __('Import is not configured for this resource.'),
            ];
        }

        ['modelClass' => $modelClass, 'columns' => $columns, 'columnMap' => $columnMap] = $meta;
        $imported = 0;
        $skipped = 0;
        $unsupported = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            try {
                $rows = $this->extractRows($file);
            } catch (RuntimeException $e) {
                $extension = strtolower((string) pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
                $unsupported[] = $extension !== '' ? $extension : __('unknown');
                continue;
            } catch (Throwable) {
                $skipped++;
                continue;
            }

            if ($rows === []) {
                continue;
            }

            foreach ($rows as $row) {
                $payload = $this->mapRowToColumns($row, $columns, $columnMap);
                if ($payload === []) {
                    $skipped++;
                    continue;
                }

                try {
                    Model::unguarded(static function () use ($modelClass, $payload): void {
                        /** @var Model $entry */
                        $entry = new $modelClass();
                        $entry->fill($payload);
                        $entry->save();
                    });
                    $imported++;
                } catch (Throwable) {
                    $skipped++;
                }
            }
        }

        if ($imported === 0 && $unsupported !== []) {
            $types = implode(', ', array_values(array_unique(array_filter($unsupported))));

            return [
                'warning' => __('Unsupported import file format: :types', ['types' => $types]),
            ];
        }

        if ($imported === 0) {
            return [
                'warning' => __('No records were imported.'),
            ];
        }

        if ($skipped > 0) {
            return [
                'warning' => __('Imported :count record(s), skipped :skipped row(s).', [
                    'count' => $imported,
                    'skipped' => $skipped,
                ]),
            ];
        }

        return [
            'success' => __('Imported :count record(s).', ['count' => $imported]),
        ];
    }

    protected function resolveModelClass(object $resource): ?string
    {
        if (method_exists($resource, 'model')) {
            $model = $resource::model();

            if (is_string($model) && $model !== '') {
                return $model;
            }
        }

        if (method_exists($resource, 'getModel')) {
            $model = $resource::getModel();

            if (is_string($model) && $model !== '') {
                return $model;
            }
        }

        return null;
    }

    /**
     * @return array{modelClass: class-string<Model>, columns: array<int, string>, columnMap: array<string, string>}|null
     */
    protected function resolveModelMeta(object $resource): ?array
    {
        $modelClass = $this->resolveModelClass($resource);
        if (! is_string($modelClass) || $modelClass === '' || ! class_exists($modelClass)) {
            return null;
        }

        /** @var Model $model */
        $model = new $modelClass();

        $connection = $model->getConnectionName();
        $table = $model->getTable();
        $columns = Schema::connection($connection)->getColumnListing($table);
        if ($columns === []) {
            return null;
        }

        return [
            'modelClass' => $modelClass,
            'columns' => $columns,
            'columnMap' => $this->buildColumnMap($columns),
        ];
    }

    protected function storeTemporaryFile(UploadedFile $file): array
    {
        $disk = 'local';
        $directory = 'svarium/import-temp/'.date('Y/m/d');
        $extension = strtolower((string) pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        $name = (string) Str::uuid();
        if ($extension !== '') {
            $name .= '.'.$extension;
        }

        $path = $file->storeAs($directory, $name, $disk);

        return [
            'disk' => $disk,
            'path' => (string) $path,
            'originalName' => (string) $file->getClientOriginalName(),
        ];
    }

    protected function uploadedFileFromState(array $state): ?UploadedFile
    {
        $disk = trim((string) ($state['disk'] ?? 'local'));
        if ($disk === '') {
            $disk = 'local';
        }

        $path = trim((string) ($state['path'] ?? ''));
        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $absolute = Storage::disk($disk)->path($path);
        if (! is_string($absolute) || $absolute === '' || ! is_file($absolute)) {
            return null;
        }

        $originalName = trim((string) ($state['originalName'] ?? ''));
        if ($originalName === '') {
            $originalName = basename($path);
        }

        return new UploadedFile($absolute, $originalName, null, null, true);
    }

    protected function deleteStoredFile(array $state): void
    {
        $disk = trim((string) ($state['disk'] ?? 'local'));
        if ($disk === '') {
            $disk = 'local';
        }

        $path = trim((string) ($state['path'] ?? ''));
        if ($path === '') {
            return;
        }

        try {
            Storage::disk($disk)->delete($path);
        } catch (Throwable) {
            // Ignore cleanup failures.
        }
    }

    protected function extractPreviewHeaders(array $rows): array
    {
        $headers = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach (array_keys($row) as $key) {
                $header = trim((string) $key);
                if ($header === '' || in_array($header, $headers, true)) {
                    continue;
                }

                $headers[] = $header;
            }
        }

        return $headers;
    }

    protected function extractRows(UploadedFile $file): array
    {
        $extension = strtolower(trim((string) $file->getClientOriginalExtension()));
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '') {
            return [];
        }

        return match ($extension) {
            'csv' => $this->extractDelimitedRows($path, ','),
            'tsv' => $this->extractDelimitedRows($path, "\t"),
            'json' => $this->extractJsonRows($path),
            'xml' => $this->extractXmlRows($path),
            'xlsx' => $this->extractXlsxRows($path),
            'ods' => $this->extractOdsRows($path),
            default => throw new RuntimeException('Unsupported format'),
        };
    }

    protected function extractDelimitedRows(string $path, string $delimiter): array
    {
        $reader = new SplFileObject($path);
        $reader->setFlags(SplFileObject::READ_CSV);
        $reader->setCsvControl($delimiter);

        $rows = [];

        foreach ($reader as $line) {
            if (! is_array($line)) {
                continue;
            }

            if ($line === [null]) {
                continue;
            }

            $rows[] = array_map(static function ($value) {
                return is_string($value) ? trim($value) : $value;
            }, $line);
        }

        return $this->tabularToAssociativeRows($rows);
    }

    protected function extractJsonRows(string $path): array
    {
        $raw = @file_get_contents($path);
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        if (array_is_list($decoded)) {
            return array_values(array_filter($decoded, 'is_array'));
        }

        if (isset($decoded['data']) && is_array($decoded['data']) && array_is_list($decoded['data'])) {
            return array_values(array_filter($decoded['data'], 'is_array'));
        }

        return [$decoded];
    }

    protected function extractXmlRows(string $path): array
    {
        $raw = @file_get_contents($path);
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $xml = @simplexml_load_string($raw);
        if (! $xml instanceof SimpleXMLElement) {
            return [];
        }

        $children = $xml->children();
        if ($children->count() === 0) {
            return [];
        }

        $rows = [];
        foreach ($children as $child) {
            $row = [];
            foreach ($child->children() as $field) {
                $row[$field->getName()] = trim((string) $field);
            }

            if ($row !== []) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    protected function extractXlsxRows(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            return [];
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $shared = $this->parseXlsxSharedStrings($zip);
        $sheetPath = $this->resolveXlsxFirstSheetPath($zip);
        $sheetXml = $sheetPath ? $zip->getFromName($sheetPath) : false;
        $zip->close();

        if (! is_string($sheetXml) || $sheetXml === '') {
            return [];
        }

        $xml = @simplexml_load_string($sheetXml);
        if (! $xml instanceof SimpleXMLElement) {
            return [];
        }

        $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rowNodes = $xml->xpath('//x:sheetData/x:row');
        if (! is_array($rowNodes) || $rowNodes === []) {
            return [];
        }

        $table = [];

        foreach ($rowNodes as $rowNode) {
            $cells = [];
            $cellNodes = $rowNode->xpath('./x:c');
            if (! is_array($cellNodes)) {
                continue;
            }

            foreach ($cellNodes as $cellNode) {
                $ref = (string) ($cellNode['r'] ?? '');
                $index = $this->xlsxCellReferenceToIndex($ref);
                $value = $this->resolveXlsxCellValue($cellNode, $shared);

                if ($index < 0) {
                    $cells[] = $value;
                    continue;
                }

                $cells[$index] = $value;
            }

            if ($cells === []) {
                continue;
            }

            ksort($cells);
            $table[] = array_values($cells);
        }

        return $this->tabularToAssociativeRows($table);
    }

    protected function parseXlsxSharedStrings(ZipArchive $zip): array
    {
        $raw = $zip->getFromName('xl/sharedStrings.xml');
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $xml = @simplexml_load_string($raw);
        if (! $xml instanceof SimpleXMLElement) {
            return [];
        }

        $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $items = $xml->xpath('//x:si');
        if (! is_array($items)) {
            return [];
        }

        $shared = [];
        foreach ($items as $item) {
            $textParts = $item->xpath('.//x:t');
            if (! is_array($textParts) || $textParts === []) {
                $shared[] = trim((string) $item);
                continue;
            }

            $value = '';
            foreach ($textParts as $part) {
                $value .= (string) $part;
            }
            $shared[] = trim($value);
        }

        return $shared;
    }

    protected function resolveXlsxFirstSheetPath(ZipArchive $zip): ?string
    {
        $workbookRaw = $zip->getFromName('xl/workbook.xml');
        if (! is_string($workbookRaw) || $workbookRaw === '') {
            return $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        }

        $workbook = @simplexml_load_string($workbookRaw);
        if (! $workbook instanceof SimpleXMLElement) {
            return $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        }

        $workbook->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $sheetNodes = $workbook->xpath('//x:sheets/x:sheet');
        $firstSheet = is_array($sheetNodes) && $sheetNodes !== [] ? $sheetNodes[0] : null;
        if (! $firstSheet instanceof SimpleXMLElement) {
            return $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        }

        $relationsRaw = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (! is_string($relationsRaw) || $relationsRaw === '') {
            return $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        }

        $rels = @simplexml_load_string($relationsRaw);
        if (! $rels instanceof SimpleXMLElement) {
            return $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        }

        $relId = (string) $firstSheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
        if ($relId === '') {
            return $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        }

        foreach ($rels->Relationship as $relation) {
            if ((string) ($relation['Id'] ?? '') !== $relId) {
                continue;
            }

            $target = ltrim((string) ($relation['Target'] ?? ''), '/');
            if ($target === '') {
                continue;
            }

            $sheetPath = Str::startsWith($target, 'worksheets/')
                ? 'xl/'.$target
                : 'xl/worksheets/'.$target;

            if ($zip->locateName($sheetPath) !== false) {
                return $sheetPath;
            }
        }

        return $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
    }

    protected function resolveXlsxCellValue(SimpleXMLElement $cellNode, array $shared): string
    {
        $type = strtolower((string) ($cellNode['t'] ?? ''));

        if ($type === 'inlineStr') {
            $parts = $cellNode->xpath('.//x:t');
            if (is_array($parts) && $parts !== []) {
                $value = '';
                foreach ($parts as $part) {
                    $value .= (string) $part;
                }

                return trim($value);
            }

            return trim((string) $cellNode);
        }

        $raw = trim((string) ($cellNode->v ?? ''));
        if ($raw === '') {
            return '';
        }

        if ($type === 's') {
            $index = (int) $raw;

            return (string) ($shared[$index] ?? '');
        }

        if ($type === 'b') {
            return $raw === '1' ? '1' : '0';
        }

        return $raw;
    }

    protected function xlsxCellReferenceToIndex(string $reference): int
    {
        if (! preg_match('/^([A-Z]+)/i', $reference, $matches)) {
            return -1;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    protected function extractOdsRows(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            return [];
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $content = $zip->getFromName('content.xml');
        $zip->close();

        if (! is_string($content) || $content === '') {
            return [];
        }

        $xml = @simplexml_load_string($content);
        if (! $xml instanceof SimpleXMLElement) {
            return [];
        }

        $xml->registerXPathNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
        $xml->registerXPathNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');

        $rowNodes = $xml->xpath('//table:table[1]/table:table-row');
        if (! is_array($rowNodes) || $rowNodes === []) {
            return [];
        }

        $matrix = [];

        foreach ($rowNodes as $rowNode) {
            $row = [];
            $cellNodes = $rowNode->xpath('./table:table-cell');
            if (! is_array($cellNodes)) {
                continue;
            }

            foreach ($cellNodes as $cellNode) {
                $textParts = $cellNode->xpath('.//text:p');
                $value = '';

                if (is_array($textParts) && $textParts !== []) {
                    $segments = [];
                    foreach ($textParts as $part) {
                        $segments[] = trim((string) $part);
                    }
                    $value = trim(implode(' ', array_filter($segments, static fn ($segment) => $segment !== '')));
                }

                $repeat = (int) ($cellNode->attributes('urn:oasis:names:tc:opendocument:xmlns:table:1.0')['number-columns-repeated'] ?? 1);
                $repeat = max(1, $repeat);

                for ($i = 0; $i < $repeat; $i++) {
                    $row[] = $value;
                }
            }

            if ($row !== []) {
                $matrix[] = $row;
            }
        }

        return $this->tabularToAssociativeRows($matrix);
    }

    protected function tabularToAssociativeRows(array $matrix): array
    {
        if ($matrix === []) {
            return [];
        }

        $headerRow = array_shift($matrix);
        if (! is_array($headerRow) || $headerRow === []) {
            return [];
        }

        $headers = [];
        foreach (array_values($headerRow) as $index => $value) {
            $label = trim((string) $value);
            $headers[] = $label !== '' ? $label : "column_{$index}";
        }

        $rows = [];
        foreach ($matrix as $line) {
            if (! is_array($line)) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $value = $line[$index] ?? null;
                if (is_string($value)) {
                    $value = trim($value);
                }

                $row[$header] = $value;
            }

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    protected function buildColumnMap(array $columns): array
    {
        $map = [];

        foreach ($columns as $column) {
            $actual = (string) $column;
            $normalized = $this->normalizeColumnKey($actual);
            if ($normalized !== '') {
                $map[$normalized] = $actual;
            }

            $map[strtolower($actual)] = $actual;
        }

        return $map;
    }

    protected function mapRowToColumns(array $row, array $columns, array $columnMap): array
    {
        $blocked = ['id'];
        $payload = [];

        foreach ($row as $key => $value) {
            $header = trim((string) $key);
            if ($header === '') {
                continue;
            }

            $directKey = strtolower($header);
            $normalized = $this->normalizeColumnKey($header);

            $column = $columnMap[$directKey] ?? $columnMap[$normalized] ?? null;
            if (! is_string($column) || $column === '') {
                continue;
            }

            if (in_array($column, $blocked, true)) {
                continue;
            }

            if (! in_array($column, $columns, true)) {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    $value = null;
                }
            }

            $payload[$column] = $value;
        }

        return $payload;
    }

    protected function normalizeColumnKey(string $value): string
    {
        $normalized = Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();

        return (string) $normalized;
    }

    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            return false;
        }

        return true;
    }
}
