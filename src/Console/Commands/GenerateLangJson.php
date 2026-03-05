<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateLangJson extends Command
{

    protected $signature = 'svarium:lang.prepare {lang?}';

    protected $description = 'Konwertuje pliki tłumaczeń PHP (messages.php) na pliki JSON (pl.json)';

    public function handle()
    {
        $langPath = __DIR__.'/../../lang';
        $modulesPath = app_path('Svarium/Modules');
        $requestedLocale = $this->normalizeLocale((string) ($this->argument('lang') ?? ''));

        $directories = File::isDirectory($langPath)
            ? File::directories($langPath)
            : [];

        $locales = [];
        foreach ($directories as $dir) {
            $locale = $this->normalizeLocale(basename($dir));
            if ($locale !== null) {
                $locales[$locale] = true;
            }
        }

        if (File::isDirectory($modulesPath)) {
            foreach ((array) glob($modulesPath.'/*/Lang/*', GLOB_ONLYDIR) as $localeDir) {
                if (! is_string($localeDir)) {
                    continue;
                }

                $locale = $this->normalizeLocale(basename($localeDir));
                if ($locale !== null) {
                    $locales[$locale] = true;
                }
            }
        }

        if ($requestedLocale !== null) {
            $locales = [$requestedLocale => true];
        }

        if ($locales === []) {
            $this->warn("Nie znaleziono folderów językowych w: $langPath ani tłumaczeń modułów.");
            return;
        }

        foreach (array_keys($locales) as $locale) {
            $this->info("Przetwarzanie języka: $locale");

            $translations = [];

            $packageLocaleDir = $langPath.'/'.$locale;
            if (File::isDirectory($packageLocaleDir)) {
                $files = File::files($packageLocaleDir);

                foreach ($files as $file) {
                    if ($file->getExtension() !== 'php') {
                        continue;
                    }

                    $content = include $file->getPathname();

                    if (! is_array($content)) {
                        continue;
                    }

                    $translations = array_replace_recursive($translations, $content);
                }
            }

            $translations = array_replace_recursive(
                $translations,
                $this->buildModuleTranslations($modulesPath, $locale)
            );

            $jsonFile = $langPath . "/$locale.json";
            $existingJson = [];

            if (File::exists($jsonFile)) {
                $existingJson = json_decode(File::get($jsonFile), true) ?? [];
            }

            $finalTranslations = array_replace_recursive($existingJson, $translations);

            ksort($finalTranslations);

            File::put(
                $jsonFile,
                json_encode($finalTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            $this->info("Zapisano: $locale.json (" . count($finalTranslations) . " kluczy)");
        }

        $this->newLine();
        $this->info('Gotowe.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildModuleTranslations(string $modulesPath, string $locale): array
    {
        if (! File::isDirectory($modulesPath)) {
            return [];
        }

        $translations = [];
        $pattern = $modulesPath.'/*/Lang/'.$locale.'/*.php';
        $files = (array) glob($pattern);

        foreach ($files as $filePath) {
            if (! is_string($filePath) || ! File::exists($filePath)) {
                continue;
            }

            $content = include $filePath;
            if (! is_array($content)) {
                continue;
            }

            $translations = array_replace_recursive($translations, $content);
        }

        return $translations;
    }

    protected function normalizeLocale(string $value): ?string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9_-]/', '', $normalized) ?? '';
        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : null;
    }
}
