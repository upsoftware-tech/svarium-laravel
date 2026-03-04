<?php

namespace Upsoftware\Svarium\Console\Commands;

use Upsoftware\Svarium\Console\Commands\Concerns\InteractsWithTenantTenancy;

class MakeTenantMigrationCommand extends CoreCommand
{
    use InteractsWithTenantTenancy;

    protected $signature = 'svarium:tenant.migration
        {name : Migration name}
        {--create= : The table to be created}
        {--table= : The table to migrate}
        {--path= : Override tenant migrations path}
        {--fullpath : Output the full path of the migration}';

    protected $description = 'Create tenant migration in configured tenant migrations directory';
    protected $aliases = ['svarium:make.migrate'];

    public function handle(): int
    {
        $name = trim((string) $this->argument('name'));

        if ($name === '') {
            $this->error('Migration name cannot be empty.');
            return self::FAILURE;
        }

        $pathOption = $this->option('path');
        $paths = $this->userTenantMigrationsPaths(
            is_string($pathOption) && trim($pathOption) !== ''
                ? [trim($pathOption)]
                : []
        );

        $path = $paths[0] ?? app_path('Svarium/Tenancy/Migrations');
        $this->ensureDirectory($path);

        $params = [
            'name' => $name,
            '--path' => $path,
            '--realpath' => true,
        ];

        $create = $this->option('create');
        if (is_string($create) && trim($create) !== '') {
            $params['--create'] = trim($create);
        }

        $table = $this->option('table');
        if (is_string($table) && trim($table) !== '') {
            $params['--table'] = trim($table);
        }

        if ((bool) $this->option('fullpath')) {
            $params['--fullpath'] = true;
        }

        $exitCode = $this->call('make:migration', $params);

        if ($exitCode !== self::SUCCESS) {
            return $exitCode;
        }

        $this->info("Tenant migration created in: {$path}");

        return self::SUCCESS;
    }
}
