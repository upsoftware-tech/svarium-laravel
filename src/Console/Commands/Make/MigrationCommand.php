<?php

namespace Upsoftware\Svarium\Console\Commands\Make;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Upsoftware\Svarium\Console\Commands\CoreCommand;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class MigrationCommand extends CoreCommand
{
    protected $signature = 'svarium:make:migration
        {name? : Migration name}
        {--module= : Module name (e.g. Patient)}
        {--create= : The table to be created}
        {--table= : The table to migrate}
        {--fullpath : Output the full path of the migration}';

    protected $description = 'Create a migration globally or in a selected Svarium module';
    protected $descriptionKey = 'make.migration';

    public function handle(): int
    {
        $name = $this->resolveMigrationName();

        if ($name === '') {
            $this->error('Nazwa migracji jest wymagana.');
            return self::FAILURE;
        }

        $module = $this->resolveModuleName();
        if ($module === false) {
            return self::FAILURE;
        }

        $params = [
            'name' => $name,
        ];

        $targetPath = database_path('migrations');

        if ($module !== null) {
            $targetPath = svarium_modules("{$module}/Database/Migrations");
            $this->ensureDirectory($targetPath);
            $params['--path'] = $targetPath;
            $params['--realpath'] = true;
        }

        $create = trim((string) ($this->option('create') ?? ''));
        $table = trim((string) ($this->option('table') ?? ''));

        if ($create !== '') {
            $params['--create'] = $create;
        }

        if ($table !== '') {
            $params['--table'] = $table;
        }

        if ((bool) $this->option('fullpath')) {
            $params['--fullpath'] = true;
        }

        $exitCode = $this->call('make:migration', $params);
        if ($exitCode !== self::SUCCESS) {
            return $exitCode;
        }

        if ($module !== null) {
            $this->info("Migracja utworzona w module: {$module}");
            $this->line("Ścieżka: {$targetPath}");
        } else {
            $this->info('Migracja utworzona globalnie (database/migrations).');
        }

        return self::SUCCESS;
    }

    protected function resolveMigrationName(): string
    {
        $name = trim((string) $this->argument('name'));

        if ($name !== '') {
            return $name;
        }

        if (! $this->input->isInteractive()) {
            return '';
        }

        while ($name === '') {
            $name = trim((string) text('Podaj nazwę migracji', 'np. create_patients_table', ''));

            if ($name === '') {
                $this->error('Nazwa migracji jest wymagana.');
            }
        }

        return $name;
    }

    /**
     * @return string|false|null
     */
    protected function resolveModuleName(): string|false|null
    {
        $modules = $this->availableModules();
        $moduleOption = trim((string) $this->option('module'));

        if ($moduleOption !== '') {
            $normalized = Str::studly($moduleOption);

            if (! in_array($normalized, $modules, true)) {
                $this->error("Moduł [{$moduleOption}] nie istnieje.");
                return false;
            }

            return $normalized;
        }

        if (! $this->input->isInteractive()) {
            return null;
        }

        if ($modules === []) {
            return null;
        }

        $useModule = (bool) confirm('Czy utworzyć migrację w module?', false, 'Tak', 'Nie');
        if (! $useModule) {
            return null;
        }

        $options = [];
        foreach ($modules as $module) {
            $options[$module] = $module;
        }

        return (string) select('Wybierz moduł', $options, array_key_first($options));
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

    protected function ensureDirectory(string $path): void
    {
        if (File::isDirectory($path)) {
            return;
        }

        File::makeDirectory($path, 0755, true, true);
    }
}

