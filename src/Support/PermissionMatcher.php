<?php

namespace Upsoftware\Svarium\Support;

use Throwable;

class PermissionMatcher
{
    public static function hasPermission(?object $user, string $requiredPermission): bool
    {
        $requiredPermission = trim($requiredPermission);
        if (! is_object($user) || $requiredPermission === '') {
            return false;
        }

        if (self::hasExactPermissionCheck($user, $requiredPermission)) {
            return true;
        }

        $required = strtolower($requiredPermission);
        $assigned = self::assignedPermissions($user);
        if ($assigned === []) {
            return false;
        }

        if (in_array($required, $assigned, true)) {
            return true;
        }

        if (str_contains($required, '*')) {
            foreach ($assigned as $permission) {
                if (self::wildcardMatches($required, $permission)) {
                    return true;
                }
            }
        }

        foreach ($assigned as $permission) {
            if (! str_contains($permission, '*')) {
                continue;
            }

            if (self::wildcardMatches($permission, $required)) {
                return true;
            }
        }

        return false;
    }

    protected static function hasExactPermissionCheck(object $user, string $permission): bool
    {
        if (method_exists($user, 'can')) {
            try {
                if ((bool) $user->can($permission)) {
                    return true;
                }
            } catch (Throwable) {
                // continue with fallback checks
            }
        }

        if (method_exists($user, 'hasPermissionTo')) {
            try {
                if ((bool) $user->hasPermissionTo($permission)) {
                    return true;
                }
            } catch (Throwable) {
                // continue with wildcard checks
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected static function assignedPermissions(object $user): array
    {
        $permissions = [];

        if (method_exists($user, 'getAllPermissions')) {
            try {
                foreach ($user->getAllPermissions() as $permission) {
                    $name = strtolower(trim((string) ($permission->name ?? '')));
                    if ($name !== '') {
                        $permissions[] = $name;
                    }
                }
            } catch (Throwable) {
                // continue with next strategy
            }
        }

        if ($permissions === [] && method_exists($user, 'getPermissionNames')) {
            try {
                $names = $user->getPermissionNames();
                if (is_object($names) && method_exists($names, 'all')) {
                    foreach ((array) $names->all() as $name) {
                        $normalized = strtolower(trim((string) $name));
                        if ($normalized !== '') {
                            $permissions[] = $normalized;
                        }
                    }
                }
            } catch (Throwable) {
                // continue with next strategy
            }
        }

        if ($permissions === [] && method_exists($user, 'permissions')) {
            try {
                foreach ($user->permissions as $permission) {
                    $name = strtolower(trim((string) ($permission->name ?? '')));
                    if ($name !== '') {
                        $permissions[] = $name;
                    }
                }
            } catch (Throwable) {
                return [];
            }
        }

        return array_values(array_unique($permissions));
    }

    protected static function wildcardMatches(string $pattern, string $value): bool
    {
        $pattern = trim($pattern);
        $value = trim($value);
        if ($pattern === '' || $value === '') {
            return false;
        }

        $quoted = preg_quote($pattern, '/');
        $quoted = str_replace('\*', '.*', $quoted);

        return preg_match('/^'.$quoted.'$/i', $value) === 1;
    }
}

