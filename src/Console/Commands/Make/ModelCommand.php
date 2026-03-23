<?php

namespace Upsoftware\Svarium\Console\Commands\Make;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Upsoftware\Svarium\Console\Commands\CoreCommand;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class ModelCommand extends CoreCommand
{
    protected $signature = 'svarium:make.model
        {name? : Model name}
        {--module= : Module name (e.g. Patient)}
        {--migration : Create migration}
        {--operations : Create CRUD operation stubs in module (List/Create/Edit/Delete)}
        {--api : Enable API in module Resource (sets protected static bool $api = true)}';

    protected $description = 'Create a model (optionally in a Svarium module) with optional migration, CRUD operation stubs and API enable switch';
    protected $descriptionKey = 'make.model';

    public function handle(): int
    {
        $modelName = $this->resolveModelName();
        if ($modelName === '') {
            $this->error('Nazwa modelu jest wymagana.');
            return self::FAILURE;
        }

        $moduleName = $this->resolveModuleName();
        if ($moduleName === false) {
            return self::FAILURE;
        }

        $createMigration = $this->resolveCreateMigration();
        $createOperations = $moduleName !== null
            ? $this->resolveCreateOperations()
            : false;
        $enableApi = $moduleName !== null
            ? $this->resolveEnableApi()
            : false;

        $modelTarget = $moduleName !== null
            ? "App/Svarium/Modules/{$moduleName}/Models/{$modelName}"
            : $modelName;

        $modelParams = [
            'name' => $modelTarget,
        ];

        if ($moduleName === null && $createMigration) {
            $modelParams['--migration'] = true;
        }

        $modelExitCode = $this->call('make:model', $modelParams);
        if ($modelExitCode !== self::SUCCESS) {
            return $modelExitCode;
        }

        if ($moduleName !== null && $createMigration) {
            $migrationExitCode = $this->createModuleMigration($moduleName, $modelName);
            if ($migrationExitCode !== self::SUCCESS) {
                return $migrationExitCode;
            }
        }

        if ($moduleName !== null && $createOperations) {
            $operationsExitCode = $this->createModuleOperations($moduleName, $modelName);
            if ($operationsExitCode !== self::SUCCESS) {
                return $operationsExitCode;
            }
        }

        if ($moduleName !== null && $enableApi) {
            $this->enableApiInModuleResource($moduleName);
        }

        $targetInfo = $moduleName !== null
            ? "app/Svarium/Modules/{$moduleName}/Models/{$modelName}.php"
            : "app/Models/{$modelName}.php";
        $targetAbsolute = base_path($targetInfo);

        $this->info("Model utworzony: {$targetInfo}");
        $this->line("<href=file://{$targetAbsolute}>{$targetAbsolute}</>");

        return self::SUCCESS;
    }

    protected function resolveModelName(): string
    {
        $raw = trim((string) $this->argument('name'));

        if ($raw === '' && $this->input->isInteractive()) {
            while ($raw === '') {
                $raw = trim((string) text('Podaj nazwę modelu', 'np. Patient', ''));

                if ($raw === '') {
                    $this->error('Nazwa modelu jest wymagana.');
                }
            }
        }

        if ($raw === '') {
            return '';
        }

        return Str::studly(class_basename(str_replace('\\', '/', $raw)));
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

        $options = ['__global__' => 'Brak modułu (app/Models)'];

        foreach ($availableModules as $module) {
            $options[$module] = $module;
        }

        $selected = (string) select(
            'Do jakiego modułu dodać model?',
            $options,
            '__global__'
        );

        if ($selected === '__global__') {
            return null;
        }

        return $selected;
    }

    protected function resolveCreateMigration(): bool
    {
        if ((bool) $this->option('migration')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        return (bool) confirm('Czy utworzyć migrację?', false, 'Tak', 'Nie');
    }

    protected function resolveCreateOperations(): bool
    {
        if ((bool) $this->option('operations')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        return (bool) confirm('Czy utworzyć operacje CRUD (List/Create/Edit/Delete) w module?', false, 'Tak', 'Nie');
    }

    protected function resolveEnableApi(): bool
    {
        if ((bool) $this->option('api')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        return (bool) confirm('Czy włączyć API w module (Resource::$api = true)?', false, 'Tak', 'Nie');
    }

    protected function createModuleMigration(string $moduleName, string $modelName): int
    {
        $table = Str::snake(Str::pluralStudly($modelName));
        $migrationName = "create_{$table}_table";
        $path = svarium_modules("{$moduleName}/Database/Migrations");

        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $this->info("Tworzę migrację modułu: {$moduleName}");

        return $this->call('make:migration', [
            'name' => $migrationName,
            '--create' => $table,
            '--path' => $path,
            '--realpath' => true,
        ]);
    }

    protected function createModuleOperations(string $moduleName, string $modelName): int
    {
        $directory = svarium_modules("{$moduleName}/Panel/Operations");
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true, true);
        }

        $namespace = "App\\Svarium\\Modules\\{$moduleName}\\Panel\\Operations";
        $classBase = Str::studly($modelName);

        $templates = [
            "{$classBase}ListOperation.php" => $this->operationStub(
                $namespace,
                "{$classBase}ListOperation",
                'ResourceListOperation'
            ),
            "{$classBase}CreateOperation.php" => $this->operationStub(
                $namespace,
                "{$classBase}CreateOperation",
                'ResourceCreateOperation'
            ),
            "{$classBase}EditOperation.php" => $this->operationStub(
                $namespace,
                "{$classBase}EditOperation",
                'ResourceEditOperation'
            ),
            "{$classBase}DeleteOperation.php" => $this->operationStub(
                $namespace,
                "{$classBase}DeleteOperation",
                'ResourceDeleteOperation'
            ),
        ];

        foreach ($templates as $fileName => $content) {
            $target = $directory.DIRECTORY_SEPARATOR.$fileName;

            if (File::exists($target)) {
                $this->warn("Pominięto istniejący plik operation: {$target}");
                continue;
            }

            File::put($target, $content);
            $this->info("Utworzono operation: {$target}");
            $this->line("<href=file://{$target}>{$target}</>");
        }

        return self::SUCCESS;
    }

    protected function enableApiInModuleResource(string $moduleName): void
    {
        $resourcePath = svarium_modules("{$moduleName}/Panel/{$moduleName}Resource.php");

        if (! File::exists($resourcePath)) {
            $this->warn("Nie znaleziono Resource do włączenia API: {$resourcePath}");
            return;
        }

        $content = File::get($resourcePath);
        if (! is_string($content) || $content === '') {
            $this->warn("Nie udało się odczytać Resource: {$resourcePath}");
            return;
        }

        $updated = $content;

        if (preg_match('/protected\s+static\s+bool\s+\$api\s*=\s*(true|false)\s*;/i', $updated) === 1) {
            $updated = (string) preg_replace(
                '/protected\s+static\s+bool\s+\$api\s*=\s*(true|false)\s*;/i',
                'protected static bool $api = true;',
                $updated,
                1
            );
        } elseif (preg_match('/public\s+static\s+function\s+api\s*\(/i', $updated) === 1) {
            if (preg_match('/class\s+[A-Za-z0-9_]+\s+extends\s+Resource\s*\{/', $updated) === 1) {
                $updated = (string) preg_replace(
                    '/(class\s+[A-Za-z0-9_]+\s+extends\s+Resource\s*\{)/',
                    "$1\n    protected static bool \$api = true;\n",
                    $updated,
                    1
                );
            }
        } elseif (preg_match('/protected\s+static\s+string\s+\$model\s*=\s*[^;]+;/', $updated) === 1) {
            $updated = (string) preg_replace(
                '/(protected\s+static\s+string\s+\$model\s*=\s*[^;]+;)/',
                "$1\n\n    protected static bool \$api = true;",
                $updated,
                1
            );
        } elseif (preg_match('/class\s+[A-Za-z0-9_]+\s+extends\s+Resource\s*\{/', $updated) === 1) {
            $updated = (string) preg_replace(
                '/(class\s+[A-Za-z0-9_]+\s+extends\s+Resource\s*\{)/',
                "$1\n    protected static bool \$api = true;\n",
                $updated,
                1
            );
        }

        if ($updated === $content) {
            $this->warn("Nie wprowadzono zmian API w Resource: {$resourcePath}");
            return;
        }

        File::put($resourcePath, $updated);
        $this->info("Włączono API w Resource: {$resourcePath}");
        $this->line("<href=file://{$resourcePath}>{$resourcePath}</>");
    }

    protected function operationStub(
        string $namespace,
        string $className,
        string $baseOperationClass
    ): string {
        return <<<PHP
<?php

namespace {$namespace};

use Upsoftware\\Svarium\\Panel\\Resource\\Operations\\{$baseOperationClass};

class {$className} extends {$baseOperationClass}
{
}

PHP;
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

        $directories = File::directories($base);

        $modules = [];
        foreach ($directories as $directory) {
            $name = trim((string) basename($directory));
            if ($name === '') {
                continue;
            }

            $modules[] = Str::studly($name);
        }

        sort($modules);

        return $modules;
    }
}
