<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SwitchRoleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof Model) {
            abort(403);
        }

        $validated = $request->validate([
            'role_id' => ['required', 'integer'],
        ]);

        $roleId = (int) ($validated['role_id'] ?? 0);
        if ($roleId <= 0) {
            abort(422);
        }

        // Keep role-switch validation and role list source consistent with SidebarUser/get_roles().
        if (function_exists('change_role') && change_role($roleId, $user)) {
            $this->persistRoleInSession($request, $roleId, $user, true);

            return $this->redirectBack($request);
        }

        $allowedRoleIds = $this->resolveAllowedRoleIds($user);
        if (in_array($roleId, $allowedRoleIds, true)) {
            $this->persistRoleInSession($request, $roleId, $user, false, $allowedRoleIds);

            return $this->redirectBack($request);
        }

        abort(403);
    }

    protected function redirectBack(Request $request): RedirectResponse
    {
        $target = trim((string) ($request->headers->get('referer') ?? ''));
        if ($target === '') {
            $target = url()->previous();
        }
        if (trim($target) === '') {
            $target = '/';
        }

        return redirect()->to($target, 303);
    }

    /**
     * @param array<int, int> $allowedRoleIds
     */
    protected function persistRoleInSession(
        Request $request,
        int $roleId,
        Model $user,
        bool $viaHelper,
        array $allowedRoleIds = []
    ): void {
        $request->session()->put('svarium.active_role_id', $roleId);
        $request->session()->put('role_id', $roleId);

        if (function_exists('svarium_session_put')) {
            svarium_session_put('svarium.active_role_id', $roleId);
            svarium_session_put('role_id', $roleId);
        }

        if (function_exists('debug_role') && debug_role()) {
            $payload = [
                'requested_role_id' => $roleId,
                'user_id' => (string) $user->getKey(),
                'via_helper' => $viaHelper,
                'allowed_role_ids' => $allowedRoleIds,
                'session_before_save' => [
                    'svarium.active_role_id' => (int) $request->session()->get('svarium.active_role_id', 0),
                    'role_id' => (int) $request->session()->get('role_id', 0),
                ],
                'session_id' => (string) $request->session()->getId(),
                'route' => trim((string) optional($request->route())->getName()),
                'path' => trim((string) $request->path()),
            ];

            $request->session()->put('svarium.debug_role_last_switch', $payload);
            Log::debug('svarium.role.switch', $payload);
        }

        try {
            $request->session()->save();
        } catch (\Throwable) {
            // Let framework lifecycle handle save as fallback.
        }
    }

    /**
     * @return array<int, int>
     */
    protected function resolveAllowedRoleIds(Model $user): array
    {
        if (function_exists('get_roles')) {
            try {
                $resolved = get_roles($user);
                if (is_array($resolved) && $resolved !== []) {
                    return array_values(array_unique(array_filter(array_map(
                        static fn (mixed $row): int => (int) (is_array($row) ? ($row['id'] ?? 0) : 0),
                        $resolved
                    ), static fn (int $id): bool => $id > 0)));
                }
            } catch (\Throwable) {
                // fallback to pivot lookup below
            }
        }

        $ids = $this->resolveRoleIdsFromPivotTable($user);

        if ($ids === [] && method_exists($user, 'roles')) {
            try {
                $ids = $user->roles()
                    ->pluck('id')
                    ->map(static fn (mixed $value): int => (int) $value)
                    ->filter(static fn (int $value): bool => $value > 0)
                    ->unique()
                    ->values()
                    ->all();
            } catch (\Throwable) {
                $ids = [];
            }
        }

        return $ids;
    }

    /**
     * @return array<int, int>
     */
    protected function resolveRoleIdsFromPivotTable(Model $user): array
    {
        $table = trim((string) config('permission.table_names.model_has_roles', 'model_has_roles'));
        if ($table === '') {
            return [];
        }

        try {
            if (! Schema::hasTable($table)) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        $hasModelType = Schema::hasColumn($table, 'model_type');
        $hasStatus = Schema::hasColumn($table, 'status');

        $modelHasRoleClass = (string) get_model('model_has_role');
        $query = is_string($modelHasRoleClass) && $modelHasRoleClass !== '' && class_exists($modelHasRoleClass)
            ? $modelHasRoleClass::query()->from($table)->where('model_id', $user->getKey())
            : DB::table($table)->where('model_id', $user->getKey());

        if ($hasModelType) {
            $modelTypes = $this->resolveUserModelTypeCandidates($user);
            if ($modelTypes !== []) {
                $query->whereIn('model_type', $modelTypes);
            }
        }

        if ($hasStatus) {
            $query->where('status', 1);
        }

        $ids = $query
            ->pluck('role_id')
            ->map(static fn (mixed $value): int => (int) $value)
            ->filter(static fn (int $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();

        return $ids;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveUserModelTypeCandidates(Model $user): array
    {
        $candidates = [
            trim((string) svarium_model_type($user)),
            trim((string) $user::class),
            trim((string) config('upsoftware.models.user', '')),
            trim((string) config('auth.providers.users.model', '')),
            'App\\Models\\User',
            'Upsoftware\\Svarium\\Models\\User',
        ];

        $unique = [];

        foreach ($candidates as $candidate) {
            if ($candidate === '' || in_array($candidate, $unique, true)) {
                continue;
            }

            $unique[] = ltrim($candidate, '\\');
        }

        return $unique;
    }

    protected function resolveCurrentTenantId(): ?string
    {
        try {
            $tenant = function_exists('tenant') ? tenant() : null;
            if (! is_object($tenant) || ! method_exists($tenant, 'getKey')) {
                return null;
            }

            $id = trim((string) $tenant->getKey());

            return $id !== '' ? $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolveCurrentDomainId(): ?string
    {
        try {
            $domainId = function_exists('tenant_domain')
                ? tenant_domain('id')
                : request()?->attributes?->get('svarium.domain.id');

            $id = trim((string) ($domainId ?? ''));

            return $id !== '' ? $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function userBelongsToTenant(Model $user, ?string $tenantId): bool
    {
        if ($tenantId === null || $tenantId === '') {
            return true;
        }

        $modelHasTenant = get_model('model_has_tenant');
        if (! is_string($modelHasTenant) || ! class_exists($modelHasTenant)) {
            return true;
        }

        try {
            return $modelHasTenant::query()
                ->where('model_id', (string) $user->getKey())
                ->where('model_type', svarium_model_type($user))
                ->where('tenant_id', $tenantId)
                ->exists();
        } catch (\Throwable) {
            return true;
        }
    }

    protected function userMatchesCurrentDomain(Model $user, ?string $tenantId = null): bool
    {
        if (! svarium_tenancy_column_mode()) {
            return true;
        }

        if (! (bool) config('upsoftware.tenancy.column.model_maps.domains.enabled', true)) {
            return true;
        }

        $domainId = $this->resolveCurrentDomainId();
        if ($domainId === null) {
            return true;
        }

        $table = trim((string) config('upsoftware.tenancy.column.model_maps.domains.table', 'model_has_domains'));
        if ($table === '') {
            $table = 'model_has_domains';
        }

        try {
            if (! Schema::hasTable($table) && Schema::hasTable('model_has_domain_tenants')) {
                $table = 'model_has_domain_tenants';
            }

            if (! Schema::hasTable($table)) {
                return true;
            }
        } catch (\Throwable) {
            return true;
        }

        $domainColumn = trim((string) config('upsoftware.tenancy.column.model_maps.domains.domain_key', 'domain_id'));
        if ($domainColumn === '') {
            $domainColumn = 'domain_id';
        }

        try {
            if (! Schema::hasColumn($table, $domainColumn) && Schema::hasColumn($table, 'tenant_domain_id')) {
                $domainColumn = 'tenant_domain_id';
            }

            if (! Schema::hasColumn($table, $domainColumn)) {
                return true;
            }
        } catch (\Throwable) {
            return true;
        }

        $query = DB::table($table)
            ->where('model_id', (string) $user->getKey());

        try {
            if (Schema::hasColumn($table, 'model_type')) {
                $modelTypes = $this->resolveUserModelTypeCandidates($user);
                if ($modelTypes !== []) {
                    $query->whereIn('model_type', $modelTypes);
                }
            }

            if ($tenantId !== null && $tenantId !== '' && Schema::hasColumn($table, 'tenant_id')) {
                $query->where(function ($builder) use ($tenantId): void {
                    $builder->where('tenant_id', $tenantId)
                        ->orWhereNull('tenant_id')
                        ->orWhere('tenant_id', '');
                });
            }
        } catch (\Throwable) {
            return true;
        }

        $assignedCount = (clone $query)->count();
        if ($assignedCount === 0) {
            return true;
        }

        return (clone $query)->where($domainColumn, $domainId)->exists();
    }
}
