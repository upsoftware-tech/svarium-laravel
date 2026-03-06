<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Throwable;
use function Laravel\Prompts\select;

class DiagnoseDatabaseConnectionCommand extends CoreCommand
{
    protected $signature = 'svarium:diagnose.database.connection
        {--model= : Klucz modelu z upsoftware.models lub pełna klasa modelu}';

    protected $description = 'Diagnozuje połączenie bazy danych dla wybranego modelu';

    public function handle(): int
    {
        try {
            [$modelKey, $modelClass] = $this->resolveTargetModel();

            /** @var Model $model */
            $model = new $modelClass();

            $rawConnectionName = $model->getConnectionName();
            $fallbackDefaultConnection = (string) config('database.default', 'mysql');
            $resolvedConnectionName = is_string($rawConnectionName) && $rawConnectionName !== ''
                ? $rawConnectionName
                : $fallbackDefaultConnection;

            $connectionError = null;

            try {
                $resolvedConnectionName = $model->getConnection()->getName();
            } catch (Throwable $exception) {
                $connectionError = $exception->getMessage();
            }

            $connectionConfig = config("database.connections.{$resolvedConnectionName}");
            if (! is_array($connectionConfig)) {
                throw new RuntimeException("Nie znaleziono konfiguracji połączenia [{$resolvedConnectionName}] w database.connections.");
            }

            $this->table(
                ['Pole', 'Wartość'],
                [
                    ['Model key', $modelKey],
                    ['Model class', $modelClass],
                    ['Tabela', $model->getTable()],
                    ['Connection (model)', $rawConnectionName ?: '(null -> database.default)'],
                    ['Connection (resolved)', $resolvedConnectionName],
                    ['Driver', (string) ($connectionConfig['driver'] ?? '')],
                    ['Host', (string) ($connectionConfig['host'] ?? '')],
                    ['Port', (string) ($connectionConfig['port'] ?? '')],
                    ['Database', (string) ($connectionConfig['database'] ?? '')],
                    ['Username', (string) ($connectionConfig['username'] ?? '')],
                    ['Password', (string) ($connectionConfig['password'] ?? '')],
                ]
            );

            if ($connectionError !== null) {
                $this->warn('Uwaga: nie udało się zestawić runtime connection dla modelu. Pokazano dane z config().');
                $this->line('Błąd: '.$connectionError);
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array{0:string,1:class-string<Model>}
     */
    protected function resolveTargetModel(): array
    {
        $models = $this->availableModels();
        if ($models === []) {
            throw new RuntimeException('Brak poprawnych modeli w config upsoftware.models.');
        }

        $option = trim((string) $this->option('model'));
        if ($option !== '') {
            $resolved = $this->resolveModelFromOption($option, $models);
            if ($resolved !== null) {
                return $resolved;
            }

            throw new RuntimeException("Nie znaleziono modelu dla opcji --model={$option}.");
        }

        if (! $this->input->isInteractive()) {
            throw new RuntimeException('Podaj --model={key|class} w trybie nieinteraktywnym.');
        }

        $selectOptions = [];
        foreach ($models as $key => $class) {
            $selectOptions[$key] = "{$key} ({$class})";
        }

        $selectedKey = (string) select('Wybierz model do diagnozy połączenia', $selectOptions);

        if (! array_key_exists($selectedKey, $models)) {
            throw new RuntimeException('Wybrano nieprawidłowy model.');
        }

        return [$selectedKey, $models[$selectedKey]];
    }

    /**
     * @param  array<string, class-string<Model>>  $models
     * @return array{0:string,1:class-string<Model>}|null
     */
    protected function resolveModelFromOption(string $option, array $models): ?array
    {
        if (array_key_exists($option, $models)) {
            return [$option, $models[$option]];
        }

        if (class_exists($option) && is_subclass_of($option, Model::class)) {
            foreach ($models as $key => $class) {
                if ($class === $option) {
                    return [$key, $class];
                }
            }

            return [$option, $option];
        }

        return null;
    }

    /**
     * @return array<string, class-string<Model>>
     */
    protected function availableModels(): array
    {
        $configured = config('upsoftware.models', []);
        if (! is_array($configured)) {
            return [];
        }

        $models = [];
        foreach ($configured as $key => $class) {
            if (! is_string($key) || ! is_string($class)) {
                continue;
            }

            $class = trim($class);
            if ($class === '' || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $models[$key] = $class;
        }

        ksort($models);

        return $models;
    }
}

