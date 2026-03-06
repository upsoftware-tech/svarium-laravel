<?php

namespace Upsoftware\Svarium\Console\Commands;

class TenantMigrateRollbackCommand extends CoreCommand
{
    protected $signature = 'svarium:tenant.migrate.rollback
        {--tenant=* : Tenant IDs (database mode)}
        {--step=1 : Number of steps for rollback mode}
        {--all : Run for all tenants, ignore env filter}
        {--path=* : Override migration path(s)}
        {--force : Force execution in production}';

    protected $description = 'Rollback tenant migrations using built-in Svarium tenancy';
    protected $descriptionKey = 'tenant.migrate_rollback';

    public function handle(): int
    {
        $step = max(1, (int) $this->option('step'));

        return (int) $this->call('svarium:tenant.migrate', [
            '--tenant' => (array) $this->option('tenant'),
            '--rollback' => true,
            '--step' => $step,
            '--all' => (bool) $this->option('all'),
            '--path' => (array) $this->option('path'),
            '--force' => (bool) $this->option('force'),
        ]);
    }
}
