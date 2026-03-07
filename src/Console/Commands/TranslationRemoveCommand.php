<?php

namespace Upsoftware\Svarium\Console\Commands;

use RuntimeException;
use Throwable;
use Upsoftware\Svarium\Console\Commands\Concerns\InteractsWithSvariumTranslations;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

class TranslationRemoveCommand extends CoreCommand
{
    use InteractsWithSvariumTranslations;

    protected $signature = 'svarium:translation.remove
        {--locale= : Kod języka, np. pl}
        {--from= : Skąd usunąć: global lub nazwa modułu}
        {--module= : Alias dla --from (nazwa modułu)}
        {--key=* : Klucz tłumaczenia (można podać wiele razy)}
        {--force : Usuń bez potwierdzenia}';

    protected $description = 'Usuwa tłumaczenia z globalnego pliku lub modułu';

    public function handle(): int
    {
        try {
            $locale = $this->resolveLocale();
            $locations = $this->translationLocations($locale);
            $source = $this->resolveSourceLocation($locations);

            if (! is_file($source['file'])) {
                throw new RuntimeException('Nie znaleziono pliku źródłowego: '.$source['file']);
            }

            $translations = $this->loadTranslationFile($source['file']);
            if ($translations === []) {
                throw new RuntimeException('Wybrane miejsce nie zawiera tłumaczeń do usunięcia.');
            }

            $keys = $this->resolveTranslationKeys($translations);
            $missing = array_values(array_filter($keys, static fn (string $key): bool => ! array_key_exists($key, $translations)));
            if ($missing !== []) {
                throw new RuntimeException('Nie znaleziono kluczy: '.implode(', ', $missing));
            }

            if (! $this->shouldDelete($keys, $source['label'])) {
                $this->line('Anulowano usuwanie.');

                return self::SUCCESS;
            }

            foreach ($keys as $key) {
                unset($translations[$key]);
            }

            $this->saveTranslationFile($source['file'], $translations);
            $this->syncPreparedTranslations($locale);

            $this->info('Usunięto klucze: '.implode(', ', $keys).'.');
            $this->line('Źródło: '.$source['label']);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    protected function resolveLocale(): string
    {
        $locale = $this->normalizeLocaleCode((string) ($this->option('locale') ?? ''));
        $options = $this->availableLocaleOptions();

        if ($locale !== null) {
            return $locale;
        }

        if (! $this->input->isInteractive()) {
            throw new RuntimeException('Brak --locale=. Podaj kod języka, np. pl.');
        }

        if ($options === []) {
            throw new RuntimeException('Brak dostępnych języków do wyboru.');
        }

        return (string) select('Wybierz język', $options);
    }

    /**
     * @param array<string, array{id: string, type: string, label: string, file: string, moduleName: string|null}> $locations
     * @return array{id: string, type: string, label: string, file: string, moduleName: string|null}
     */
    protected function resolveSourceLocation(array $locations): array
    {
        $module = trim((string) ($this->option('module') ?? ''));
        if ($module !== '') {
            $matched = $this->matchTranslationLocation($module, $locations, true);
            if ($matched === null) {
                throw new RuntimeException("Nie znaleziono modułu: {$module}");
            }

            return $matched;
        }

        $from = trim((string) ($this->option('from') ?? ''));
        if ($from !== '') {
            $matched = $this->matchTranslationLocation($from, $locations);
            if ($matched === null) {
                throw new RuntimeException("Nie znaleziono źródła: {$from}");
            }

            return $matched;
        }

        if (! $this->input->isInteractive()) {
            throw new RuntimeException('Brak --from=. Użyj: global albo nazwa modułu.');
        }

        $selected = (string) select(
            label: 'Skąd chcesz usunąć tłumaczenia',
            options: $this->translationLocationOptions($locations)
        );

        return $locations[$selected];
    }

    /**
     * @param array<string, mixed> $translations
     * @return array<int, string>
     */
    protected function resolveTranslationKeys(array $translations): array
    {
        $keysOption = $this->option('key');
        $keys = [];

        if (is_array($keysOption)) {
            foreach ($keysOption as $value) {
                $key = trim((string) $value);
                if ($key !== '') {
                    $keys[] = $key;
                }
            }
        } elseif (is_string($keysOption)) {
            $key = trim($keysOption);
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        if ($keys !== []) {
            return array_values(array_unique($keys));
        }

        if (! $this->input->isInteractive()) {
            throw new RuntimeException('Brak --key=. Podaj co najmniej jeden klucz.');
        }

        $availableKeys = array_keys($translations);
        natcasesort($availableKeys);

        $selected = multiselect(
            label: 'Wybierz klucze do usunięcia',
            options: array_combine($availableKeys, $availableKeys)
        );

        $selected = array_values(array_filter(
            array_map(static fn ($value): string => trim((string) $value), $selected),
            static fn (string $value): bool => $value !== ''
        ));

        if ($selected === []) {
            throw new RuntimeException('Nie wybrano żadnych kluczy do usunięcia.');
        }

        return array_values(array_unique($selected));
    }

    /**
     * @param array<int, string> $keys
     */
    protected function shouldDelete(array $keys, string $sourceLabel): bool
    {
        if ((bool) $this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        $count = count($keys);
        $keysLabel = implode(', ', $keys);

        return confirm(
            $count === 1
                ? "Czy na pewno usunąć klucz [{$keysLabel}] z {$sourceLabel}?"
                : "Czy na pewno usunąć {$count} kluczy z {$sourceLabel}? ({$keysLabel})",
            false,
            'Tak',
            'Nie'
        );
    }
}
