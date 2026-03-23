<?php

namespace Upsoftware\Svarium\UI\Components;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Upsoftware\Svarium\Services\NavigationService;
use Upsoftware\Svarium\UI\Component;

class SidebarUser extends Component
{
    public function user(mixed $user): static
    {
        if ($user instanceof \Illuminate\Contracts\Support\Arrayable) {
            $user = $user->toArray();
        } elseif (is_object($user) && method_exists($user, 'toArray')) {
            $user = $user->toArray();
        }

        return $this->prop('user', is_array($user) ? $user : null);
    }

    public function name(string $name): static
    {
        return $this->prop('name', $name);
    }

    public function email(string $email): static
    {
        return $this->prop('email', $email);
    }

    public function avatar(string $avatar): static
    {
        return $this->prop('avatar', $avatar);
    }

    public function themeToggle(bool $enabled = true): static
    {
        return $this->prop('themeToggle', $enabled);
    }

    public function locale(bool $enabled = true): static
    {
        return $this->prop('locale', $enabled);
    }

    public function twoFactor(bool $enabled = true): static
    {
        return $this->prop('twoFactor', $enabled);
    }

    public function activityLog(bool $enabled = true): static
    {
        return $this->prop('activityLog', $enabled);
    }

    public function logout(bool $enabled = true): static
    {
        return $this->prop('logout', $enabled);
    }

    public function menu(bool $enabled = true): static
    {
        return $this->prop('menu', $enabled);
    }

    public function menuItems(array $items): static
    {
        return $this->prop('menuItems', $items);
    }

    public function menuNavigationId(string|int $navigationId): static
    {
        return $this->prop('menuNavigationId', $navigationId);
    }

    public function roles(bool $enabled = true): static
    {
        return $this->prop('rolesEnabled', $enabled);
    }

    public function debugRole(bool $enabled = true): static
    {
        return $this->prop('debugRole', $enabled);
    }

    public function toArray(): array
    {
        $array = parent::toArray();
        $props = $array['props'] ?? [];

        if (! is_array($props)) {
            $props = [];
        }

        $authUserObject = $this->resolveAuthUserObject();
        $authUser = $this->resolveAuthUserPayload($authUserObject);
        $resolvedRoleItems = $authUserObject instanceof Model
            ? $this->resolveRoleItems($authUserObject)
            : [];

        if (is_array($authUser)) {
            $authUser['roles'] = $this->formatRoleItemsForUserPayload($resolvedRoleItems);
        }

        if ($authUser !== null && ! array_key_exists('user', $props)) {
            $props['user'] = $authUser;
        } elseif (is_array($props['user'] ?? null) && ! array_key_exists('roles', $props['user'])) {
            $props['user']['roles'] = $this->formatRoleItemsForUserPayload($resolvedRoleItems);
        }

        if ($authUser !== null && ! array_key_exists('name', $props)) {
            $props['name'] = (string) ($authUser['name'] ?? '');
        }

        if ($authUser !== null && ! array_key_exists('email', $props)) {
            $props['email'] = (string) ($authUser['email'] ?? '');
        }

        if ($authUser !== null && ! array_key_exists('avatar', $props)) {
            $avatar = trim((string) ($authUser['avatar'] ?? ''));

            if ($avatar !== '') {
                $props['avatar'] = $avatar;
            }
        }

        if (! array_key_exists('menu', $props)) {
            $props['menu'] = (bool) config('upsoftware.ui.sidebar_user.menu_enabled', true);
        }

        if (($props['menu'] ?? false) === true && ! array_key_exists('menuItems', $props)) {
            $navigationId = $props['menuNavigationId']
                ?? config('upsoftware.ui.sidebar_user.menu_navigation_id', 'sidebar_user');

            $props['menuNavigationId'] = $navigationId;
            $props['menuItems'] = $this->resolveMenuItems(
                is_string($navigationId) || is_int($navigationId) ? $navigationId : null
            );
        }

        if (! array_key_exists('rolesEnabled', $props)) {
            $props['rolesEnabled'] = (bool) config('upsoftware.ui.sidebar_user.roles_enabled', true);
        }

        if (! array_key_exists('debugRole', $props)) {
            $props['debugRole'] = function_exists('debug_role')
                ? (bool) debug_role()
                : (bool) config('upsoftware.ui.sidebar_user.debug_role', false);
        }

        if (($props['rolesEnabled'] ?? false) === true && ! array_key_exists('roleItems', $props)) {
            $roleItems = $resolvedRoleItems;
            $props['roleItems'] = $roleItems;

            if (! array_key_exists('activeRoleId', $props)) {
                $props['activeRoleId'] = $this->resolveActiveRoleId($roleItems);
            }

            if (! array_key_exists('roleSwitchUrl', $props)) {
                $props['roleSwitchUrl'] = $this->resolveRoleSwitchUrl();
            }
        }

        if (($props['debugRole'] ?? false) === true && ! array_key_exists('roleDebug', $props)) {
            $props['roleDebug'] = $this->resolveRoleDebugPayload($authUserObject, $resolvedRoleItems);
        }

        $array['props'] = $props;

        return $array;
    }

    protected function resolveAuthUserObject(): ?Model
    {
        try {
            if (! function_exists('auth') || ! auth()->check()) {
                return null;
            }

            $user = auth()->user();

            return $user instanceof Model ? $user : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function resolveAuthUserPayload(?Model $user): ?array
    {
        try {
            if (! $user) {
                return null;
            }

            $firstName = trim((string) $this->attributeValue($user, 'first_name'));
            $lastName = trim((string) $this->attributeValue($user, 'last_name'));

            $name = trim((string) $this->attributeValue($user, 'name'));
            if ($name === '') {
                $name = trim($firstName.' '.$lastName);
            }

            $email = trim((string) $this->attributeValue($user, 'email'));
            $avatar = trim((string) (
                $this->attributeValue($user, 'avatar_url')
                ?: $this->attributeValue($user, 'avatar')
                ?: $this->attributeValue($user, 'photo')
                ?: $this->attributeValue($user, 'profile_photo_url')
            ));

            return [
                'id' => $this->attributeValue($user, 'id'),
                'name' => $name,
                'email' => $email,
                'avatar' => $avatar,
                'first_name' => $firstName,
                'last_name' => $lastName,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array{id:int,label:string,key:string}>
     */
    protected function resolveRoleItems(?Model $user): array
    {
        if (! $user) {
            return [];
        }

        if (function_exists('get_roles')) {
            $resolved = get_roles($user);
            if (is_array($resolved) && $resolved !== []) {
                $items = [];

                foreach ($resolved as $role) {
                    if (! is_array($role)) {
                        continue;
                    }

                    $id = (int) ($role['id'] ?? 0);
                    if ($id <= 0) {
                        continue;
                    }

                    $items[] = [
                        'id' => $id,
                        'label' => trim((string) ($role['name_locale'] ?? $role['name'] ?? '#'.$id)),
                        'key' => trim((string) ($role['role_key'] ?? '')),
                    ];
                }

                if ($items !== []) {
                    return $items;
                }
            }
        }

        $roleIds = $this->resolveRoleIdsFromPivotTable($user);

        if ($roleIds === [] && method_exists($user, 'roles')) {
            try {
                $roleIds = $user->roles()
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->filter(static fn (int $id): bool => $id > 0)
                    ->unique()
                    ->values()
                    ->all();
            } catch (Throwable) {
                $roleIds = [];
            }
        }

        if ($roleIds === []) {
            return [];
        }

        $roleModelClass = (string) get_model('role');
        if ($roleModelClass === '' || ! class_exists($roleModelClass)) {
            $roleModelClass = (string) config('permission.models.role', \Spatie\Permission\Models\Role::class);
        }
        if ($roleModelClass === '' || ! class_exists($roleModelClass)) {
            return [];
        }

        try {
            $roles = $roleModelClass::query()
                ->whereIn('id', $roleIds)
                ->get()
                ->keyBy('id');
        } catch (Throwable) {
            return [];
        }

        $items = [];

        foreach ($roleIds as $roleId) {
            $role = $roles->get($roleId);
            if (! $role) {
                continue;
            }

            $label = $this->resolveRoleLabel($role);
            $key = trim((string) $role->getAttribute('role_key'));

            $items[] = [
                'id' => (int) $roleId,
                'label' => $label !== '' ? $label : ('#'.$roleId),
                'key' => $key,
            ];
        }

        return $items;
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
        } catch (Throwable) {
            return [];
        }

        $hasModelType = Schema::hasColumn($table, 'model_type');
        $hasStatus = Schema::hasColumn($table, 'status');

        $modelHasRoleClass = (string) get_model('model_has_role');
        $query = is_string($modelHasRoleClass) && $modelHasRoleClass !== '' && class_exists($modelHasRoleClass)
            ? $modelHasRoleClass::query()->from($table)->where('model_id', $user->getKey())
            : DB::table($table)->where('model_id', $user->getKey());

        if ($hasModelType) {
            $candidates = $this->resolveUserModelTypeCandidates($user);
            if ($candidates !== []) {
                $query->whereIn('model_type', $candidates);
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

    protected function resolveRoleLabel(object $role): string
    {
        $locale = (string) app()->getLocale();
        $fallbackLocale = (string) config('app.fallback_locale', 'en');

        if (method_exists($role, 'getTranslation')) {
            try {
                $translated = (string) $role->getTranslation('name', $locale, false);
                if (trim($translated) !== '') {
                    return trim($translated);
                }

                $fallback = (string) $role->getTranslation('name', $fallbackLocale, false);
                if (trim($fallback) !== '') {
                    return trim($fallback);
                }
            } catch (Throwable) {
                // ignore and continue with fallback resolution
            }
        }

        $name = $role->getAttribute('name');

        if (is_string($name)) {
            $decoded = json_decode($name, true);
            if (is_array($decoded)) {
                $fromLocale = trim((string) ($decoded[$locale] ?? ''));
                if ($fromLocale !== '') {
                    return $fromLocale;
                }

                $fromFallback = trim((string) ($decoded[$fallbackLocale] ?? ''));
                if ($fromFallback !== '') {
                    return $fromFallback;
                }

                $first = reset($decoded);
                $firstString = trim((string) ($first ?? ''));
                if ($firstString !== '') {
                    return $firstString;
                }
            }

            $trimmed = trim($name);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        $localeName = trim((string) $role->getAttribute('name_locale'));
        if ($localeName !== '') {
            return $localeName;
        }

        $roleKey = trim((string) $role->getAttribute('role_key'));
        if ($roleKey !== '') {
            return $roleKey;
        }

        return (string) $role->getAttribute('id');
    }

    protected function resolveActiveRoleId(array $roleItems): ?int
    {
        if ($roleItems === []) {
            return null;
        }

        $ids = array_values(array_filter(array_map(
            static fn (array $item): int => (int) ($item['id'] ?? 0),
            $roleItems
        ), static fn (int $id): bool => $id > 0));

        if ($ids === []) {
            return null;
        }

        $active = (int) session('svarium.active_role_id', session('role_id', 0));
        if ($active > 0 && in_array($active, $ids, true)) {
            return $active;
        }

        return $ids[0];
    }

    protected function resolveRoleSwitchUrl(): ?string
    {
        try {
            $panelName = null;

            if (function_exists('svarium_resolve_panel')) {
                $resolvedPanel = svarium_resolve_panel(null, request());
                if ($resolvedPanel instanceof \Upsoftware\Svarium\Panel\Panel) {
                    $candidate = trim((string) ($resolvedPanel->name ?? ''));
                    if ($candidate !== '') {
                        $panelName = $candidate;
                    }
                }
            }

            return route_panel('role.switch', [], false, $panelName);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<int, array{id:int,label:string,key:string}> $roleItems
     * @return array<string, mixed>
     */
    protected function resolveRoleDebugPayload(?Model $user, array $roleItems): array
    {
        $routeName = trim((string) optional(request()?->route())->getName());
        $path = trim((string) request()?->path());

        $panelName = null;
        if (function_exists('svarium_resolve_panel')) {
            $resolvedPanel = svarium_resolve_panel(null, request());
            if ($resolvedPanel instanceof \Upsoftware\Svarium\Panel\Panel) {
                $candidate = trim((string) ($resolvedPanel->name ?? ''));
                if ($candidate !== '') {
                    $panelName = $candidate;
                }
            }
        }

        $activeRoleIdSession = (int) session('svarium.active_role_id', 0);
        $legacyRoleIdSession = (int) session('role_id', 0);
        $helperRoleId = function_exists('get_role_id') ? (int) (get_role_id($user) ?? 0) : 0;
        $helperRole = function_exists('get_role') ? get_role($user) : null;

        return [
            'panel' => $panelName,
            'route_name' => $routeName,
            'path' => $path,
            'switch_url' => $this->resolveRoleSwitchUrl(),
            'user_id' => $user?->getKey(),
            'session' => [
                'svarium.active_role_id' => $activeRoleIdSession,
                'role_id' => $legacyRoleIdSession,
            ],
            'helper' => [
                'get_role_id' => $helperRoleId,
                'get_role' => is_array($helperRole) ? $helperRole : null,
            ],
            'last_switch' => svarium_session_get('svarium.debug_role_last_switch', null),
            'role_items' => $roleItems,
            'active_role_id_from_items' => $this->resolveActiveRoleId($roleItems),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function resolveMenuItems(string|int|null $navigationId): array
    {
        try {
            $tree = NavigationService::make()->getRegisteredTree($navigationId);
            $nodes = is_array($tree['children'] ?? null) ? $tree['children'] : [];
        } catch (Throwable) {
            return [];
        }

        $resolved = $this->flattenMenuNodes($nodes);

        usort($resolved, static function (array $left, array $right): int {
            $leftOrder = (int) ($left['order'] ?? 0);
            $rightOrder = (int) ($right['order'] ?? 0);

            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return $resolved;
    }

    /**
     * @param array<int, mixed> $nodes
     * @param array<int, string> $path
     * @return array<int, array<string, mixed>>
     */
    protected function flattenMenuNodes(array $nodes, array $path = []): array
    {
        $resolved = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $type = strtolower(trim((string) ($node['type'] ?? 'item')));
            $label = trim((string) ($node['label'] ?? ''));
            $url = trim((string) ($node['url'] ?? ''));
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];

            $icon = '';
            if (is_array($node['icon'] ?? null)) {
                $icon = trim((string) ($node['icon']['value'] ?? ''));
            } else {
                $icon = trim((string) ($node['icon'] ?? ''));
            }

            if ($type === 'item' && $label !== '' && $url !== '') {
                $resolved[] = [
                    'key' => trim((string) ($node['__menu_key'] ?? '')) !== ''
                        ? (string) $node['__menu_key']
                        : sha1($label.'|'.$url),
                    'label' => $label,
                    'path' => $path,
                    'icon' => $icon,
                    'url' => $url,
                    'order' => (int) ($node['order'] ?? 0),
                ];
            }

            if ($children === []) {
                continue;
            }

            if ($type === 'separator') {
                continue;
            }

            $nextPath = $path;
            if ($label !== '' && $url === '') {
                $nextPath[] = $label;
            }

            $resolved = [
                ...$resolved,
                ...$this->flattenMenuNodes($children, $nextPath),
            ];
        }

        return $resolved;
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
        } catch (Throwable) {
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
        } catch (Throwable) {
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
        } catch (Throwable) {
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
        } catch (Throwable) {
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
        } catch (Throwable) {
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
        } catch (Throwable) {
            return true;
        }

        $assignedCount = (clone $query)->count();
        if ($assignedCount === 0) {
            // No explicit domain restrictions for this user.
            return true;
        }

        return (clone $query)->where($domainColumn, $domainId)->exists();
    }

    /**
     * @param array<int, array{id:int,label:string,key:string}> $roleItems
     * @return array<int, array{id:int,name:string,name_locale:string,key:string}>
     */
    protected function formatRoleItemsForUserPayload(array $roleItems): array
    {
        $formatted = [];

        foreach ($roleItems as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            $key = trim((string) ($item['key'] ?? ''));

            $formatted[] = [
                'id' => $id,
                'name' => $label,
                'name_locale' => $label,
                'key' => $key,
            ];
        }

        return $formatted;
    }

    protected function resolveMenuItemUrl(array $item): ?string
    {
        $routeName = trim((string) ($item['route_name'] ?? ''));
        if ($routeName !== '' && Route::has($routeName)) {
            try {
                return route($routeName, [], false);
            } catch (Throwable) {
                // ignore invalid route parameters
            }
        }

        $url = trim((string) ($item['url'] ?? ''));
        if ($url !== '') {
            return $url;
        }

        return null;
    }

    protected function attributeValue(mixed $user, string $key): mixed
    {
        if (is_object($user)) {
            if (method_exists($user, 'getAttribute')) {
                return $user->getAttribute($key);
            }

            if (isset($user->{$key})) {
                return $user->{$key};
            }
        }

        if (is_array($user)) {
            return $user[$key] ?? null;
        }

        return null;
    }
}
