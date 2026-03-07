<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

class AttributeMoveCommand extends CoreCommand
{
    protected $signature = 'svarium:attribute.move
        {--from= : Skąd przenieść: global lub nazwa modułu}
        {--to= : Dokąd przenieść: global lub nazwa modułu}
        {--field=* : Nazwa pola do przeniesienia (można podać wiele razy)}
        {--delete-source : Usuń wpis z pliku źródłowego}';

    protected $description = 'Przenosi atrybut pola między plikiem globalnym i modułami';

    public function handle(): int
    {
        try {
            $locations = $this->availableLocations();

            $source = $this->resolveSourceLocation($locations);
            $destination = $this->resolveDestinationLocation($locations, $source['id']);

            $sourceState = $this->readLocationState($source, false);
            if ($sourceState['entries'] === []) {
                throw new RuntimeException('Wybrane źródło nie zawiera żadnych atrybutów do przeniesienia.');
            }

            $fields = $this->resolveFieldNames($sourceState['entries']);
            $missing = array_values(array_filter($fields, static fn (string $field): bool => ! array_key_exists($field, $sourceState['entries'])));
            if ($missing !== []) {
                throw new RuntimeException('Nie znaleziono pól w wybranym źródle: '.implode(', ', $missing));
            }

            $destinationState = $this->readLocationState($destination, true);
            $movedFields = [];
            $skippedFields = [];

            foreach ($fields as $field) {
                if (array_key_exists($field, $destinationState['entries'])) {
                    if (! $this->confirmOverwrite("Atrybut pola [{$field}] już istnieje w miejscu docelowym. Czy chcesz nadpisać?")) {
                        $skippedFields[] = $field;
                        continue;
                    }
                }

                $sourceEntry = (string) $sourceState['entries'][$field];
                $destinationState['entries'][$field] = $this->normalizeEntryForIndent(
                    $sourceEntry,
                    $field,
                    $this->entryIndent($destination['type'])
                );
                $movedFields[] = $field;
            }

            if ($movedFields === []) {
                throw new RuntimeException('Nie przeniesiono żadnego pola (wszystkie zostały pominięte).');
            }

            $this->writeLocationState($destination, $destinationState);

            $removeFromSource = $this->resolveRemoveFromSource();
            if ($removeFromSource) {
                $sourceState = $this->readLocationState($source, false);

                foreach ($movedFields as $field) {
                    if (! array_key_exists($field, $sourceState['entries'])) {
                        continue;
                    }

                    unset($sourceState['entries'][$field]);
                }

                if (count($movedFields) > 0) {
                    $this->writeLocationState($source, $sourceState);
                }
            }

            $this->info('Przeniesiono pola: '.implode(', ', $movedFields).'.');
            if ($skippedFields !== []) {
                $this->line('Pominięto pola: '.implode(', ', $skippedFields).'.');
            }
            $this->line('Źródło: '.$source['label']);
            $this->line('Cel: '.$destination['label']);
            $this->line('Usunięto ze źródła: '.($removeFromSource ? 'tak' : 'nie'));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array<string, array{id: string, type: string, label: string, file: string, moduleName: string|null}>
     */
    protected function availableLocations(): array
    {
        $locations = [
            'global' => [
                'id' => 'global',
                'type' => 'global',
                'label' => 'Globalny plik',
                'file' => base_path('app/Svarium/attributes.php'),
                'moduleName' => null,
            ],
        ];

        foreach ($this->availableModules() as $name => $file) {
            $locations['module:'.$name] = [
                'id' => 'module:'.$name,
                'type' => 'module',
                'label' => 'Moduł: '.$name,
                'file' => $file,
                'moduleName' => $name,
            ];
        }

        return $locations;
    }

    /**
     * @return array{id: string, type: string, label: string, file: string, moduleName: string|null}
     */
    protected function resolveSourceLocation(array $locations): array
    {
        $from = trim((string) $this->option('from'));
        if ($from !== '') {
            $matched = $this->matchLocation($from, $locations);
            if ($matched === null) {
                throw new RuntimeException("Nie znaleziono źródła: {$from}");
            }

            return $matched;
        }

        if (! $this->input->isInteractive()) {
            throw new RuntimeException('Brak --from=. Użyj: global albo nazwa modułu.');
        }

        $selected = (string) select(
            label: 'Skąd chcesz przenieść',
            options: $this->locationOptions($locations)
        );

        return $locations[$selected];
    }

    /**
     * @param string $sourceId
     * @return array{id: string, type: string, label: string, file: string, moduleName: string|null}
     */
    protected function resolveDestinationLocation(array $locations, string $sourceId): array
    {
        $available = $locations;
        unset($available[$sourceId]);

        if ($available === []) {
            throw new RuntimeException('Brak dostępnego miejsca docelowego do przeniesienia.');
        }

        $to = trim((string) $this->option('to'));
        if ($to !== '') {
            $matched = $this->matchLocation($to, $available);
            if ($matched === null) {
                throw new RuntimeException("Nie znaleziono celu: {$to}");
            }

            return $matched;
        }

        if (! $this->input->isInteractive()) {
            throw new RuntimeException('Brak --to=. Użyj: global albo nazwa modułu.');
        }

        $selected = (string) select(
            label: 'Dokąd chcesz przenieść',
            options: $this->locationOptions($available)
        );

        return $available[$selected];
    }

    /**
     * @param array<string, string> $entries
     */
    protected function resolveFieldNames(array $entries): array
    {
        $fieldsOption = $this->option('field');
        $fields = [];

        if (is_array($fieldsOption)) {
            foreach ($fieldsOption as $value) {
                $field = trim((string) $value);
                if ($field !== '') {
                    $fields[] = $field;
                }
            }
        } elseif (is_string($fieldsOption)) {
            $field = trim($fieldsOption);
            if ($field !== '') {
                $fields[] = $field;
            }
        }

        if ($fields !== []) {
            return array_values(array_unique($fields));
        }

        if (! $this->input->isInteractive()) {
            throw new RuntimeException('Brak --field=. Podaj co najmniej jedno pole do przeniesienia.');
        }

        $keys = array_keys($entries);
        natcasesort($keys);

        $selected = multiselect(
            label: 'Wybierz pola do przeniesienia',
            options: array_combine($keys, $keys)
        );

        $selected = array_values(array_filter(
            array_map(static fn ($value): string => trim((string) $value), $selected),
            static fn (string $value): bool => $value !== ''
        ));

        if ($selected === []) {
            throw new RuntimeException('Nie wybrano żadnego pola do przeniesienia.');
        }

        return array_values(array_unique($selected));
    }

    protected function resolveRemoveFromSource(): bool
    {
        if ((bool) $this->option('delete-source')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        return confirm('Czy usunąć z pliku źródłowego?', true, 'Tak', 'Nie');
    }

    protected function confirmOverwrite(string $message): bool
    {
        if (! $this->input->isInteractive()) {
            return false;
        }

        return confirm($message, false, 'Tak', 'Nie');
    }

    protected function entryIndent(string $type): string
    {
        return $type === 'global' ? '    ' : '            ';
    }

    /**
     * @param array{id: string, type: string, label: string, file: string, moduleName: string|null} $location
     * @return array{
     *     file: string,
     *     content: string,
     *     hasArray: bool,
     *     openPos: int|null,
     *     closePos: int|null,
     *     prefixChunks: list<string>,
     *     entries: array<string, string>
     * }
     */
    protected function readLocationState(array $location, bool $forWrite): array
    {
        if ($location['type'] === 'global') {
            if (! File::exists($location['file'])) {
                if (! $forWrite) {
                    throw new RuntimeException('Nie znaleziono pliku globalnego: '.$location['file']);
                }

                File::ensureDirectoryExists(dirname($location['file']));
                File::put($location['file'], $this->defaultGlobalAttributesStub());
            }

            $content = File::get($location['file']);
            [$openPos, $closePos] = $this->locateRootReturnArray($content);
            $arrayBody = substr($content, $openPos + 1, $closePos - $openPos - 1);
            [$prefixChunks, $entries] = $this->parseArrayBody($arrayBody);

            return [
                'file' => $location['file'],
                'content' => $content,
                'hasArray' => true,
                'openPos' => $openPos,
                'closePos' => $closePos,
                'prefixChunks' => $prefixChunks,
                'entries' => $entries,
            ];
        }

        if (! File::exists($location['file'])) {
            throw new RuntimeException('Nie znaleziono pliku modułu: '.$location['file']);
        }

        $content = File::get($location['file']);
        if (preg_match('/public function fieldAttributes\(\): array\s*\{/', $content, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return [
                'file' => $location['file'],
                'content' => $content,
                'hasArray' => false,
                'openPos' => null,
                'closePos' => null,
                'prefixChunks' => [],
                'entries' => [],
            ];
        }

        [$openPos, $closePos] = $this->locateFieldAttributesReturnArray($content);
        $arrayBody = substr($content, $openPos + 1, $closePos - $openPos - 1);
        [$prefixChunks, $entries] = $this->parseArrayBody($arrayBody);

        return [
            'file' => $location['file'],
            'content' => $content,
            'hasArray' => true,
            'openPos' => $openPos,
            'closePos' => $closePos,
            'prefixChunks' => $prefixChunks,
            'entries' => $entries,
        ];
    }

    /**
     * @param array{id: string, type: string, label: string, file: string, moduleName: string|null} $location
     * @param array{
     *     file: string,
     *     content: string,
     *     hasArray: bool,
     *     openPos: int|null,
     *     closePos: int|null,
     *     prefixChunks: list<string>,
     *     entries: array<string, string>
     * } $state
     */
    protected function writeLocationState(array $location, array $state): void
    {
        $entries = $state['entries'];
        uksort($entries, static fn (string $left, string $right): int => strcasecmp($left, $right));

        if ($state['hasArray'] === true && $state['openPos'] !== null && $state['closePos'] !== null) {
            $rebuiltBody = $this->buildArrayBody($state['prefixChunks'], $entries);
            $updated = substr($state['content'], 0, $state['openPos'] + 1)
                .$rebuiltBody
                .substr($state['content'], $state['closePos']);

            File::put($state['file'], $updated);

            return;
        }

        if ($location['type'] !== 'module') {
            throw new RuntimeException('Nie udało się zapisać atrybutów w miejscu docelowym.');
        }

        $method = $this->buildFieldAttributesMethodFromEntries($entries);
        $insertPos = strrpos($state['content'], '}');

        if ($insertPos === false) {
            throw new RuntimeException('Nie udało się znaleźć końca klasy w pliku modułu.');
        }

        $updated = substr($state['content'], 0, $insertPos)
            ."\n"
            .$method
            ."\n"
            .substr($state['content'], $insertPos);

        File::put($state['file'], $updated);
    }

    protected function buildFieldAttributesMethodFromEntries(array $entries): string
    {
        $arrayBody = $this->buildArrayBody([], $entries);

        return <<<PHP
    public function fieldAttributes(): array
    {
        return [{$arrayBody}        ];
    }
PHP;
    }

    protected function normalizeEntryForIndent(string $entryChunk, string $field, string $entryIndent): string
    {
        $normalizedChunk = trim($entryChunk);

        if (preg_match('/^\s*[\'"][^\'"]+[\'"]\s*=>\s*(.+)$/s', $normalizedChunk, $matches) !== 1) {
            return $entryIndent.rtrim($normalizedChunk, ", \t\n\r");
        }

        $value = rtrim(trim((string) $matches[1]), ", \t\n\r");
        $value = $this->normalizeValueIndentation($value, $entryIndent.'    ');

        return $entryIndent.var_export($field, true).' => '.$value;
    }

    protected function normalizeValueIndentation(string $value, string $continuationIndent): string
    {
        if (! str_contains($value, "\n")) {
            return $value;
        }

        $lines = explode("\n", $value);
        $firstLine = ltrim((string) array_shift($lines));

        $minIndent = null;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            preg_match('/^\s*/', $line, $match);
            $indentLength = strlen($match[0] ?? '');
            $minIndent = $minIndent === null ? $indentLength : min($minIndent, $indentLength);
        }

        $minIndent = $minIndent ?? 0;
        $rebuilt = [$firstLine];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                $rebuilt[] = '';
                continue;
            }

            $rebuilt[] = $continuationIndent.substr($line, $minIndent);
        }

        return implode("\n", $rebuilt);
    }

    /**
     * @return array<string, string>
     */
    protected function availableModules(): array
    {
        $root = base_path('app/Svarium/Modules');
        if (! is_dir($root)) {
            return [];
        }

        $modules = [];
        foreach (File::directories($root) as $directory) {
            $name = basename($directory);
            $file = $directory.DIRECTORY_SEPARATOR.$name.'Module.php';
            if (! is_file($file)) {
                continue;
            }

            $modules[$name] = $file;
        }

        ksort($modules);

        return $modules;
    }

    /**
     * @param array<string, array{id: string, type: string, label: string, file: string, moduleName: string|null}> $locations
     * @return array{id: string, type: string, label: string, file: string, moduleName: string|null}|null
     */
    protected function matchLocation(string $input, array $locations): ?array
    {
        $value = trim($input);
        if ($value === '') {
            return null;
        }

        if (strcasecmp($value, 'global') === 0) {
            return $locations['global'] ?? null;
        }

        if (isset($locations[$value])) {
            return $locations[$value];
        }

        if (str_starts_with(strtolower($value), 'module:')) {
            $value = trim(substr($value, 7));
        }

        foreach ($locations as $location) {
            if ($location['type'] !== 'module') {
                continue;
            }

            if (strcasecmp((string) $location['moduleName'], $value) === 0) {
                return $location;
            }
        }

        return null;
    }

    /**
     * @param array<string, array{id: string, type: string, label: string, file: string, moduleName: string|null}> $locations
     * @return array<string, string>
     */
    protected function locationOptions(array $locations): array
    {
        $options = [];
        foreach ($locations as $id => $location) {
            $options[$id] = $location['label'];
        }

        return $options;
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function locateRootReturnArray(string $content): array
    {
        $returnMatch = [];
        if (preg_match('/return\s*\[/', $content, $returnMatch, PREG_OFFSET_CAPTURE) !== 1) {
            throw new RuntimeException('Nie udało się znaleźć tablicy zwracanej w pliku globalnym.');
        }

        $returnPos = (int) $returnMatch[0][1];
        $openPos = strpos($content, '[', $returnPos);

        if ($openPos === false) {
            throw new RuntimeException('Nie udało się znaleźć otwarcia tablicy globalnej.');
        }

        $closePos = $this->findMatchingBracket($content, $openPos, '[', ']');

        return [$openPos, $closePos];
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function locateFieldAttributesReturnArray(string $content): array
    {
        $methodMatch = [];
        if (preg_match('/public function fieldAttributes\(\): array\s*\{/', $content, $methodMatch, PREG_OFFSET_CAPTURE) !== 1) {
            throw new RuntimeException('Nie udało się znaleźć metody fieldAttributes().');
        }

        $methodStart = (int) $methodMatch[0][1];
        $methodOpenBrace = strpos($content, '{', $methodStart);

        if ($methodOpenBrace === false) {
            throw new RuntimeException('Nie udało się znaleźć otwarcia metody fieldAttributes().');
        }

        $methodCloseBrace = $this->findMatchingBracket($content, $methodOpenBrace, '{', '}');
        $methodBody = substr($content, $methodOpenBrace + 1, $methodCloseBrace - $methodOpenBrace - 1);

        $returnMatch = [];
        if (preg_match('/return\s*\[/', $methodBody, $returnMatch, PREG_OFFSET_CAPTURE) !== 1) {
            throw new RuntimeException('Metoda fieldAttributes() nie zawiera tablicy return [...].');
        }

        $returnPosInMethod = (int) $returnMatch[0][1];
        $openPos = strpos($methodBody, '[', $returnPosInMethod);

        if ($openPos === false) {
            throw new RuntimeException('Nie udało się znaleźć otwarcia tablicy w fieldAttributes().');
        }

        $absoluteOpenPos = $methodOpenBrace + 1 + $openPos;
        $absoluteClosePos = $this->findMatchingBracket($content, $absoluteOpenPos, '[', ']');

        return [$absoluteOpenPos, $absoluteClosePos];
    }

    /**
     * @return array{0: list<string>, 1: array<string, string>}
     */
    protected function parseArrayBody(string $arrayBody): array
    {
        $chunks = [];
        $length = strlen($arrayBody);
        $quote = null;
        $escape = false;
        $lineComment = false;
        $blockComment = false;
        $squareDepth = 0;
        $curlyDepth = 0;
        $parenDepth = 0;
        $start = 0;

        for ($index = 0; $index < $length; $index++) {
            $char = $arrayBody[$index];
            $next = $index + 1 < $length ? $arrayBody[$index + 1] : null;

            if ($lineComment) {
                if ($char === "\n") {
                    $lineComment = false;
                }

                continue;
            }

            if ($blockComment) {
                if ($char === '*' && $next === '/') {
                    $blockComment = false;
                    $index++;
                }

                continue;
            }

            if ($quote !== null) {
                if ($escape) {
                    $escape = false;
                    continue;
                }

                if ($char === '\\') {
                    $escape = true;
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '/' && $next === '/') {
                $lineComment = true;
                $index++;
                continue;
            }

            if ($char === '/' && $next === '*') {
                $blockComment = true;
                $index++;
                continue;
            }

            if ($char === '\'' || $char === '"') {
                $quote = $char;
                continue;
            }

            if ($char === '[') {
                $squareDepth++;
                continue;
            }

            if ($char === ']') {
                $squareDepth--;
                continue;
            }

            if ($char === '{') {
                $curlyDepth++;
                continue;
            }

            if ($char === '}') {
                $curlyDepth--;
                continue;
            }

            if ($char === '(') {
                $parenDepth++;
                continue;
            }

            if ($char === ')') {
                $parenDepth--;
                continue;
            }

            if ($char === ',' && $squareDepth === 0 && $curlyDepth === 0 && $parenDepth === 0) {
                $chunks[] = substr($arrayBody, $start, $index - $start);
                $start = $index + 1;
            }
        }

        $tail = substr($arrayBody, $start);
        if (trim($tail) !== '') {
            $chunks[] = $tail;
        }

        $prefixChunks = [];
        $entries = [];

        foreach ($chunks as $chunk) {
            $normalized = $this->trimBlankLines($chunk);
            if ($normalized === '') {
                continue;
            }

            if (preg_match('/^\s*[\'"]([^\'"]+)[\'"]\s*=>/s', $normalized, $matches) === 1) {
                $entries[$matches[1]] = rtrim($normalized, ", \t\n\r");
                continue;
            }

            if (preg_match('/\A(?P<prefix>.*?)(?P<entry>^\s*[\'"](?P<key>[^\'"]+)[\'"]\s*=>.*)\z/sm', $normalized, $matches) === 1) {
                $prefix = $this->trimBlankLines((string) ($matches['prefix'] ?? ''));
                $entryChunk = $this->trimBlankLines((string) ($matches['entry'] ?? ''));
                $key = (string) ($matches['key'] ?? '');

                if ($prefix !== '') {
                    $prefixChunks[] = $prefix;
                }

                if ($entryChunk !== '' && $key !== '') {
                    $entries[$key] = rtrim($entryChunk, ", \t\n\r");
                    continue;
                }
            }

            $prefixChunks[] = $normalized;
        }

        return [$prefixChunks, $entries];
    }

    /**
     * @param list<string> $prefixChunks
     * @param array<string, string> $entries
     */
    protected function buildArrayBody(array $prefixChunks, array $entries): string
    {
        if ($prefixChunks === [] && $entries === []) {
            return '';
        }

        $lines = [];
        foreach ($prefixChunks as $chunk) {
            $lines[] = $chunk;
        }

        foreach ($entries as $chunk) {
            $lines[] = rtrim((string) $chunk, ", \t\n\r").',';
        }

        return "\n".implode("\n", $lines)."\n";
    }

    protected function trimBlankLines(string $value): string
    {
        return preg_replace('/^\s*\n|\n\s*$/', '', $value) ?? trim($value);
    }

    protected function defaultGlobalAttributesStub(): string
    {
        return <<<'PHP'
<?php

return [
    // Global field attributes with highest priority.
    // Example:
    // 'first_name' => __('First name'),
    // 'status' => [
    //     'label' => __('Status'),
    //     'column' => ['sortable' => true],
    //     'input' => ['placeholder' => __('Select status')],
    // ],
];
PHP;
    }

    protected function findMatchingBracket(string $content, int $openPos, string $openChar, string $closeChar): int
    {
        $length = strlen($content);
        $depth = 0;
        $quote = null;
        $escape = false;

        for ($index = $openPos; $index < $length; $index++) {
            $char = $content[$index];

            if ($quote !== null) {
                if ($escape) {
                    $escape = false;
                    continue;
                }

                if ($char === '\\') {
                    $escape = true;
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '\'' || $char === '"') {
                $quote = $char;
                continue;
            }

            if ($char === $openChar) {
                $depth++;
                continue;
            }

            if ($char === $closeChar) {
                $depth--;
                if ($depth === 0) {
                    return $index;
                }
            }
        }

        throw new RuntimeException('Nie udało się dopasować nawiasów podczas edycji pliku.');
    }
}
