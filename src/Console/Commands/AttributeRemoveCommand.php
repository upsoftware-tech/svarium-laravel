<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

class AttributeRemoveCommand extends CoreCommand
{
    protected $signature = 'svarium:attribute.remove
        {--from= : Skąd usunąć: global lub nazwa modułu}
        {--module= : Alias dla --from (nazwa modułu)}
        {--field=* : Nazwa pola do usunięcia (można podać wiele razy)}
        {--force : Usuń bez potwierdzenia}';

    protected $description = 'Usuwa atrybut pola z pliku globalnego lub modułu';

    public function handle(): int
    {
        try {
            $locations = $this->availableLocations();
            $source = $this->resolveSourceLocation($locations);
            $state = $this->readLocationState($source);

            if ($state['entries'] === []) {
                throw new RuntimeException('Wybrane miejsce nie zawiera atrybutów do usunięcia.');
            }

            $fields = $this->resolveFieldNames($state['entries']);
            $missing = array_values(array_filter($fields, static fn (string $field): bool => ! array_key_exists($field, $state['entries'])));
            if ($missing !== []) {
                throw new RuntimeException('Nie znaleziono pól w wybranym miejscu: '.implode(', ', $missing));
            }

            if (! $this->shouldDelete($fields, $source['label'])) {
                $this->line('Anulowano usuwanie.');

                return self::SUCCESS;
            }

            foreach ($fields as $field) {
                unset($state['entries'][$field]);
            }
            $this->writeLocationState($source, $state);

            $this->info('Usunięto pola: '.implode(', ', $fields).'.');
            $this->line('Źródło: '.$source['label']);

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
     * @param array<string, array{id: string, type: string, label: string, file: string, moduleName: string|null}> $locations
     * @return array{id: string, type: string, label: string, file: string, moduleName: string|null}
     */
    protected function resolveSourceLocation(array $locations): array
    {
        $moduleOption = trim((string) $this->option('module'));
        if ($moduleOption !== '') {
            $matched = $this->matchLocation($moduleOption, $locations, true);
            if ($matched === null) {
                throw new RuntimeException("Nie znaleziono modułu: {$moduleOption}");
            }

            return $matched;
        }

        $from = trim((string) $this->option('from'));
        if ($from !== '') {
            $matched = $this->matchLocation($from, $locations, false);
            if ($matched === null) {
                throw new RuntimeException("Nie znaleziono źródła: {$from}");
            }

            return $matched;
        }

        if (! $this->input->isInteractive()) {
            throw new RuntimeException('Brak --from=. Użyj: global albo nazwa modułu.');
        }

        $selected = (string) select(
            label: 'Skąd chcesz usunąć',
            options: $this->locationOptions($locations)
        );

        return $locations[$selected];
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
            throw new RuntimeException('Brak --field=. Podaj co najmniej jedno pole do usunięcia.');
        }

        $keys = array_keys($entries);
        natcasesort($keys);

        $selected = multiselect(
            label: 'Wybierz pola do usunięcia',
            options: array_combine($keys, $keys)
        );

        $selected = array_values(array_filter(
            array_map(static fn ($value): string => trim((string) $value), $selected),
            static fn (string $value): bool => $value !== ''
        ));

        if ($selected === []) {
            throw new RuntimeException('Nie wybrano żadnego pola do usunięcia.');
        }

        return array_values(array_unique($selected));
    }

    protected function shouldDelete(array $fields, string $sourceLabel): bool
    {
        if ((bool) $this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        $count = count($fields);
        $fieldsLabel = implode(', ', $fields);

        return confirm(
            $count === 1
                ? "Czy na pewno usunąć atrybut pola [{$fieldsLabel}] z {$sourceLabel}?"
                : "Czy na pewno usunąć {$count} pól z {$sourceLabel}? ({$fieldsLabel})",
            false,
            'Tak',
            'Nie'
        );
    }

    /**
     * @param array{id: string, type: string, label: string, file: string, moduleName: string|null} $location
     * @return array{
     *     file: string,
     *     content: string,
     *     hasArray: bool,
     *     openPos: int,
     *     closePos: int,
     *     prefixChunks: list<string>,
     *     entries: array<string, string>
     * }
     */
    protected function readLocationState(array $location): array
    {
        if ($location['type'] === 'global') {
            if (! File::exists($location['file'])) {
                throw new RuntimeException('Nie znaleziono pliku globalnego: '.$location['file']);
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
            throw new RuntimeException('Wybrany moduł nie ma metody fieldAttributes().');
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
     *     openPos: int,
     *     closePos: int,
     *     prefixChunks: list<string>,
     *     entries: array<string, string>
     * } $state
     */
    protected function writeLocationState(array $location, array $state): void
    {
        $entries = $state['entries'];
        uksort($entries, static fn (string $left, string $right): int => strcasecmp($left, $right));

        $rebuiltBody = $this->buildArrayBody($state['prefixChunks'], $entries);
        $updated = substr($state['content'], 0, $state['openPos'] + 1)
            .$rebuiltBody
            .substr($state['content'], $state['closePos']);

        File::put($location['file'], $updated);
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
    protected function matchLocation(string $input, array $locations, bool $moduleOnly): ?array
    {
        $value = trim($input);
        if ($value === '') {
            return null;
        }

        if (! $moduleOnly && strcasecmp($value, 'global') === 0) {
            return $locations['global'] ?? null;
        }

        if (! $moduleOnly && isset($locations[$value])) {
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
