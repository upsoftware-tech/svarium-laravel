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
        {--migration : Create migration}';

    protected $description = 'Create a model (optionally in a Svarium module) with optional migration';
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

        $modelTarget = $moduleName !== null
            ? "Svarium/Modules/{$moduleName}/Models/{$modelName}"
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

        $targetInfo = $moduleName !== null
            ? "app/Svarium/Modules/{$moduleName}/Models/{$modelName}.php"
            : "app/Models/{$modelName}.php";

        $this->info("Model utworzony: {$targetInfo}");

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

