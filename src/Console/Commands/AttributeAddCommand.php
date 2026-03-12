<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use Upsoftware\Svarium\Console\Commands\Concerns\InteractsWithSvariumTranslations;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class AttributeAddCommand extends CoreCommand
{
    use InteractsWithSvariumTranslations;

    protected $signature = 'svarium:attribute.add
        {--field= : Nazwa pola, np. first_name}
        {--label= : Etykieta pola, np. First name}
        {--module= : Nazwa modułu, np. Patient}
        {--translations : Dodaj od razu tłumaczenia dla etykiety}';

    protected $description = 'Dodaje atrybut pola do globalnych atrybutów lub do modułu';

    public function handle(): int
    {
        try {
            $field = $this->resolveFieldName();
            $label = $this->resolveFieldLabel($field);
            $module = $this->resolveTargetModule();
            $locationId = 'global';

            if ($module !== null) {
                $this->addAttributeToModule($module['name'], $module['file'], $field, $label);
                $locationId = 'module:'.$module['name'];

                $this->info("Dodano atrybut do modułu {$module['name']}.");
                $this->line('Plik: '.$module['file']);
            } else {
                $file = $this->addAttributeToGlobalFile($field, $label);

                $this->info('Dodano atrybut globalny.');
                $this->line('Plik: '.$file);
            }

            $this->maybeAddTranslations($locationId, $label);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    protected function resolveFieldName(): string
    {
        $field = trim((string) $this->option('field'));

        if ($field === '' && $this->input->isInteractive()) {
            while ($field === '') {
                $field = trim((string) text('Podaj nazwę pola', 'np. first_name'));
            }
        }

        if ($field === '') {
            throw new RuntimeException('Nazwa pola jest wymagana. Użyj --field=');
        }

        return $field;
    }

    protected function resolveFieldLabel(string $field): string
    {
        $label = trim((string) $this->option('label'));

        if ($label !== '') {
            return $label;
        }

        $default = (string) Str::of($field)
            ->replace(['.', '_', '-'], ' ')
            ->squish()
            ->title();

        if (! $this->input->isInteractive()) {
            if ($default === '') {
                throw new RuntimeException('Etykieta pola jest wymagana. Użyj --label=');
            }

            return $default;
        }

        while ($label === '') {
            $label = trim((string) text('Podaj etykietę pola', $default !== '' ? $default : 'np. First name'));
        }

        return $label;
    }

    /**
     * @return array{name: string, file: string}|null
     */
    protected function resolveTargetModule(): ?array
    {
        $modules = $this->availableModules();
        $moduleOption = trim((string) $this->option('module'));

        if ($moduleOption !== '') {
            $resolved = $this->matchModule($moduleOption, $modules);

            if ($resolved === null) {
                throw new RuntimeException("Nie znaleziono modułu: {$moduleOption}");
            }

            return $resolved;
        }

        if (! $this->input->isInteractive()) {
            return null;
        }

        $addToModule = confirm('Czy dodać atrybut do modułu?', false, 'Tak', 'Nie');

        if (! $addToModule) {
            return null;
        }

        if ($modules === []) {
            throw new RuntimeException('Nie znaleziono żadnych modułów w app/Svarium/Modules.');
        }

        $selected = (string) select(
            label: 'Wybierz moduł',
            options: array_combine(array_keys($modules), array_keys($modules))
        );

        return [
            'name' => $selected,
            'file' => $modules[$selected],
        ];
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
     * @param array<string, string> $modules
     * @return array{name: string, file: string}|null
     */
    protected function matchModule(string $input, array $modules): ?array
    {
        foreach ($modules as $name => $file) {
            if (strcasecmp($name, trim($input)) !== 0) {
                continue;
            }

            return [
                'name' => $name,
                'file' => $file,
            ];
        }

        return null;
    }

    protected function addAttributeToGlobalFile(string $field, string $label): string
    {
        $file = base_path('app/Svarium/attributes.php');

        if (! File::exists($file)) {
            File::ensureDirectoryExists(dirname($file));
            File::put($file, $this->defaultGlobalAttributesStub());
        }

        $content = File::get($file);
        [$openPos, $closePos] = $this->locateRootReturnArray($content);

        if ($this->arrayContainsField($content, $openPos, $closePos, $field)) {
            if (! $this->confirmOverwrite("Atrybut pola [{$field}] już istnieje w pliku globalnym. Czy chcesz nadpisać?")) {
                throw new RuntimeException("Atrybut pola [{$field}] już istnieje w pliku globalnym.");
            }
        }

        $updated = $this->insertEntryIntoArray($content, $openPos, $closePos, $field, $this->buildAttributeEntry($field, $label), '    ');
        File::put($file, $updated);

        return $file;
    }

    protected function addAttributeToModule(string $moduleName, string $file, string $field, string $label): void
    {
        if (! File::exists($file)) {
            throw new RuntimeException("Nie znaleziono pliku modułu: {$file}");
        }

        $content = File::get($file);

        if (preg_match('/public function fieldAttributes\(\): array\s*\{/', $content, $match, PREG_OFFSET_CAPTURE) === 1) {
            [$openPos, $closePos] = $this->locateFieldAttributesReturnArray($content);
            if ($this->arrayContainsField($content, $openPos, $closePos, $field)) {
                if (! $this->confirmOverwrite("Atrybut pola [{$field}] już istnieje w module {$moduleName}. Czy chcesz nadpisać?")) {
                    throw new RuntimeException("Atrybut pola [{$field}] już istnieje w module {$moduleName}.");
                }
            }

            $updated = $this->insertEntryIntoArray($content, $openPos, $closePos, $field, $this->buildAttributeEntry($field, $label), '            ');
            File::put($file, $updated);

            return;
        }

        $method = $this->buildFieldAttributesMethod($field, $label);
        $insertPos = strrpos($content, '}');

        if ($insertPos === false) {
            throw new RuntimeException("Nie udało się znaleźć końca klasy w pliku {$file}");
        }

        $updated = substr($content, 0, $insertPos)
            ."\n"
            .$method
            ."\n"
            .substr($content, $insertPos);

        File::put($file, $updated);
    }

    protected function buildAttributeEntry(string $field, string $label): string
    {
        return var_export($field, true).' => '.var_export($label, true).',';
    }

    protected function buildFieldAttributesMethod(string $field, string $label): string
    {
        $entry = $this->buildAttributeEntry($field, $label);

        return <<<PHP
    public function fieldAttributes(): array
    {
        return [
            {$entry}
        ];
    }
PHP;
    }

    protected function defaultGlobalAttributesStub(): string
    {
        return <<<'PHP'
<?php

return [
    // Global field attributes with highest priority.
    // Example:
    // 'first_name' => 'First name',
    // 'status' => [
    //     'label' => 'Status',
    //     'column' => ['sortable' => true],
    //     'input' => ['placeholder' => 'Select status'],
    // ],
];
PHP;
    }

    protected function confirmOverwrite(string $message): bool
    {
        if (! $this->input->isInteractive()) {
            return false;
        }

        return confirm($message, false, 'Tak', 'Nie');
    }

    protected function maybeAddTranslations(string $targetLocationId, string $key): void
    {
        $key = trim($key);
        if ($key === '') {
            return;
        }

        if (! $this->shouldAddTranslations()) {
            return;
        }

        $localeOptions = $this->availableLocaleOptions();
        $locales = array_keys($localeOptions);

        if ($locales === []) {
            $this->warn('Nie znaleziono języków. Pomijam dodanie tłumaczeń.');

            return;
        }

        $targetsByLocale = [];
        $existingLocales = [];

        foreach ($locales as $locale) {
            $locations = $this->translationLocations($locale);
            $target = $locations[$targetLocationId] ?? null;

            if ($target === null) {
                throw new RuntimeException("Nie znaleziono lokalizacji tłumaczeń [{$targetLocationId}] dla locale [{$locale}].");
            }

            $targetsByLocale[$locale] = $target;

            if (! is_file($target['file'])) {
                continue;
            }

            $translations = $this->loadTranslationFile($target['file']);
            if (array_key_exists($key, $translations)) {
                $existingLocales[] = strtoupper($locale);
            }
        }

        if ($existingLocales !== []) {
            $existingLocales = array_values(array_unique($existingLocales));

            if (! $this->confirmOverwrite(
                'Klucz tłumaczenia ['.$key.'] już istnieje dla locale: '.implode(', ', $existingLocales).'. Czy chcesz nadpisać?'
            )) {
                $this->warn('Pomijam dodawanie tłumaczeń.');

                return;
            }
        }

        $valuesByLocale = $this->resolveTranslationValues($key, $locales, $localeOptions);

        foreach ($locales as $locale) {
            $target = $targetsByLocale[$locale];
            $this->ensureTranslationFile($target['file']);

            $translations = $this->loadTranslationFile($target['file']);
            $translations[$key] = $valuesByLocale[$locale] ?? $key;
            $this->saveTranslationFile($target['file'], $translations);
        }

        foreach ($locales as $locale) {
            $this->syncPreparedTranslations($locale);
        }

        $sampleTarget = $targetsByLocale[$locales[0]];
        $this->info(
            'Dodano tłumaczenia klucza ['.$key.'] dla locale: '.implode(', ', array_map('strtoupper', $locales)).'.'
        );
        $this->line('Miejsce: '.$sampleTarget['label']);
        $this->line('Przykładowy plik: '.$sampleTarget['file']);
    }

    protected function shouldAddTranslations(): bool
    {
        if ((bool) $this->option('translations')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        return confirm('Czy dodać tłumaczenia dla etykiety?', false, 'Tak', 'Nie');
    }

    /**
     * @param array<int, string> $locales
     * @param array<string, string> $localeOptions
     * @return array<string, string>
     */
    protected function resolveTranslationValues(string $key, array $locales, array $localeOptions): array
    {
        if (! $this->input->isInteractive()) {
            $values = [];
            foreach ($locales as $locale) {
                $values[$locale] = $key;
            }

            return $values;
        }

        $values = [];

        foreach ($locales as $locale) {
            $localeLabel = strtoupper($locale);
            if (isset($localeOptions[$locale]) && trim((string) $localeOptions[$locale]) !== '') {
                $localeLabel = strtoupper($locale);
            }

            $value = (string) text(
                "Wprowadź tłumaczenie ({$key}) dla {$localeLabel}",
                'puste = klucz',
                ''
            );

            $values[$locale] = trim($value) === '' ? $key : $value;
        }

        return $values;
    }

    protected function locateRootReturnArray(string $content): array
    {
        $returnMatch = [];
        if (preg_match('/return\s*\[/', $content, $returnMatch, PREG_OFFSET_CAPTURE) !== 1) {
            throw new RuntimeException('Nie udało się znaleźć tablicy zwracanej w pliku atrybutów.');
        }

        $returnPos = (int) $returnMatch[0][1];
        $openPos = strpos($content, '[', $returnPos);

        if ($openPos === false) {
            throw new RuntimeException('Nie udało się znaleźć otwarcia tablicy atrybutów.');
        }

        $closePos = $this->findMatchingBracket($content, $openPos, '[', ']');

        return [$openPos, $closePos];
    }

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

    protected function arrayContainsField(string $content, int $openPos, int $closePos, string $field): bool
    {
        $arrayBody = substr($content, $openPos + 1, $closePos - $openPos - 1);
        [, $entries] = $this->parseArrayBody($arrayBody);

        return array_key_exists($field, $entries);
    }

    protected function insertEntryIntoArray(
        string $content,
        int $openPos,
        int $closePos,
        string $field,
        string $entry,
        string $entryIndent
    ): string {
        $arrayBody = substr($content, $openPos + 1, $closePos - $openPos - 1);
        [$prefixChunks, $entries] = $this->parseArrayBody($arrayBody);

        $entries[$field] = $entryIndent.$entry;

        uksort($entries, static fn (string $left, string $right): int => strcasecmp($left, $right));

        $rebuiltBody = $this->buildArrayBody($prefixChunks, $entries);

        return substr($content, 0, $openPos + 1)
            .$rebuiltBody
            .substr($content, $closePos);
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
            $lines[] = rtrim($chunk, ", \t\n\r").',';
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
