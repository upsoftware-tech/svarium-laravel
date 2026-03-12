<?php

namespace Upsoftware\Svarium\Console\Commands\Make;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Upsoftware\Svarium\Console\Commands\CoreCommand;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class NotificationCommand extends CoreCommand
{
    protected $signature = 'svarium:make.notification
        {name? : Notification class name}
        {--module= : Module name (e.g. Patient)}
        {--force : Overwrite if file already exists}';

    protected $description = 'Create a Svarium notification globally or in a selected module';
    protected $descriptionKey = 'make.notification';

    public function handle(): int
    {
        $name = $this->resolveNotificationName();
        if ($name === '') {
            $this->error('Nazwa notification jest wymagana.');
            return self::FAILURE;
        }

        $module = $this->resolveTargetModule();
        if ($module === false) {
            return self::FAILURE;
        }

        $relativeTarget = $module !== null
            ? "Svarium/Modules/{$module}/Notifications/{$name}"
            : "Svarium/Notifications/{$name}";
        $targetPath = app_path(str_replace('\\', '/', $relativeTarget).'.php');

        $force = (bool) $this->option('force');
        if (File::exists($targetPath) && ! $force) {
            if (! $this->input->isInteractive()) {
                $this->error("Plik już istnieje: {$targetPath}. Użyj --force, aby nadpisać.");
                return self::FAILURE;
            }

            $force = (bool) confirm(
                "Plik już istnieje: {$targetPath}. Czy nadpisać?",
                false,
                'Tak',
                'Nie'
            );

            if (! $force) {
                $this->warn('Anulowano tworzenie notification.');
                return self::FAILURE;
            }
        }

        $params = ['name' => $relativeTarget];
        if ($force) {
            $params['--force'] = true;
        }

        $exitCode = $this->call('make:notification', $params);
        if ($exitCode !== self::SUCCESS) {
            return $exitCode;
        }

        $this->info("Notification utworzony: {$targetPath}");

        return self::SUCCESS;
    }

    protected function resolveNotificationName(): string
    {
        $raw = trim((string) $this->argument('name'));

        if ($raw === '' && $this->input->isInteractive()) {
            while ($raw === '') {
                $raw = trim((string) text('Podaj nazwę Notification', 'np. UserRegisteredNotification', ''));

                if ($raw === '') {
                    $this->error('Nazwa notification jest wymagana.');
                }
            }
        }

        if ($raw === '') {
            return '';
        }

        $raw = str_replace('\\', '/', $raw);
        $raw = preg_replace('/\.php$/i', '', $raw) ?? $raw;

        $segments = array_values(array_filter(
            array_map(static fn (string $segment): string => Str::studly(trim($segment)), explode('/', $raw)),
            static fn (string $segment): bool => $segment !== ''
        ));

        return implode('/', $segments);
    }

    /**
     * @return string|false|null
     */
    protected function resolveTargetModule(): string|false|null
    {
        $modules = $this->availableModules();
        $moduleOption = trim((string) $this->option('module'));

        if ($moduleOption !== '') {
            $normalized = Str::studly($moduleOption);

            if (! in_array($normalized, $modules, true)) {
                $this->error("Moduł [{$moduleOption}] nie istnieje.");
                return false;
            }

            return $normalized;
        }

        if (! $this->input->isInteractive()) {
            return null;
        }

        if ($modules === []) {
            return null;
        }

        $destination = (string) select(
            'Gdzie utworzyć Notification?',
            [
                '__global__' => 'Katalog ogólny Svarium (app/Svarium/Notifications)',
                '__module__' => 'Moduł Svarium (app/Svarium/Modules/{Module}/Notifications)',
            ],
            '__global__'
        );

        if ($destination === '__global__') {
            return null;
        }

        $moduleOptions = [];
        foreach ($modules as $module) {
            $moduleOptions[$module] = $module;
        }

        return (string) select('Wybierz moduł', $moduleOptions, array_key_first($moduleOptions));
    }

    /**
     * @return list<string>
     */
    protected function availableModules(): array
    {
        $base = svarium_modules();

        if (! File::isDirectory($base)) {
            return [];
        }

        $directories = File::directories($base);
        $modules = [];

        foreach ($directories as $directory) {
            $name = trim((string) basename($directory));
            if ($name === '') {
                continue;
            }

            $modules[] = Str::studly($name);
        }

        sort($modules);

        return $modules;
    }
}

