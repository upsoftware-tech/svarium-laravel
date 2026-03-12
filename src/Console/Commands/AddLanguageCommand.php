<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use LaravelLang\Locales\Facades\Locales;
use Upsoftware\Svarium\Console\Commands\Concerns\InteractsWithSvariumTranslations;
use function Laravel\Prompts\select;

class AddLanguageCommand extends CoreCommand
{
    use InteractsWithSvariumTranslations;

    protected $signature = 'svarium:lang.add {lang?*} {--from= : Kod języka źródłowego do kopiowania tłumaczeń, np. pl}';

    protected $description = 'Add a new language';
    protected $descriptionKey = 'lang.add';

    public function handle()
    {
        $settingModel = config('svarium.models.setting', \Upsoftware\Svarium\Models\Setting::class);
        try {
            $settingsTableExists = Schema::hasTable((new $settingModel)->getTable());
        } catch (\Throwable) {
            $settingsTableExists = false;
        }

        if (! $settingsTableExists) {
            $this->error('Tabela settings nie istnieje. Najpierw uruchom migracje: php artisan migrate');

            return 1;
        }

        $languages = (array) $this->argument('lang');
        $sourceOption = $this->normalizeLocaleCode((string) ($this->option('from') ?? ''));

        $locales = [];
        foreach (Locales::available() as $locale) {
            $locales[$locale->code] = "{$locale->localized} ({$locale->native})";
        }
        asort($locales);

        if (empty($languages)) {
            $selected = select('Wybierz język:', $locales);
            if (!empty($selected)) {
                $languages[] = $selected;
            }
        }

        $languages = array_values(array_unique(array_filter(array_map('trim', $languages))));
        if (empty($languages)) {
            $this->warn('Nie podano kodu języka.');
            return 1;
        }

        $localesCollection = collect(Locales::available());
        $availableSourceLocales = $this->availableLocaleOptions();

        foreach ($languages as $rawLang) {
            $lang = $this->normalizeLocaleCode($rawLang);
            if ($lang === null) {
                $this->error("Nieprawidłowy kod języka: {$rawLang}");
                continue;
            }

            $locale_data = json_decode(json_encode($localesCollection->firstWhere('code', $lang)), true);

            if (!$locale_data) {
                $this->error("Język nie został znaleziony: $lang");
                continue;
            }

            if (!empty($locale_data['regional']) && strlen($locale_data['regional']) === 5) {
                $locale_data['flag'] = strtolower(explode('_', $locale_data['regional'])[1]);
            }

            $sourceLocale = $this->resolveSourceLocale($lang, $sourceOption, $availableSourceLocales);

            $this->info("Dodawanie języka: $lang ...");
            passthru('php artisan lang:add '.escapeshellarg($lang));
            $this->newLine();

            $syncedFiles = $this->syncSvariumTranslationTrees($lang, $sourceLocale);
            $modeLabel = $sourceLocale !== null ? "kopiowanie z [{$sourceLocale}]" : 'klucz = wartość';
            $this->line("Zaktualizowano pliki tłumaczeń Svarium: {$syncedFiles} ({$modeLabel})");

            passthru('php artisan svarium:lang.prepare '.escapeshellarg($lang));
            passthru('php artisan svarium:lang.merge '.escapeshellarg($lang));

            $settingModel::setSettingGlobal('locales', [$lang => $locale_data]);

            $storedLocales = (array) $settingModel::getSettingGlobal('locales', []);
            if (! array_key_exists($lang, $storedLocales)) {
                $this->error("Nie udało się dopisać języka [{$lang}] do settings.locales.");

                return 1;
            }

            $this->line('Aktualne locale: '.implode(', ', array_map('strtoupper', array_keys($storedLocales))));
        }

        return 0;
    }

    protected function resolveSourceLocale(string $targetLocale, ?string $sourceOption, array $availableSourceLocales): ?string
    {
        if ($sourceOption !== null && $sourceOption !== '') {
            if ($sourceOption === $targetLocale) {
                return null;
            }

            return $sourceOption;
        }

        if (! $this->input->isInteractive()) {
            return null;
        }

        $options = ['__none__' => 'Nie kopiuj (klucz = wartość)'];
        foreach ($availableSourceLocales as $code => $label) {
            if ($code === $targetLocale) {
                continue;
            }

            $options[$code] = strtoupper($code).' - '.$label;
        }

        if (count($options) === 1) {
            return null;
        }

        $selected = (string) select(
            label: "Z jakiego języka skopiować tłumaczenia dla [{$targetLocale}]?",
            options: $options
        );

        if ($selected === '__none__' || trim($selected) === '') {
            return null;
        }

        return $this->normalizeLocaleCode($selected);
    }

    protected function syncSvariumTranslationTrees(string $targetLocale, ?string $sourceLocale): int
    {
        $updated = 0;
        $updated += $this->syncTranslationTree(base_path('app/Svarium/Lang'), $targetLocale, $sourceLocale);

        $modulesRoot = base_path('app/Svarium/Modules');
        if (File::isDirectory($modulesRoot)) {
            foreach (File::directories($modulesRoot) as $moduleDirectory) {
                $updated += $this->syncTranslationTree($moduleDirectory.'/Lang', $targetLocale, $sourceLocale);
            }
        }

        return $updated;
    }

    protected function syncTranslationTree(string $langRoot, string $targetLocale, ?string $sourceLocale): int
    {
        if (! File::isDirectory($langRoot)) {
            return 0;
        }

        $sourceTemplateFiles = $this->collectTemplateFilesForLocale($langRoot, $sourceLocale);
        $templateFiles = $sourceTemplateFiles;
        $copyFromSource = $sourceLocale !== null && $sourceTemplateFiles !== [];

        if ($templateFiles === []) {
            $templateFiles = $this->collectTemplateFilesFromAllLocales($langRoot, $targetLocale);
        }

        if ($templateFiles === []) {
            return 0;
        }

        $updated = 0;
        $targetLocaleDir = rtrim($langRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$targetLocale;

        foreach ($templateFiles as $relativePath => $templateFile) {
            $targetFile = $targetLocaleDir.DIRECTORY_SEPARATOR.$relativePath;

            $templateTranslations = $this->loadTranslationFile($templateFile);
            if ($templateTranslations === []) {
                continue;
            }

            $targetValues = $copyFromSource
                ? $templateTranslations
                : $this->convertTranslationValuesToKeys($templateTranslations);

            $existingValues = $this->loadTranslationFile($targetFile);
            $mergedValues = $this->mergeMissingTranslations($existingValues, $targetValues);

            $this->saveTranslationFile($targetFile, $mergedValues);
            $updated++;
        }

        return $updated;
    }

    /**
     * @return array<string, string>
     */
    protected function collectTemplateFilesForLocale(string $langRoot, ?string $locale): array
    {
        if ($locale === null || trim($locale) === '') {
            return [];
        }

        $localeDirectory = rtrim($langRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$locale;
        if (! File::isDirectory($localeDirectory)) {
            return [];
        }

        $files = [];
        foreach (File::allFiles($localeDirectory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $fullPath = $file->getPathname();
            $relativePath = ltrim(str_replace($localeDirectory, '', $fullPath), DIRECTORY_SEPARATOR);
            if ($relativePath === '') {
                continue;
            }

            $files[$relativePath] = $fullPath;
        }

        ksort($files);

        return $files;
    }

    /**
     * @return array<string, string>
     */
    protected function collectTemplateFilesFromAllLocales(string $langRoot, string $targetLocale): array
    {
        $files = [];
        foreach (File::directories($langRoot) as $localeDirectory) {
            $locale = $this->normalizeLocaleCode((string) basename($localeDirectory));
            if ($locale === null || $locale === $targetLocale) {
                continue;
            }

            foreach (File::allFiles($localeDirectory) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $fullPath = $file->getPathname();
                $relativePath = ltrim(str_replace($localeDirectory, '', $fullPath), DIRECTORY_SEPARATOR);
                if ($relativePath === '' || isset($files[$relativePath])) {
                    continue;
                }

                $files[$relativePath] = $fullPath;
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * @param array<string, mixed> $translations
     * @return array<string, mixed>
     */
    protected function convertTranslationValuesToKeys(array $translations): array
    {
        $converted = [];
        foreach ($translations as $key => $value) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            if (is_array($value)) {
                $converted[$normalizedKey] = $this->convertTranslationValuesToKeys($value);
                continue;
            }

            $converted[$normalizedKey] = $normalizedKey;
        }

        return $converted;
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    protected function mergeMissingTranslations(array $existing, array $defaults): array
    {
        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $existing)) {
                $existing[$key] = $value;
                continue;
            }

            if (is_array($existing[$key]) && is_array($value)) {
                $existing[$key] = $this->mergeMissingTranslations($existing[$key], $value);
            }
        }

        return $existing;
    }
}
