<?php

namespace Upsoftware\Svarium\Console\Commands;

use RuntimeException;
use Throwable;
use Upsoftware\Svarium\Console\Commands\Concerns\InteractsWithSvariumTranslations;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class TranslationAddCommand extends CoreCommand
{
    use InteractsWithSvariumTranslations;

    protected $signature = 'svarium:translation.add
        {--locale= : Kod języka, np. pl}
        {--module= : Nazwa modułu, np. Patient}
        {--key= : Klucz tłumaczenia}
        {--value= : Wartość tłumaczenia}';

    protected $description = 'Dodaje tłumaczenie globalnie lub do modułu';

    public function handle(): int
    {
        try {
            $localeOptions = $this->availableLocaleOptions();
            $locales = $this->resolveLocales($localeOptions);
            $targetLocationId = $this->resolveTargetLocationId();
            $key = $this->resolveTranslationKey();

            $targetsByLocale = [];
            $existingLocales = [];

            foreach ($locales as $locale) {
                $locations = $this->translationLocations($locale);
                $target = $locations[$targetLocationId] ?? null;
                if ($target === null) {
                    throw new RuntimeException("Nie znaleziono lokalizacji docelowej dla locale [{$locale}].");
                }

                $targetsByLocale[$locale] = $target;

                if (! is_file($target['file'])) {
                    continue;
                }

                $translations = $this->loadTranslationFile($target['file']);
                if (array_key_exists($key, $translations)) {
                    $existingLocales[] = strtoupper($locale);
                }
            }

            if ($existingLocales !== []) {
                $existingLocales = array_values(array_unique($existingLocales));
                if (! $this->confirmOverwrite(
                    'Klucz ['.$key.'] już istnieje dla locale: '.implode(', ', $existingLocales).'. Czy chcesz nadpisać?'
                )) {
                    throw new RuntimeException("Klucz [{$key}] już istnieje.");
                }
            }

            $valuesByLocale = $this->resolveTranslationValues($key, $locales, $localeOptions);

            $writtenLocales = [];
            foreach ($locales as $locale) {
                $target = $targetsByLocale[$locale];
                $this->ensureTranslationFile($target['file']);

                $translations = $this->loadTranslationFile($target['file']);
                $translations[$key] = $valuesByLocale[$locale] ?? $key;

                $this->saveTranslationFile($target['file'], $translations);
                $writtenLocales[] = strtoupper($locale);
            }

            foreach ($locales as $locale) {
                $this->syncPreparedTranslations($locale);
            }

            $sampleTarget = $targetsByLocale[$locales[0]];
            $this->info("Dodano tłumaczenie [{$key}] dla locale: ".implode(', ', $writtenLocales).'.');
            $this->line('Miejsce: '.$sampleTarget['label']);
            $this->line('Przykładowy plik: '.$sampleTarget['file']);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param array<string, string> $localeOptions
     * @return array<int, string>
     */
    protected function resolveLocales(array $localeOptions): array
    {
        $locale = $this->normalizeLocaleCode((string) ($this->option('locale') ?? ''));
        if ($locale !== null) {
            return [$locale];
        }

        if (! $this->input->isInteractive()) {
            throw new RuntimeException('Brak --locale=. Podaj kod języka, np. pl.');
        }

        if ($localeOptions === []) {
            throw new RuntimeException('Brak dostępnych języków.');
        }

        return array_keys($localeOptions);
    }

    /**
     * @return string
     */
    protected function resolveTargetLocationId(): string
    {
        $module = trim((string) ($this->option('module') ?? ''));
        if ($module !== '') {
            $locations = $this->translationLocations('pl');
            $matched = $this->matchTranslationLocation($module, $locations, true);
            if ($matched === null) {
                throw new RuntimeException("Nie znaleziono modułu: {$module}");
            }

            return (string) $matched['id'];
        }

        if (! $this->input->isInteractive()) {
            return 'global';
        }

        $locations = $this->translationLocations('pl');
        $selected = (string) select(
            label: 'Gdzie dodać tłumaczenie',
            options: $this->translationLocationOptions($locations)
        );

        return $selected;
    }

    protected function resolveTranslationKey(): string
    {
        $key = trim((string) ($this->option('key') ?? ''));
        if ($key !== '') {
            return $key;
        }

        if (! $this->input->isInteractive()) {
            throw new RuntimeException('Brak --key=. Podaj klucz tłumaczenia.');
        }

        while ($key === '') {
            $key = trim((string) text('Podaj klucz tłumaczenia', 'np. Create account'));
        }

        return $key;
    }

    /**
     * @param array<int, string> $locales
     * @param array<string, string> $localeOptions
     * @return array<string, string>
     */
    protected function resolveTranslationValues(string $key, array $locales, array $localeOptions): array
    {
        $optionValue = (string) ($this->option('value') ?? '');

        if (! $this->input->isInteractive()) {
            $value = trim($optionValue) !== '' ? $optionValue : $key;
            $values = [];
            foreach ($locales as $locale) {
                $values[$locale] = $value;
            }

            return $values;
        }

        $values = [];
        foreach ($locales as $locale) {
            $localeLabel = strtoupper($locale);
            if (isset($localeOptions[$locale]) && trim((string) $localeOptions[$locale]) !== '') {
                $localeLabel = strtoupper($locale);
            }

            $value = (string) text(
                "Wprowadź tłumaczenie ({$key}) dla {$localeLabel}",
                'puste = klucz',
                ''
            );

            $values[$locale] = trim($value) === '' ? $key : $value;
        }

        return $values;
    }

    protected function confirmOverwrite(string $message): bool
    {
        if (! $this->input->isInteractive()) {
            return false;
        }

        return confirm($message, false, 'Tak', 'Nie');
    }
}
