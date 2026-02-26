<?php

namespace Upsoftware\Svarium\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModuleCommand extends Command
{
    protected $signature = 'svarium:make:module {name}';
    protected $description = 'Create new Svarium module';

    public function handle(): void
    {
        $name = Str::studly($this->argument('name'));
        $base = svarium_path("Modules/{$name}");

        if (File::exists($base)) {
            $this->error("Module {$name} already exists.");
            return;
        }

        $this->createStructure($base);
        $this->createModuleClass($name, $base);
        $this->createResourceClass($name, $base);
        $this->createTableClass($name, $base);
        $this->createFormClass($name, $base);

        $this->info("Svarium module {$name} created.");
    }

    protected function createStructure(string $base): void
    {
        $dirs = [
            '',
            'Panel',
            'Web',
            'Api',
            'Forms',
            'Tables',
            'Models',
            'Policies',
        ];

        foreach ($dirs as $dir) {
            File::makeDirectory($base.'/'.$dir, 0755, true, true);
        }
    }

    protected function createModuleClass(string $name, string $base): void
    {
        File::put($base."/{$name}Module.php", $this->renderStub('svarium.module.php.stub', $name));
    }

    protected function createResourceClass(string $name, string $base): void
    {
        File::put($base."/Panel/{$name}Resource.php", $this->renderStub('svarium.module.resource.php.stub', $name));
    }

    protected function createTableClass(string $name, string $base): void
    {
        File::put($base."/Tables/{$name}Table.php", $this->renderStub('svarium.module.table.php.stub', $name));
    }

    protected function createFormClass(string $name, string $base): void
    {
        File::put($base."/Forms/{$name}Form.php", $this->renderStub('svarium.module.form.php.stub', $name));
    }

    protected function renderStub(string $stubFile, string $name): string
    {
        $path = $this->stubPath($stubFile);

        if (! File::exists($path)) {
            throw new \RuntimeException("Stub file [{$stubFile}] does not exist.");
        }

        $content = File::get($path);

        return strtr($content, [
            '{{ModuleName}}' => $name,
            '{{ModuleNamePlural}}' => Str::plural($name),
            '{{ModuleNameLower}}' => Str::of($name)->snake()->toString(),
            '{{ModuleNamePluralLower}}' => Str::of(Str::plural($name))->snake()->toString(),
        ]);
    }

    protected function stubPath(string $stubFile): string
    {

        return __DIR__.'/../../../stubs/'.$stubFile;
    }
}
