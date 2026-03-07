<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class MergeLangCommand extends CoreCommand
{
    protected $signature = 'svarium:lang.merge {lang?}';

    protected $description = 'Merge Svarium package JSON files with application JSON files.';
    protected $descriptionKey = 'lang.merge';

    public function handle()
    {
        $appLangPath = lang_path();
        $packageLangPath = __DIR__ . '/../../lang';
        $requestedLocale = $this->normalizeLocale((string) ($this->argument('lang') ?? ''));

        Artisan::call('svarium:lang.prepare', array_filter([
            'lang' => $requestedLocale,
        ], static fn ($value) => $value !== null && $value !== ''));

        if (!File::isDirectory($packageLangPath)) {
            $this->error("Nie znaleziono folderu lang w paczce: $packageLangPath");
            return;
        }

        $packageFiles = File::files($packageLangPath);

        foreach ($packageFiles as $packageFile) {
            if ($packageFile->getExtension() !== 'json') {
                continue;
            }

            $filename = $packageFile->getFilename();
            $locale = $this->normalizeLocale(pathinfo($filename, PATHINFO_FILENAME));
            if ($requestedLocale !== null && $locale !== $requestedLocale) {
                continue;
            }

            $appFilePath = $appLangPath . '/' . $filename;

            $packageContent = json_decode(File::get($packageFile->getPathname()), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("Błąd JSON w paczce ($filename): " . json_last_error_msg());
                continue;
            }

            $appContent = [];
            if (!File::exists($appFilePath)) {
                $this->warn("  - Pominięto ($filename) — język nie istnieje w aplikacji.");
                continue;
            }

            $appContent = json_decode(File::get($appFilePath), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("Błąd JSON w aplikacji ($filename): " . json_last_error_msg());
                continue;
            }

            $mergedContent = array_replace($appContent ?? [], $packageContent ?? []);

            ksort($mergedContent);

            File::put(
                $appFilePath,
                json_encode($mergedContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        }

        $this->info('Gotowe łączenie tłumaczeń');
    }

    protected function normalizeLocale(string $value): ?string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9_-]/', '', $normalized) ?? '';
        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : null;
    }
}
