<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use function Laravel\Prompts\confirm;

class SubscriptionUninstallCommand extends CoreCommand
{
    protected $signature = 'svarium:subscription.uninstall
        {--rollback= : Wymuś wycofanie migracji subskrypcji (true/false)}
        {--force : Force execution in production}';

    protected $description = 'Disable subscription module and optionally rollback migration';
    protected $descriptionKey = 'subscription.uninstall';

    public function handle(): int
    {
        $configPath = config_path('upsoftware.php');
        if (! is_file($configPath)) {
            $this->error('Brak pliku config/upsoftware.php');

            return self::FAILURE;
        }

        $wasEnabled = (bool) config('upsoftware.modules.builtin.subscriptions', false);

        $this->addConfigKey('upsoftware.php', 'modules.builtin.subscriptions', false, true);
        config()->set('upsoftware.modules.builtin.subscriptions', false);

        $rollback = $this->resolveBooleanOption(
            'rollback',
            'Czy wycofać migrację tabel subskrypcji?',
            true
        );

        if ($rollback === null) {
            return self::FAILURE;
        }

        $rolledBack = false;
        if ($rollback) {
            $exitCode = $this->rollbackSubscriptionMigration((bool) $this->option('force'));
            if ($exitCode !== self::SUCCESS) {
                return $exitCode;
            }

            $rolledBack = true;
        }

        $this->info('Zaktualizowano konfigurację subskrypcji.');
        $this->line('Subscriptions enabled: false');
        if (! $wasEnabled) {
            $this->line('Moduł subskrypcji był już wyłączony.');
        }
        $this->line('Rollback migracji subskrypcji: '.($rolledBack ? 'wykonany' : 'pominięty'));

        $this->newLine();
        $this->warn('Po zmianie uruchom: php artisan optimize:clear');

        return self::SUCCESS;
    }

    protected function rollbackSubscriptionMigration(bool $force = false): int
    {
        // We intentionally avoid migrate:reset here because it scans the whole
        // migrations table and prints noisy "Migration not found" for unrelated files.
        $migrationName = '2030_03_12_000016_create_subscription_management_tables';
        $migrationTable = $this->resolveMigrationsTableName();

        try {
            Schema::dropIfExists('tenant_subscription_items');
            Schema::dropIfExists('tenant_subscriptions');
            Schema::dropIfExists('subscription_limit_tiers');
            Schema::dropIfExists('subscription_modules');

            if (Schema::hasTable($migrationTable)) {
                DB::table($migrationTable)
                    ->where('migration', $migrationName)
                    ->delete();
            }
        } catch (\Throwable $e) {
            $this->error('Nie udało się wycofać tabel subskrypcji: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line('Wycofano tabele subskrypcji i usunięto wpis migracji.');

        return self::SUCCESS;
    }

    protected function resolveMigrationsTableName(): string
    {
        $configured = config('database.migrations', 'migrations');

        if (is_array($configured)) {
            $table = trim((string) ($configured['table'] ?? 'migrations'));

            return $table !== '' ? $table : 'migrations';
        }

        $table = trim((string) $configured);

        return $table !== '' ? $table : 'migrations';
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
