<?php

namespace Upsoftware\Svarium\Console\Commands;

use RuntimeException;
use Throwable;
use Upsoftware\Svarium\Console\Commands\Concerns\InteractsWithSvariumTranslations;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

class TranslationMoveCommand extends CoreCommand
{
    use InteractsWithSvariumTranslations;

    protected $signature = 'svarium:translation.move
        {--locale= : Kod języka, np. pl}
        {--from= : Skąd przenieść: global lub nazwa modułu}
        {--to= : Dokąd przenieść: global lub nazwa modułu}
        {--key=* : Klucz tłumaczenia (można podać wiele razy)}
        {--delete-source : Usuń wpis z miejsca źródłowego}';

    protected $description = 'Przenosi tłumaczenia między globalnym plikiem i modułami';

    public function handle(): int
    {
        try {
            $locale = $this->resolveLocale();
            $locations = $this->translationLocations($locale);

            $source = $this->resolveSourceLocation($locations);
            $destination = $this->resolveDestinationLocation($locations, $source['id']);

            $sourceTranslations = $this->loadSourceTranslations($source['file']);
            if ($sourceTranslations === []) {
                throw new RuntimeException('Wybrane źródło nie zawiera tłumaczeń do przeniesienia.');
            }

            $keys = $this->resolveTranslationKeys($sourceTranslations);
            $missing = array_values(array_filter($keys, static fn (string $key): bool => ! array_key_exists($key, $sourceTranslations)));
            if ($missing !== []) {
                throw new RuntimeException('Nie znaleziono kluczy w źródle: '.implode(', ', $missing));
            }

            $this->ensureTranslationFile($destination['file']);
            $destinationTranslations = $this->loadTranslationFile($destination['file']);

            $moved = [];
            $skipped = [];
            foreach ($keys as $key) {
                if (array_key_exists($key, $destinationTranslations)) {
                    if (! $this->confirmOverwrite("Klucz [{$key}] już istnieje w miejscu docelowym. Nadpisać?")) {
                        $skipped[] = $key;
                        continue;
                    }
                }

                $destinationTranslations[$key] = $sourceTranslations[$key];
                $moved[] = $key;
            }

            if ($moved === []) {
                throw new RuntimeException('Nie przeniesiono żadnego klucza (wszystkie zostały pominięte).');
            }

            $this->saveTranslationFile($destination['file'], $destinationTranslations);

            $removeFromSource = $this->resolveRemoveFromSource();
            if ($removeFromSource) {
                $sourceTranslations = $this->loadSourceTranslations($source['file']);
                foreach ($moved as $key) {
                    unset($sourceTranslations[$key]);
                }

                $this->saveTranslationFile($source['file'], $sourceTranslations);
            }

            $this->syncPreparedTranslations($locale);

            $this->info('Przeniesiono klucze: '.implode(', ', $moved).'.');
            if ($skipped !== []) {
                $this->line('Pominięto klucze: '.implode(', ', $skipped).'.');
            }
            $this->line('Źródło: '.$source['label']);
            $this->line('Cel: '.$destination['label']);
            $this->line('Usunięto ze źródła: '.($removeFromSource ? 'tak' : 'nie'));

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
            label: 'Skąd chcesz przenieść tłumaczenie',
            options: $this->translationLocationOptions($locations)
        );

        return $locations[$selected];
    }

    /**
     * @param array<string, array{id: string, type: string, label: string, file: string, moduleName: string|null}> $locations
     * @return array{id: string, type: string, label: string, file: string, moduleName: string|null}
     */
    protected function resolveDestinationLocation(array $locations, string $sourceId): array
    {
        $available = $locations;
        unset($available[$sourceId]);

        if ($available === []) {
            throw new RuntimeException('Brak dostępnego miejsca docelowego do przeniesienia.');
        }

        $to = trim((string) ($this->option('to') ?? ''));
        if ($to !== '') {
            $matched = $this->matchTranslationLocation($to, $available);
            if ($matched === null) {
                throw new RuntimeException("Nie znaleziono celu: {$to}");
            }

            return $matched;
        }

        if (! $this->input->isInteractive()) {
            throw new RuntimeException('Brak --to=. Użyj: global albo nazwa modułu.');
        }

        $selected = (string) select(
            label: 'Dokąd chcesz przenieść tłumaczenie',
            options: $this->translationLocationOptions($available)
        );

        return $available[$selected];
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
            label: 'Wybierz klucze do przeniesienia',
            options: array_combine($availableKeys, $availableKeys)
        );

        $selected = array_values(array_filter(
            array_map(static fn ($value): string => trim((string) $value), $selected),
            static fn (string $value): bool => $value !== ''
        ));

        if ($selected === []) {
            throw new RuntimeException('Nie wybrano żadnych kluczy do przeniesienia.');
        }

        return array_values(array_unique($selected));
    }

    protected function resolveRemoveFromSource(): bool
    {
        if ((bool) $this->option('delete-source')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        return confirm('Czy usunąć z miejsca źródłowego?', true, 'Tak', 'Nie');
    }

    protected function confirmOverwrite(string $message): bool
    {
        if (! $this->input->isInteractive()) {
            return false;
        }

        return confirm($message, false, 'Tak', 'Nie');
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadSourceTranslations(string $file): array
    {
        if (! is_file($file)) {
            throw new RuntimeException('Nie znaleziono pliku źródłowego: '.$file);
        }

        return $this->loadTranslationFile($file);
    }
}
