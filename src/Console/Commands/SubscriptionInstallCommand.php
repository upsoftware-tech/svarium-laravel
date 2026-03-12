<?php

namespace Upsoftware\Svarium\Console\Commands;

use function Laravel\Prompts\confirm;

class SubscriptionInstallCommand extends CoreCommand
{
    protected $signature = 'svarium:subscription.install
        {--enable= : Wymuś włączenie/wyłączenie modułu subskrypcji (true/false)}
        {--migrate= : Wymuś uruchomienie migracji subskrypcji (true/false)}
        {--force : Force execution in production}';

    protected $description = 'Configure subscription module and optionally run migration';
    protected $descriptionKey = 'subscription.install';

    public function handle(): int
    {
        $configPath = config_path('upsoftware.php');
        if (! is_file($configPath)) {
            $this->error('Brak pliku config/upsoftware.php');

            return self::FAILURE;
        }

        $enabled = $this->resolveBooleanOption(
            'enable',
            'Czy włączyć moduł subskrypcji?',
            (bool) config('upsoftware.modules.builtin.subscriptions', false)
        );

        if ($enabled === null) {
            return self::FAILURE;
        }

        $this->addConfigKey('upsoftware.php', 'modules.builtin.subscriptions', $enabled, true);

        $addedModels = $this->ensureSubscriptionModels();

        $migrated = false;
        if ($enabled) {
            $runMigration = $this->resolveBooleanOption(
                'migrate',
                'Czy uruchomić migrację tabel subskrypcji?',
                true
            );

            if ($runMigration === null) {
                return self::FAILURE;
            }

            if ($runMigration) {
                $exitCode = $this->runSubscriptionMigration((bool) $this->option('force'));
                if ($exitCode !== self::SUCCESS) {
                    return $exitCode;
                }

                $migrated = true;
            }
        }

        $this->info('Zaktualizowano konfigurację subskrypcji.');
        $this->line('Subscriptions enabled: '.($enabled ? 'true' : 'false'));
        if ($addedModels !== []) {
            $this->line('Dodano brakujące modele: '.implode(', ', $addedModels));
        } else {
            $this->line('Modele subskrypcji były już skonfigurowane.');
        }
        if ($enabled) {
            $this->line('Migracja subskrypcji: '.($migrated ? 'wykonana' : 'pominięta'));
        }

        $this->newLine();
        $this->warn('Po zmianie uruchom: php artisan optimize:clear');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    protected function ensureSubscriptionModels(): array
    {
        $mappings = [
            'subscription_module' => \Upsoftware\Svarium\Models\SubscriptionModule::class,
            'subscription_limit_tier' => \Upsoftware\Svarium\Models\SubscriptionLimitTier::class,
            'tenant_subscription' => \Upsoftware\Svarium\Models\TenantSubscription::class,
            'tenant_subscription_item' => \Upsoftware\Svarium\Models\TenantSubscriptionItem::class,
        ];

        $existing = (array) config('upsoftware.models', []);
        $added = [];

        foreach ($mappings as $key => $className) {
            $current = $existing[$key] ?? null;

            if (is_string($current) && trim($current) !== '') {
                continue;
            }

            $this->addConfigKey('upsoftware.php', "models.{$key}", $className, true);
            config()->set("upsoftware.models.{$key}", $className);
            $added[] = $key;
        }

        return $added;
    }

    protected function runSubscriptionMigration(bool $force = false): int
    {
        $migrationPath = __DIR__.'/../../database/migrations/2030_03_12_000016_create_subscription_management_tables.php';

        if (! is_file($migrationPath)) {
            $this->error("Nie znaleziono pliku migracji: {$migrationPath}");

            return self::FAILURE;
        }

        $originalBypass = env('SVARIUM_ALLOW_MIGRATE');
        putenv('SVARIUM_ALLOW_MIGRATE=1');
        $_ENV['SVARIUM_ALLOW_MIGRATE'] = '1';
        $_SERVER['SVARIUM_ALLOW_MIGRATE'] = '1';

        try {
            return (int) $this->call('migrate', [
                '--path' => $migrationPath,
                '--realpath' => true,
                '--force' => $force,
            ]);
        } finally {
            if ($originalBypass === null || $originalBypass === false) {
                putenv('SVARIUM_ALLOW_MIGRATE');
                unset($_ENV['SVARIUM_ALLOW_MIGRATE'], $_SERVER['SVARIUM_ALLOW_MIGRATE']);
            } else {
                $restored = (string) $originalBypass;
                putenv("SVARIUM_ALLOW_MIGRATE={$restored}");
                $_ENV['SVARIUM_ALLOW_MIGRATE'] = $restored;
                $_SERVER['SVARIUM_ALLOW_MIGRATE'] = $restored;
            }
        }
    }

    protected function resolveBooleanOption(string $option, string $question, bool $default): ?bool
    {
        $raw = $this->option($option);

        if (is_bool($raw)) {
            return $raw;
        }

        if (is_string($raw) && trim($raw) !== '') {
            $normalized = strtolower(trim($raw));
            if (in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'n', 'off'], true)) {
                return false;
            }

            $this->error("Nieprawidłowa wartość opcji --{$option}: {$raw}. Użyj true/false.");

            return null;
        }

        if ($this->input->isInteractive()) {
            return confirm($question, $default, 'Tak', 'Nie');
        }

        return $default;
    }
}

