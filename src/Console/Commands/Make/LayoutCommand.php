<?php

namespace Upsoftware\Svarium\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class LayoutCommand extends Command
{
    protected $signature = 'svarium:make:layout {name}';

    protected $description = 'Create new Svarium layout';

    public function handle(): int
    {
        $layoutName = Str::studly((string) $this->argument('name'));

        if (! Str::endsWith($layoutName, 'Layout')) {
            $layoutName .= 'Layout';
        }

        $layoutDirectory = svarium_path('Layouts');
        $layoutFile = $layoutDirectory.DIRECTORY_SEPARATOR.$layoutName.'.php';

        if (File::exists($layoutFile)) {
            $this->error("Layout {$layoutName} already exists.");
            return self::FAILURE;
        }

        if (! File::isDirectory($layoutDirectory)) {
            File::makeDirectory($layoutDirectory, 0755, true, true);
        }

        File::put($layoutFile, $this->renderStub('svarium.layout.php.stub', $layoutName));

        $this->info("Svarium layout {$layoutName} created.");
        $this->line("Path: {$layoutFile}");
        $this->line("Use in panel: ->layout(\\App\\Svarium\\Layouts\\{$layoutName}::class)");

        return self::SUCCESS;
    }

    protected function renderStub(string $stubFile, string $layoutName): string
    {
        $path = $this->stubPath($stubFile);

        if (! File::exists($path)) {
            throw new RuntimeException("Stub file [{$stubFile}] does not exist.");
        }

        $content = File::get($path);

        return strtr($content, [
            '{{LayoutName}}' => $layoutName,
        ]);
    }

    protected function stubPath(string $stubFile): string
    {
        return __DIR__.'/../../../stubs/'.$stubFile;
    }
}

