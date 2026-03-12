<?php

namespace Upsoftware\Svarium\Modules\Builtin\Support;

use Illuminate\Support\Collection;
use Throwable;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Roles\RolePermissionCatalog;
use Upsoftware\Svarium\Support\PermissionMatcher;

trait AuthorizesResourcePermissions
{
    protected function canResourceAction(PanelContext $context, string $action): bool
    {
        $user = $context->request()->user() ?? auth()->user();

        if (! is_object($user)) {
            return false;
        }

        if ($this->hasBypassRole($user)) {
            return true;
        }

        $permission = app(RolePermissionCatalog::class)
            ->resourcePermissionName(static::class, $action);

        return PermissionMatcher::hasPermission($user, $permission);
    }

    protected function hasBypassRole(object $user): bool
    {
        $roleKeys = array_values(array_unique(array_filter(array_map(
            static fn ($value) => strtolower(trim((string) $value)),
            (array) config('upsoftware.auth.tenant_bypass_role_keys', ['superadmin'])
        ))));

        if ($roleKeys === []) {
            return false;
        }

        if (method_exists($user, 'hasRole')) {
            foreach ($roleKeys as $roleKey) {
                try {
                    if ($user->hasRole($roleKey)) {
                        return true;
                    }
                } catch (Throwable) {
                    break;
                }
            }
        }

        foreach ($this->resolveAssignedRoles($user) as $role) {
            $assignedKey = strtolower(trim((string) ($role->role_key ?? '')));
            if ($assignedKey !== '' && in_array($assignedKey, $roleKeys, true)) {
                return true;
            }

            $assignedName = $this->resolveRoleName($role);
            if ($assignedName !== '' && in_array($assignedName, $roleKeys, true)) {
                return true;
            }
        }

        return false;
    }

    protected function resolveAssignedRoles(object $user): iterable
    {
        try {
            if (method_exists($user, 'roles')) {
                $roles = $user->roles;

                if ($roles instanceof Collection) {
                    return $roles->all();
                }

                if (is_iterable($roles)) {
                    return $roles;
                }

                $loadedRoles = $user->roles()->get();
                if ($loadedRoles instanceof Collection) {
                    return $loadedRoles->all();
                }

                return $loadedRoles;
            }
        } catch (Throwable) {
            return [];
        }

        try {
            $roles = $user->roles ?? [];

            if ($roles instanceof Collection) {
                return $roles->all();
            }

            if (is_iterable($roles)) {
                return $roles;
            }
        } catch (Throwable) {
            return [];
        }

        return [];
    }

    protected function resolveRoleName(mixed $role): string
    {
        $name = $role->name ?? null;

        if (is_array($name)) {
            $locale = app()->getLocale();

            return strtolower(trim((string) ($name[$locale] ?? reset($name) ?? '')));
        }

        return strtolower(trim((string) $name));
    }
}
