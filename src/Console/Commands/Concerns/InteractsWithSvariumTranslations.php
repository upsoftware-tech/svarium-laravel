<?php

namespace Upsoftware\Svarium\Console\Commands\Concerns;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

trait InteractsWithSvariumTranslations
{
    /**
     * @return array<string, string>
     */
    protected function availableTranslationModules(): array
    {
        $root = base_path('app/Svarium/Modules');
        if (! is_dir($root)) {
            return [];
        }

        $modules = [];
        foreach (File::directories($root) as $directory) {
            $name = basename($directory);
            $file = $directory.DIRECTORY_SEPARATOR.$name.'Module.php';

            if (! is_file($file)) {
                continue;
            }

            $modules[$name] = $directory;
        }

        ksort($modules);

        return $modules;
    }

    /**
     * @return array<string, array{id: string, type: string, label: string, file: string, moduleName: string|null}>
     */
    protected function translationLocations(string $locale): array
    {
        $locations = [
            'global' => [
                'id' => 'global',
                'type' => 'global',
                'label' => 'Globalny plik',
                'file' => base_path("app/Svarium/Lang/{$locale}/messages.php"),
                'moduleName' => null,
            ],
        ];

        foreach ($this->availableTranslationModules() as $name => $directory) {
            $locations['module:'.$name] = [
                'id' => 'module:'.$name,
                'type' => 'module',
                'label' => 'Moduł: '.$name,
                'file' => $directory."/Lang/{$locale}/messages.php",
                'moduleName' => $name,
            ];
        }

        return $locations;
    }

    /**
     * @param array<string, array{id: string, type: string, label: string, file: string, moduleName: string|null}> $locations
     * @return array{id: string, type: string, label: string, file: string, moduleName: string|null}|null
     */
    protected function matchTranslationLocation(string $input, array $locations, bool $moduleOnly = false): ?array
    {
        $value = trim($input);
        if ($value === '') {
            return null;
        }

        if (! $moduleOnly && strcasecmp($value, 'global') === 0) {
            return $locations['global'] ?? null;
        }

        if (! $moduleOnly && isset($locations[$value])) {
            return $locations[$value];
        }

        if (str_starts_with(strtolower($value), 'module:')) {
            $value = trim(substr($value, 7));
        }

        foreach ($locations as $location) {
            if ($location['type'] !== 'module') {
                continue;
            }

            if (strcasecmp((string) $location['moduleName'], $value) === 0) {
                return $location;
            }
        }

        return null;
    }

    /**
     * @param array<string, array{id: string, type: string, label: string, file: string, moduleName: string|null}> $locations
     * @return array<string, string>
     */
    protected function translationLocationOptions(array $locations): array
    {
        $options = [];
        foreach ($locations as $id => $location) {
            $options[$id] = $location['label'];
        }

        return $options;
    }

    protected function normalizeLocaleCode(string $value): ?string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9_-]/', '', $normalized) ?? '';
        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array<string, string>
     */
    protected function availableLocaleOptions(): array
    {
        $options = [];

        if (function_exists('locales')) {
            try {
                $configured = locales();
                if (is_array($configured)) {
                    foreach ($configured as $locale) {
                        if (! is_array($locale)) {
                            continue;
                        }

                        $code = $this->normalizeLocaleCode((string) ($locale['value'] ?? ''));
                        if ($code === null || isset($options[$code])) {
                            continue;
                        }

                        $label = trim((string) ($locale['label'] ?? ''));
                        $options[$code] = $label !== '' ? $label : strtoupper($code);
                    }
                }
            } catch (Throwable) {
                // Ignore and fallback to detected locales.
            }
        }

        $packageLangPath = __DIR__.'/../../../lang';
        if (File::isDirectory($packageLangPath)) {
            foreach (File::directories($packageLangPath) as $directory) {
                $code = $this->normalizeLocaleCode((string) basename($directory));
                if ($code === null || isset($options[$code])) {
                    continue;
                }

                $options[$code] = strtoupper($code);
            }
        }

        $globalLangPath = base_path('app/Svarium/Lang');
        if (File::isDirectory($globalLangPath)) {
            foreach (File::directories($globalLangPath) as $directory) {
                $code = $this->normalizeLocaleCode((string) basename($directory));
                if ($code === null || isset($options[$code])) {
                    continue;
                }

                $options[$code] = strtoupper($code);
            }
        }

        $modulesRoot = base_path('app/Svarium/Modules');
        if (File::isDirectory($modulesRoot)) {
            foreach ((array) glob($modulesRoot.'/*/Lang/*', GLOB_ONLYDIR) as $localeDir) {
                if (! is_string($localeDir)) {
                    continue;
                }

                $code = $this->normalizeLocaleCode((string) basename($localeDir));
                if ($code === null || isset($options[$code])) {
                    continue;
                }

                $options[$code] = strtoupper($code);
            }
        }

        $fallback = $this->normalizeLocaleCode((string) config('app.locale', 'pl')) ?? 'pl';
        if (! isset($options[$fallback])) {
            $options[$fallback] = strtoupper($fallback);
        }

        ksort($options);

        return $options;
    }

    protected function ensureTranslationFile(string $file): void
    {
        if (File::exists($file)) {
            return;
        }

        File::ensureDirectoryExists(dirname($file));
        File::put($file, $this->defaultTranslationStub());
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadTranslationFile(string $file): array
    {
        if (! File::exists($file)) {
            return [];
        }

        $loaded = include $file;
        if (! is_array($loaded)) {
            throw new RuntimeException("Plik tłumaczeń nie zwraca tablicy: {$file}");
        }

        $translations = [];
        foreach ($loaded as $key => $value) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            $translations[$normalizedKey] = $value;
        }

        return $translations;
    }

    /**
     * @param array<string, mixed> $translations
     */
    protected function saveTranslationFile(string $file, array $translations): void
    {
        $normalized = [];
        foreach ($translations as $key => $value) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            $normalized[$normalizedKey] = $value;
        }

        uksort($normalized, static fn (string $left, string $right): int => strcasecmp($left, $right));

        $lines = [];
        foreach ($normalized as $key => $value) {
            $lines[] = '    '.var_export($key, true).' => '.var_export($value, true).',';
        }

        $body = $lines === [] ? '' : "\n".implode("\n", $lines)."\n";
        $content = "<?php\n\nreturn [{$body}];\n";

        File::ensureDirectoryExists(dirname($file));
        File::put($file, $content);
    }

    protected function defaultTranslationStub(): string
    {
        return <<<'PHP'
<?php

return [];
PHP;
    }

    protected function syncPreparedTranslations(string $locale): void
    {
        try {
            Artisan::call('svarium:lang.prepare', ['lang' => $locale]);
            Artisan::call('svarium:lang.merge', ['lang' => $locale]);
        } catch (Throwable $exception) {
            $this->warn('Nie udało się automatycznie zaktualizować tłumaczeń (svarium:lang.prepare / svarium:lang.merge).');
            $this->warn($exception->getMessage());
        }
    }
}
