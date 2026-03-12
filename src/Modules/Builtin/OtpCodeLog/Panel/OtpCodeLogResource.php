<?php

namespace Upsoftware\Svarium\Modules\Builtin\OtpCodeLog\Panel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;
use Upsoftware\Svarium\Modules\Builtin\OtpCodeLog\Tables\OtpCodeLogTable;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Resource;
use Upsoftware\Svarium\Panel\Table\Table;
use Upsoftware\Svarium\Panel\Table\TableBuilder;

class OtpCodeLogResource extends Resource
{
    protected static ?string $slug = 'system/otp-code-logs';

    public static function model(): string
    {
        return get_model('user_auth_code');
    }

    public function table(): TableBuilder
    {
        return Table::make(OtpCodeLogTable::class);
    }

    public function form(?Model $record = null): array
    {
        return [];
    }

    public function canList(PanelContext $context): bool
    {
        return $this->isSuperadmin($context->request()->user() ?? auth()->user());
    }

    public function canCreate(PanelContext $context): bool
    {
        return false;
    }

    public function canEdit(PanelContext $context): bool
    {
        return false;
    }

    public function canDelete(PanelContext $context): bool
    {
        return false;
    }

    public function canDuplicate(PanelContext $context): bool
    {
        return false;
    }

    public function canPreview(PanelContext $context): bool
    {
        return false;
    }

    public function canImport(PanelContext $context): bool
    {
        return false;
    }

    protected function isSuperadmin(?object $user): bool
    {
        if (! is_object($user)) {
            return false;
        }

        if (method_exists($user, 'hasRole')) {
            try {
                if ($user->hasRole('superadmin')) {
                    return true;
                }
            } catch (Throwable) {
                // fallback below
            }
        }

        foreach ($this->resolveAssignedRoles($user) as $role) {
            $roleKey = strtolower(trim((string) ($role->role_key ?? '')));
            if ($roleKey === 'superadmin') {
                return true;
            }

            $name = $role->name ?? null;

            if (is_array($name)) {
                foreach ($name as $value) {
                    if (strtolower(trim((string) $value)) === 'superadmin') {
                        return true;
                    }
                }

                continue;
            }

            if (is_string($name) && strtolower(trim($name)) === 'superadmin') {
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

        return [];
    }
}
