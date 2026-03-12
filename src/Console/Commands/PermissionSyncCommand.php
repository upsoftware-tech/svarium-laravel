<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Throwable;
use Upsoftware\Svarium\Roles\RolePermissionCatalog;

class PermissionSyncCommand extends CoreCommand
{
    protected $signature = 'svarium:permission.sync
        {--guard=* : Guard names to sync (default: all configured guards)}';

    protected $description = 'Synchronizes Svarium permissions from resources and operations';

    public function handle(): int
    {
        try {
            $permissionModel = $this->resolvePermissionModelClass();
            $guards = $this->resolveGuards();

            foreach ($guards as $guard) {
                app(RolePermissionCatalog::class)->ensurePermissionsForGuard($guard);
            }

            $count = $permissionModel::query()
                ->whereIn('guard_name', $guards)
                ->count();

            if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            }

            $this->info('Permissiony zostały zsynchronizowane.');
            $this->line('Guardy: '.implode(', ', $guards));
            $this->line('Liczba permissionów: '.$count);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return class-string<Model>
     */
    protected function resolvePermissionModelClass(): string
    {
        $permissionModelClass = (string) config('permission.models.permission', \Spatie\Permission\Models\Permission::class);

        if ($permissionModelClass === '' || ! class_exists($permissionModelClass)) {
            throw new RuntimeException("Nie znaleziono modelu permission: {$permissionModelClass}");
        }

        if (! is_subclass_of($permissionModelClass, Model::class)) {
            throw new RuntimeException("Model permission [{$permissionModelClass}] nie dziedziczy po Illuminate\\Database\\Eloquent\\Model.");
        }

        return $permissionModelClass;
    }

    /**
     * @return list<string>
     */
    protected function resolveGuards(): array
    {
        $guards = array_values(array_filter(array_map(
            static fn (mixed $guard): string => trim((string) $guard),
            (array) $this->option('guard')
        )));

        if ($guards !== []) {
            return array_values(array_unique($guards));
        }

        $configuredGuards = array_keys((array) config('auth.guards', []));

        if ($configuredGuards === []) {
            $configuredGuards = [trim((string) config('auth.defaults.guard', 'web')) ?: 'web'];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $guard): string => trim((string) $guard),
            $configuredGuards
        ))));
    }
}
