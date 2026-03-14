<?php

namespace Upsoftware\Svarium\Console\Commands;

use Throwable;
use Upsoftware\Svarium\Services\Api\OpenApiGenerator;

class ApiDocsCommand extends CoreCommand
{
    protected $signature = 'svarium:api.docs
        {--path= : Path to output OpenAPI JSON (absolute or relative to base_path)}
        {--no-pretty : Disable pretty JSON formatting}';

    protected $description = 'Generuje OpenAPI JSON na podstawie tras API i udostępnia pod ReDoc';

    public function handle(): int
    {
        try {
            $generator = app(OpenApiGenerator::class);
            $outputPath = trim((string) $this->option('path'));
            $resolvedPath = $outputPath !== '' ? $this->resolvePath($outputPath) : null;
            $pretty = ! (bool) $this->option('no-pretty');

            $saved = $generator->generateAndStore($resolvedPath, $pretty);

            $docsPath = '/'.trim((string) config('upsoftware.api.docs.path', 'api/docs'), '/');
            $specPath = '/'.trim((string) config('upsoftware.api.docs.spec_path', 'api/openapi.json'), '/');

            $docsUrl = url($docsPath);
            $specUrl = url($specPath);

            $this->info('Wygenerowano dokumentację OpenAPI.');
            $this->line('Plik: '.$saved);
            $this->line("<href=file://{$saved}>{$saved}</>");
            $this->line('ReDoc: '.$docsUrl);
            $this->line("<href={$docsUrl}>{$docsUrl}</>");
            $this->line('Spec URL: '.$specUrl);
            $this->line("<href={$specUrl}>{$specUrl}</>");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Nie udało się wygenerować dokumentacji API: '.trim((string) $e->getMessage()));

            return self::FAILURE;
        }
    }

    protected function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}

