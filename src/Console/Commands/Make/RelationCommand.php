<?php

namespace Upsoftware\Svarium\Console\Commands\Make;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Upsoftware\Svarium\Console\Commands\CoreCommand;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class RelationCommand extends CoreCommand
{
    protected $signature = 'svarium:make.relation
        {--module= : Module name (e.g. Patient)}
        {--model= : Model name (e.g. Visit)}
        {--relation= : Relation method name (e.g. patient)}
        {--type= : Relation type: belongsTo|hasOne|hasMany}
        {--related= : Related model class (FQCN) or short name (e.g. Patient)}
        {--foreign-key= : Foreign key column name}
        {--owner-key= : Owner key for belongsTo (default: id)}
        {--local-key= : Local key for hasOne/hasMany (default: id)}
        {--force : Overwrite relation method when it already exists}';

    protected $description = 'Create Eloquent relation method in selected model';
    protected $descriptionKey = 'make.relation';

    public function handle(): int
    {
        $module = $this->resolveModuleName();
        if ($module === false) {
            return self::FAILURE;
        }

        $target = $this->resolveModelTarget($module);
        if ($target === null) {
            return self::FAILURE;
        }

        $relationName = $this->resolveRelationName();
        if ($relationName === '') {
            $this->error('Nazwa relacji jest wymagana.');
            return self::FAILURE;
        }

        $relationType = $this->resolveRelationType();
        if ($relationType === null) {
            return self::FAILURE;
        }

        $relatedModelClass = $this->resolveRelatedModelClass($module, $relationName);
        if ($relatedModelClass === null) {
            return self::FAILURE;
        }

        $result = $this->appendMethodToModel(
            modelPath: $target['path'],
            relationName: $relationName,
            relationType: $relationType,
            relatedModelClass: $relatedModelClass,
            modelName: $target['modelName']
        );

        if (! $result) {
            return self::FAILURE;
        }

        $this->info("Relacja [{$relationName}] została dodana do modelu [{$target['modelName']}].");
        $this->line("<href=file://{$target['path']}>{$target['path']}</>");

        return self::SUCCESS;
    }

    /**
     * @return string|false|null
     */
    protected function resolveModuleName(): string|false|null
    {
        $availableModules = $this->availableModules();
        $moduleOption = trim((string) $this->option('module'));

        if ($moduleOption !== '') {
            $normalized = Str::studly($moduleOption);

            if (! in_array($normalized, $availableModules, true)) {
                $this->error("Moduł [{$moduleOption}] nie istnieje.");
                return false;
            }

            return $normalized;
        }

        if (! $this->input->isInteractive()) {
            return null;
        }

        $options = ['__global__' => 'Globalny model (app/Models)'];
        foreach ($availableModules as $module) {
            $options[$module] = "Moduł: {$module}";
        }

        $selected = (string) select(
            'Jaki moduł?',
            $options,
            '__global__'
        );

        if ($selected === '__global__') {
            return null;
        }

        return $selected;
    }

    /**
     * @return array{modelName: string, path: string}|null
     */
    protected function resolveModelTarget(?string $module): ?array
    {
        $optionModel = trim((string) $this->option('model'));

        while (true) {
            $raw = $optionModel;

            if ($raw === '' && $this->input->isInteractive()) {
                $available = $this->availableModels($module);

                if ($available !== []) {
                    $options = ['__manual__' => 'Wpisz ręcznie'];
                    foreach ($available as $name) {
                        $options[$name] = $name;
                    }

                    $picked = (string) select('Jaki model?', $options, array_key_first($options));
                    if ($picked !== '__manual__') {
                        $raw = $picked;
                    }
                }

                if ($raw === '') {
                    $raw = trim((string) text('Jaki model?', 'np. Visit', ''));
                }
            }

            $modelName = Str::studly(class_basename(str_replace('\\', '/', $raw)));

            if ($modelName === '') {
                if (! $this->input->isInteractive()) {
                    $this->error('Model jest wymagany. Użyj opcji --model=');
                    return null;
                }

                $this->error('Nazwa modelu jest wymagana.');
                $optionModel = '';
                continue;
            }

            $path = $module !== null
                ? svarium_modules("{$module}/Models/{$modelName}.php")
                : app_path("Models/{$modelName}.php");

            if (File::exists($path)) {
                return [
                    'modelName' => $modelName,
                    'path' => $path,
                ];
            }

            if (! $this->input->isInteractive()) {
                $this->error("Model [{$modelName}] nie istnieje: {$path}");
                return null;
            }

            $this->error("Nie znaleziono modelu: {$path}");
            $optionModel = '';
        }
    }

    protected function resolveRelationName(): string
    {
        $raw = trim((string) $this->option('relation'));

        if ($raw === '' && $this->input->isInteractive()) {
            while ($raw === '') {
                $raw = trim((string) text('Nazwa relacji', 'np. patient', ''));
                if ($raw === '') {
                    $this->error('Nazwa relacji jest wymagana.');
                }
            }
        }

        if ($raw === '') {
            return '';
        }

        return Str::camel($raw);
    }

    protected function resolveRelationType(): ?string
    {
        $allowed = ['belongsTo', 'hasOne', 'hasMany'];
        $raw = trim((string) $this->option('type'));

        if ($raw !== '') {
            $normalized = Str::camel($raw);

            if (! in_array($normalized, $allowed, true)) {
                $this->error('Niepoprawny typ relacji. Dozwolone: belongsTo, hasOne, hasMany.');
                return null;
            }

            return $normalized;
        }

        if (! $this->input->isInteractive()) {
            return 'belongsTo';
        }

        return (string) select('Typ relacji (opis: kiedy stosować)', [
            'belongsTo' => 'belongsTo - klucz obcy jest w tym modelu (np. visits.patient_id)',
            'hasOne' => 'hasOne - klucz obcy jest w modelu powiązanym, relacja 1:1',
            'hasMany' => 'hasMany - klucz obcy jest w modelu powiązanym, relacja 1:N',
        ], 'belongsTo');
    }

    protected function resolveRelatedModelClass(?string $module, string $relationName): ?string
    {
        $raw = trim((string) $this->option('related'));
        $default = Str::studly(Str::singular($relationName));

        if ($raw === '' && $this->input->isInteractive()) {
            $raw = trim((string) text('Model relacji (klasa)', 'np. Patient', $default));
        }

        if ($raw === '') {
            $raw = $default;
        }

        $normalized = str_replace('/', '\\', trim($raw, '\\'));
        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, '\\')) {
            return $normalized;
        }

        $classShort = Str::studly(class_basename($normalized));

        if ($module !== null) {
            return "App\\Svarium\\Modules\\{$module}\\Models\\{$classShort}";
        }

        return "App\\Models\\{$classShort}";
    }

    protected function buildRelationMethod(
        string $relationName,
        string $relationType,
        string $relatedModelReference,
        string $modelName,
        string $relationReturnTypeHint
    ): string {
        $relatedClassLiteral = $relatedModelReference.'::class';

        if ($relationType === 'belongsTo') {
            $foreignKey = trim((string) ($this->option('foreign-key') ?? ''));
            $ownerKey = trim((string) ($this->option('owner-key') ?? ''));

            if ($foreignKey === '') {
                $foreignKey = Str::snake($relationName).'_id';
            }

            if ($ownerKey === '') {
                $ownerKey = 'id';
            }

            $return = "\$this->belongsTo({$relatedClassLiteral}, '{$foreignKey}', '{$ownerKey}')";
        } else {
            $foreignKey = trim((string) ($this->option('foreign-key') ?? ''));
            $localKey = trim((string) ($this->option('local-key') ?? ''));

            if ($foreignKey === '') {
                $foreignKey = Str::snake($modelName).'_id';
            }

            if ($localKey === '') {
                $localKey = 'id';
            }

            $return = "\$this->{$relationType}({$relatedClassLiteral}, '{$foreignKey}', '{$localKey}')";
        }

        return <<<PHP
    public function {$relationName}(): {$relationReturnTypeHint}
    {
        return {$return};
    }
PHP;
    }

    protected function appendMethodToModel(
        string $modelPath,
        string $relationName,
        string $relationType,
        string $relatedModelClass,
        string $modelName
    ): bool
    {
        $content = (string) File::get($modelPath);

        $relationReturnClass = $this->relationReturnClass($relationType);

        $relatedModelReference = $this->resolveClassReferenceForModelFile($content, $relatedModelClass);
        $relationReturnTypeHint = $this->resolveClassReferenceForModelFile($content, $relationReturnClass);
        $existingBounds = $this->findMethodBounds($content, $relationName);

        $methodCode = $this->buildRelationMethod(
            relationName: $relationName,
            relationType: $relationType,
            relatedModelReference: $relatedModelReference,
            modelName: $modelName,
            relationReturnTypeHint: $relationReturnTypeHint
        );

        if (is_array($existingBounds)) {
            $shouldOverwrite = (bool) $this->option('force');

            if (! $shouldOverwrite && $this->input->isInteractive()) {
                $shouldOverwrite = (bool) confirm(
                    "Metoda relacji [{$relationName}] już istnieje w modelu. Czy chcesz nadpisać?",
                    false,
                    'Tak',
                    'Nie'
                );
            }

            if (! $shouldOverwrite) {
                $this->error("Metoda relacji [{$relationName}] już istnieje w modelu.");
                return false;
            }

            $replacement = "\n".$methodCode."\n";
            $newContent = substr($content, 0, $existingBounds['start']).$replacement.substr($content, $existingBounds['end']);
            File::put($modelPath, $newContent);

            return true;
        }

        $position = strrpos($content, '}');
        if ($position === false) {
            $this->error('Nie udało się znaleźć końca klasy modelu.');
            return false;
        }

        $before = rtrim(substr($content, 0, $position));
        $after = substr($content, $position);

        $newContent = $before."\n\n".$methodCode."\n".$after;

        File::put($modelPath, $newContent);

        return true;
    }

    /**
     * @return array{start: int, end: int}|null
     */
    protected function findMethodBounds(string $content, string $methodName): ?array
    {
        $pattern = '/\b(?:public|protected|private)\s+function\s+'.preg_quote($methodName, '/').'\s*\(/';

        if (preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $start = (int) ($match[0][1] ?? 0);
        $searchOffset = $start + strlen((string) ($match[0][0] ?? ''));
        $braceStart = strpos($content, '{', $searchOffset);

        if ($braceStart === false) {
            return null;
        }

        $braceEnd = $this->findMatchingBraceIndex($content, $braceStart);
        if ($braceEnd === null) {
            return null;
        }

        return [
            'start' => $start,
            'end' => $braceEnd + 1,
        ];
    }

    protected function findMatchingBraceIndex(string $content, int $openIndex): ?int
    {
        $length = strlen($content);
        $depth = 0;
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $escape = false;

        for ($i = $openIndex; $i < $length; $i++) {
            $char = $content[$i];

            if ($escape) {
                $escape = false;
                continue;
            }

            if (($inSingleQuote || $inDoubleQuote) && $char === '\\') {
                $escape = true;
                continue;
            }

            if ($char === "'" && ! $inDoubleQuote) {
                $inSingleQuote = ! $inSingleQuote;
                continue;
            }

            if ($char === '"' && ! $inSingleQuote) {
                $inDoubleQuote = ! $inDoubleQuote;
                continue;
            }

            if ($inSingleQuote || $inDoubleQuote) {
                continue;
            }

            if ($char === '{') {
                $depth++;
                continue;
            }

            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    protected function relationReturnClass(string $relationType): string
    {
        return match ($relationType) {
            'belongsTo' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
            'hasOne' => 'Illuminate\\Database\\Eloquent\\Relations\\HasOne',
            'hasMany' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
            default => 'Illuminate\\Database\\Eloquent\\Relations\\Relation',
        };
    }

    protected function resolveClassReferenceForModelFile(string &$content, string $fqcn): string
    {
        $normalized = ltrim(trim($fqcn), '\\');
        $short = class_basename($normalized);

        if ($short === '') {
            return '\\'.$normalized;
        }

        $namespace = $this->extractFileNamespace($content);

        if ($namespace !== '' && strcasecmp($namespace.'\\'.$short, $normalized) === 0) {
            return $short;
        }

        $alreadyImportedAlias = $this->findImportedAliasForClass($content, $normalized);
        if ($alreadyImportedAlias !== null) {
            return $alreadyImportedAlias;
        }

        $occupiedBy = $this->findImportedClassByShortName($content, $short);

        if ($occupiedBy !== null && strcasecmp($occupiedBy, $normalized) !== 0) {
            return '\\'.$normalized;
        }

        $content = $this->insertUseStatement($content, $normalized);

        return $short;
    }

    protected function extractFileNamespace(string $content): string
    {
        if (preg_match('/^\s*namespace\s+([^;]+);/m', $content, $match) !== 1) {
            return '';
        }

        return trim((string) ($match[1] ?? ''));
    }

    protected function findImportedAliasForClass(string $content, string $class): ?string
    {
        $imports = $this->parseUseStatements($content);

        foreach ($imports as $import) {
            if (strcasecmp($import['class'], $class) !== 0) {
                continue;
            }

            return $import['alias'];
        }

        return null;
    }

    protected function findImportedClassByShortName(string $content, string $short): ?string
    {
        $imports = $this->parseUseStatements($content);

        foreach ($imports as $import) {
            if (strcasecmp($import['alias'], $short) !== 0) {
                continue;
            }

            return $import['class'];
        }

        return null;
    }

    /**
     * @return array<int, array{class: string, alias: string, line: string, start: int, end: int}>
     */
    protected function parseUseStatements(string $content): array
    {
        $results = [];
        $classPosition = $this->detectClassPosition($content);

        if (preg_match_all('/^use\s+([^;]+);/m', $content, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return $results;
        }

        foreach ($matches[1] as $index => $match) {
            $offset = (int) ($match[1] ?? 0);

            if ($classPosition !== null && $offset > $classPosition) {
                continue;
            }

            $raw = trim((string) ($match[0] ?? ''));
            if ($raw === '' || str_starts_with(strtolower($raw), 'function ') || str_starts_with(strtolower($raw), 'const ')) {
                continue;
            }

            $class = $raw;
            $alias = class_basename($class);

            if (preg_match('/^(.+?)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $raw, $aliasMatch) === 1) {
                $class = trim((string) ($aliasMatch[1] ?? ''));
                $alias = trim((string) ($aliasMatch[2] ?? ''));
            }

            if ($class === '' || $alias === '') {
                continue;
            }

            $lineRaw = (string) ($matches[0][$index][0] ?? '');
            $lineOffset = (int) ($matches[0][$index][1] ?? $offset);

            $results[] = [
                'class' => ltrim($class, '\\'),
                'alias' => $alias,
                'line' => $lineRaw,
                'start' => $lineOffset,
                'end' => $lineOffset + strlen($lineRaw),
            ];
        }

        return $results;
    }

    protected function insertUseStatement(string $content, string $fqcn): string
    {
        $normalized = ltrim(trim($fqcn), '\\');

        if ($normalized === '') {
            return $content;
        }

        if (preg_match('/^use\s+'.preg_quote($normalized, '/').'\s*;/mi', $content) === 1) {
            return $content;
        }

        $useLine = "use {$normalized};\n";
        $classPosition = $this->detectClassPosition($content);
        $imports = $this->parseUseStatements($content);

        if ($imports !== []) {
            $last = end($imports);
            if (is_array($last)) {
                $insertAt = (int) ($last['end'] ?? 0);

                return substr($content, 0, $insertAt)."\n".$useLine.substr($content, $insertAt);
            }
        }

        if (preg_match('/^\s*namespace\s+[^;]+;\s*\n/m', $content, $match, PREG_OFFSET_CAPTURE) === 1) {
            $namespaceLine = (string) ($match[0][0] ?? '');
            $namespaceOffset = (int) ($match[0][1] ?? 0);
            $insertAt = $namespaceOffset + strlen($namespaceLine);

            return substr($content, 0, $insertAt).$useLine.substr($content, $insertAt);
        }

        if ($classPosition !== null) {
            return substr($content, 0, $classPosition).$useLine.substr($content, $classPosition);
        }

        return $useLine.$content;
    }

    protected function detectClassPosition(string $content): ?int
    {
        if (preg_match('/^\s*(?:abstract\s+|final\s+)?(?:class|trait|interface|enum)\s+[A-Za-z_][A-Za-z0-9_]*/m', $content, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return (int) ($match[0][1] ?? 0);
    }

    /**
     * @return list<string>
     */
    protected function availableModules(): array
    {
        $base = svarium_modules();

        if (! File::isDirectory($base)) {
            return [];
        }

        $modules = [];
        foreach (File::directories($base) as $directory) {
            $name = trim((string) basename($directory));
            if ($name === '') {
                continue;
            }

            $modules[] = Str::studly($name);
        }

        sort($modules);

        return $modules;
    }

    /**
     * @return list<string>
     */
    protected function availableModels(?string $module): array
    {
        $modelsPath = $module !== null
            ? svarium_modules("{$module}/Models")
            : app_path('Models');

        if (! File::isDirectory($modelsPath)) {
            return [];
        }

        $models = [];
        foreach (File::allFiles($modelsPath) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $name = Str::studly($file->getFilenameWithoutExtension());
            if ($name === '') {
                continue;
            }

            $models[] = $name;
        }

        $models = array_values(array_unique($models));
        sort($models);

        return $models;
    }
}
