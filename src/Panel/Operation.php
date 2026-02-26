<?php

namespace Upsoftware\Svarium\Panel;

use Illuminate\Validation\ValidationException;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Enums\TableActionDisplay;
use Upsoftware\Svarium\Http\ComponentResult;
use Upsoftware\Svarium\Http\OperationResult;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\Button;
use Upsoftware\Svarium\UI\Components\FieldComponent;
use Upsoftware\Svarium\UI\Components\Flex;
use Upsoftware\Svarium\UI\Components\Form\Form;

abstract class Operation
{
    /*
    |--------------------------------------------------------------------------
    | Execution modes (backend lifecycle)
    |--------------------------------------------------------------------------
    */
    public function execution(): ExecutionMode
    {
        return ExecutionMode::VIEW;
    }

    /*
    |--------------------------------------------------------------------------
    | Render modes (UI presentation)
    |--------------------------------------------------------------------------
    */
    public const RENDER_PAGE   = 'page';
    public const RENDER_MODAL  = 'modal';
    public const RENDER_DRAWER = 'drawer';

    public static string|array $panels = 'admin';
    public static ?string $layout = null;
    public static ?string $view = 'Svarium';
    protected static array $middleware = [];
    protected ?array $resolvedSchema = null;
    protected ?string $tableActionDisplay = null;

    protected function submitLabel(): string
    {
        return __('Save');
    }

    protected function hasSubmit(): bool
    {
        return true;
    }

    protected function formActions(): array
    {
        return [];
    }

    public static function methods(): array
    {
        return ['GET'];
    }

    public function renderMode(): string
    {
        return 'page';
    }

    protected function resourceBase(): string
    {
        return trim($context->request()->path(), '/');
    }

    protected function submitOptions(): array
    {
        return [
            'save_and_back' => 'Zapisz i wróć',
            'save_and_edit' => 'Zapisz i zostań',
            'save_and_new'  => 'Zapisz i dodaj nową',
        ];
    }

    public function tableActionDisplay(): ?string
    {
        return $this->tableActionDisplay;
    }

    protected function defaultSubmitAction(): string
    {
        return session(
            static::class . '_submit_action',
            array_key_first($this->submitOptions())
        );
    }

    public function rules(): array
    {
        return [];
    }

    public function authorize(PanelContext $context): bool
    {
        return true;
    }

    public static function middleware(): array
    {
        return static::$middleware ?? [];
    }

    protected function hasSchema(): bool
    {
        return method_exists($this, 'schema');
    }

    final public function handle(PanelContext $context, ...$args): OperationResult
    {
        if (! $this->authorize($context)) {
            abort(403);
        }

        $context->setOperationType(
            match ($this->execution()) {
                ExecutionMode::TABLE => 'table',
                ExecutionMode::FORM  => $context->isPost() ? 'save' : 'form',
                ExecutionMode::DUPLICATE => 'duplicate',
                ExecutionMode::ACTION => 'action',
                ExecutionMode::VIEW  => 'view',
                default => 'view',
            }
        );

        return match ($this->execution()) {

            /*
            |--------------------------------------------------------------------------
            | ACTION – brak UI
            |--------------------------------------------------------------------------
            */
            ExecutionMode::ACTION => $this->handleAction($context, ...$args),

            /*
            |--------------------------------------------------------------------------
            | FORM – walidacja + save
            |--------------------------------------------------------------------------
            */
            ExecutionMode::FORM => $this->handleForm($context, ...$args),
            ExecutionMode::DUPLICATE => $this->handleForm($context, ...$args),

            /*
            |--------------------------------------------------------------------------
            | TABLE / TREE / VIEW – render only (na razie)
            |--------------------------------------------------------------------------
            */
            ExecutionMode::TABLE => $this->handleTable($context, ...$args),
            ExecutionMode::TREE,
            ExecutionMode::VIEW => $this->render($context, ...$args),
        };
    }

    protected function table(PanelContext $context): ?TableBuilder
    {
        return null;
    }

    protected function resolveTableActionDisplay(PanelContext $context): TableActionDisplay
    {
        $value =
            $this->tableActionDisplay()
            ?? $context->panel()->tableActionDisplay()
            ?? config('upsoftware.table.action_display', 'inline');

        if ($value instanceof TableActionDisplay) {
            return $value;
        }

        return TableActionDisplay::tryFrom($value)
            ?? throw new \InvalidArgumentException(
                "Invalid table action display config value."
            );
    }

    protected function handleTable(PanelContext $context, ...$args): OperationResult
    {
        $builder = $this->table($context);

        if (! $builder) {
            throw new \RuntimeException(
                static::class.' must implement table() when using EXEC_TABLE.'
            );
        }

        $this->applyTableAccess($builder, $context);

        $query = $builder->getQuery();

        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */
        if ($search = $context->request()->get('search')) {
            $builder->applySearch($query, $search);
        }

        /*
        |--------------------------------------------------------------------------
        | SORT
        |--------------------------------------------------------------------------
        */
        if ($sort = $context->request()->get('sort')) {
            $builder->applySort($query, $sort);
        }

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */
        $requestedRowsPerPage = $context->request()->get('rowsPerPage', $context->request()->get('perPage'));
        $rowsPerPage = $builder->resolveRowsPerPage($requestedRowsPerPage);
        $builder->setResolvedRowsPerPage($rowsPerPage);

        $paginatePerPage = $rowsPerPage;

        if ($rowsPerPage === 0) {
            $totalRows = $query->toBase()->getCountForPagination();
            $paginatePerPage = max(1, $totalRows);
        }

        if ($rowsPerPage === 0) {
            $paginator = $query->paginate($paginatePerPage, ['*'], 'page', 1)->withQueryString();
        } else {
            $paginator = $query->paginate($paginatePerPage)->withQueryString();
        }

        /*
        |--------------------------------------------------------------------------
        | BUILD TABLE COMPONENT
        |--------------------------------------------------------------------------
        */
        $mode = $this->resolveTableActionDisplay($context);

        /*
        |--------------------------------------------------------------------------
        | Ustalamy baseUri bez prefixu panelu
        |--------------------------------------------------------------------------
        */
        $base = '/' . trim($context->panel()->prefixName(), '/') . '/' . $this->resource()::slug();

        $builder->baseUri($base);

        if (! $builder->hasActionDisplay()) {
            $builder->actionDisplay($mode);
        }

        $tableComponent = $builder->build($paginator);

        $result = new ComponentResult($tableComponent);

        $result->meta('pagination', [
            'total' => $paginator->total(),
            'perPage' => $rowsPerPage,
            'rowsPerPage' => $rowsPerPage,
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
        ]);

        return $result;
    }

    protected function applyTableAccess(TableBuilder $builder, PanelContext $context): void
    {
        $accessMap = $this->resolveFieldAccessMap($context);

        if (empty($accessMap)) {
            return;
        }

        $builder->filterColumns(function (string $fieldName) use ($accessMap, $context) {
            return $this->resolveTableColumnVisible($fieldName, $accessMap, $context);
        });
    }

    protected function handleForm(PanelContext $context, ...$args): OperationResult
    {
        if (! $context->isPost()) {
            return $this->render($context, ...$args);
        }

        $schema = $this->getSchema($context, ...$args);
        $schema = $this->filterByOperation($schema, $context);

        $rules = array_merge(
            $this->collectRules($schema),
            $this->rules()
        );

        try {
            $context->validate($rules);
        } catch (ValidationException $e) {

            $result = $this->render($context, ...$args);
            $result->prop('errors', $e->errors());

            return $result;
        }

        $action = $context->input->get('_action');

        if ($action) {
            session()->put(static::class . '_submit_action', $action);
        }

        $result = $this->call('save', $context, ...$args);

        if ($result === null) {
            return $this->render($context, ...$args);
        }

        if (! $result instanceof OperationResult) {
            throw new \RuntimeException(
                static::class . '::save() must return OperationResult.'
            );
        }

        return $result;
    }

    protected function handleAction(PanelContext $context, ...$args): OperationResult
    {
        if (! $context->isPost()) {
            abort(405);
        }

        $result = $this->call('run', $context, ...$args);

        if (! $result instanceof OperationResult) {
            throw new \RuntimeException(
                static::class . '::run() must return OperationResult.'
            );
        }

        return $result;
    }

    public function validationRules(PanelContext $context, ...$args): array
    {
        $schema = $this->getSchema($context, ...$args);
        $schema = $this->filterByOperation($schema, $context);

        return array_merge(
            $this->collectRules($schema),
            $this->rules()
        );
    }

    protected function collectRules(array $schema): array
    {
        $rules = [];

        $walk = function ($components) use (&$rules, &$walk) {
            foreach ($components as $component) {

                if ($component instanceof FieldComponent) {
                    $mode = $component->getProp('_fieldAccessMode', 'edit');

                    if ($mode !== 'edit') {
                        continue;
                    }

                    $componentRules = $component->getValidationRules();

                    if (!empty($componentRules)) {
                        $rules[$component->getName()] = $componentRules;
                    }
                }

                if (!empty($component->children)) {
                    $walk($component->children);
                }

                if (!empty($component->slots)) {
                    foreach ($component->slots as $slot) {
                        $walk($slot);
                    }
                }
            }
        };

        $walk($schema);

        return $rules;
    }

    protected function collectAttributes(array $schema): array
    {
        $attributes = [];

        $walk = function ($components) use (&$attributes, &$walk) {
            foreach ($components as $component) {

                if ($component instanceof FieldComponent) {

                    $name = $component->getName();
                    if (!$name) {
                        continue;
                    }

                    $attribute =
                        $component->getValidationAttribute()
                        ?? $component->getLabel()
                        ?? $name;

                    $attributes[$name] = $attribute;
                }

                foreach ($component->children ?? [] as $child) {
                    $walk([$child]);
                }

                foreach ($component->slots ?? [] as $slot) {
                    $walk($slot);
                }
            }
        };

        $walk($schema);

        return $attributes;
    }

    protected function collectMessages(array $schema): array
    {
        $messages = [];

        $walk = function ($components) use (&$messages, &$walk) {
            foreach ($components as $component) {

                if ($component instanceof FieldComponent) {

                    $name = $component->getName();
                    if (!$name) continue;

                    foreach ($component->getValidationMessages() as $rule => $text) {
                        $messages["{$name}.{$rule}"] = $text;
                    }
                }

                foreach ($component->children ?? [] as $child) {
                    $walk([$child]);
                }

                foreach ($component->slots ?? [] as $slot) {
                    $walk($slot);
                }
            }
        };

        $walk($schema);

        return $messages;
    }

    protected function collectFieldNames(array $schema): array
    {
        $names = [];

        $walk = function ($components) use (&$names, &$walk) {
            foreach ($components as $component) {

                if ($component instanceof \Upsoftware\Svarium\UI\Components\FieldComponent) {
                    $mode = $component->getProp('_fieldAccessMode', 'edit');

                    if ($mode !== 'edit') {
                        continue;
                    }

                    $name = $component->getName();

                    if ($name) {
                        $names[] = $name;
                    }
                }

                if (!empty($component->children)) {
                    $walk($component->children);
                }

                if (!empty($component->slots)) {
                    foreach ($component->slots as $slot) {
                        $walk($slot);
                    }
                }
            }
        };

        $walk($schema);

        return $names;
    }

    public function validationAttributes(PanelContext $context, ...$args): array
    {
        $schema = $this->getSchema($context, ...$args);
        $schema = $this->filterByOperation($schema, $context);

        return $this->collectAttributes($schema);
    }

    public function validationMessages(PanelContext $context, ...$args): array
    {
        $schema = $this->getSchema($context, ...$args);
        $schema = $this->filterByOperation($schema, $context);

        return $this->collectMessages($schema);
    }

    protected function extractModelFromArgs(array $args): ?object
    {
        foreach ($args as $arg) {
            if (is_object($arg) && method_exists($arg, 'getKey')) {
                return $arg;
            }
        }

        return null;
    }

    protected function hydrateFields(array $schema, array $args): void
    {
        $model = $this->extractModelFromArgs($args);

        if (!$model) {
            return;
        }

        $walk = function ($components) use (&$walk, $model) {

            foreach ($components as $component) {

                if ($component instanceof FieldComponent) {

                    $name = $component->getName();
                    $value = data_get($model, $name);

                    if ($value !== null) {
                        $component->value($value);
                    }
                }

                if (!empty($component->children)) {
                    $walk($component->children);
                }

                if (!empty($component->slots)) {
                    foreach ($component->slots as $slot) {
                        $walk($slot);
                    }
                }
            }
        };

        $walk($schema);
    }

    protected function getSchema(PanelContext $context, ...$args): array
    {
        if ($this->resolvedSchema !== null) {
            return $this->resolvedSchema;
        }

        if (!method_exists($this, 'schema')) {
            return $this->resolvedSchema = [];
        }

        $schema = $this->call('schema', $context, ...$args);

        if ($schema === null) {
            return $this->resolvedSchema = [];
        }

        return $this->resolvedSchema = is_array($schema) ? $schema : [$schema];
    }

    protected function resolveFieldAccessMap(PanelContext $context): array
    {
        if (! method_exists($this, 'resource')) {
            return [];
        }

        try {
            $resource = $this->resource();
        } catch (\Throwable) {
            return [];
        }

        if (! $resource instanceof Resource) {
            return [];
        }

        $access = $resource->access();

        return is_array($access) ? $access : [];
    }

    protected function resolveFieldMode(?string $fieldName, array $accessMap, PanelContext $context): string
    {
        if (! $fieldName) {
            return 'edit';
        }

        if (! array_key_exists($fieldName, $accessMap)) {
            return 'edit';
        }

        $definition = $accessMap[$fieldName];

        if (! is_array($definition)) {
            return $this->resolveDefaultFieldMode($definition, $context);
        }

        // If the field contains only table-specific rules, do not alter form behavior.
        if (! $this->isModeDefinition($definition)) {
            return 'edit';
        }

        return $this->resolveModeFromDefinition($definition, $context);
    }

    protected function resolveTableColumnVisible(?string $fieldName, array $accessMap, PanelContext $context): bool
    {
        if (! $fieldName) {
            return true;
        }

        if (! array_key_exists($fieldName, $accessMap)) {
            return true;
        }

        $definition = $accessMap[$fieldName];

        if (is_array($definition) && array_key_exists('table', $definition)) {
            $definition = $definition['table'];
        }

        return $this->resolveTableVisibility($definition, $context);
    }

    protected function resolveTableVisibility(mixed $definition, PanelContext $context): bool
    {
        if ($definition === null) {
            return true;
        }

        if (is_bool($definition)) {
            return $definition;
        }

        if (is_string($definition)) {
            $normalized = strtolower(trim($definition));

            if (in_array($normalized, ['hidden', 'none', 'false', '0'], true)) {
                return false;
            }

            if (in_array($normalized, ['view', 'edit', 'visible', 'true', '1'], true)) {
                return true;
            }

            return $this->checkFieldAccessRule($definition, $context);
        }

        if (is_array($definition) && $this->isModeDefinition($definition)) {
            return $this->resolveModeFromDefinition($definition, $context) !== 'hidden';
        }

        return $this->checkFieldAccessRule($definition, $context);
    }

    protected function isModeDefinition(array $definition): bool
    {
        return array_key_exists('edit', $definition)
            || array_key_exists('view', $definition)
            || array_key_exists('default', $definition);
    }

    protected function resolveModeFromDefinition(array $definition, PanelContext $context): string
    {
        if ($this->checkFieldAccessRule($definition['edit'] ?? null, $context)) {
            return 'edit';
        }

        if ($this->checkFieldAccessRule($definition['view'] ?? null, $context)) {
            return 'view';
        }

        return $this->resolveDefaultFieldMode($definition['default'] ?? 'hidden', $context);
    }

    protected function resolveDefaultFieldMode(mixed $default, PanelContext $context): string
    {
        if (is_string($default)) {
            $normalized = strtolower(trim($default));

            if (in_array($normalized, ['edit', 'view', 'hidden'], true)) {
                return $normalized;
            }
        }

        return $this->checkFieldAccessRule($default, $context) ? 'view' : 'hidden';
    }

    protected function checkFieldAccessRule(mixed $rule, PanelContext $context): bool
    {
        if ($rule === true) {
            return true;
        }

        if ($rule === false || $rule === null) {
            return false;
        }

        if (is_string($rule)) {
            return $this->checkFieldAccessToken($rule, $context);
        }

        if (! is_array($rule)) {
            return false;
        }

        if (isset($rule['any']) && is_array($rule['any'])) {
            foreach ($rule['any'] as $token) {
                if ($this->checkFieldAccessRule($token, $context)) {
                    return true;
                }
            }

            return false;
        }

        if (isset($rule['all']) && is_array($rule['all'])) {
            foreach ($rule['all'] as $token) {
                if (! $this->checkFieldAccessRule($token, $context)) {
                    return false;
                }
            }

            return ! empty($rule['all']);
        }

        foreach ($rule as $token) {
            if ($this->checkFieldAccessRule($token, $context)) {
                return true;
            }
        }

        return false;
    }

    protected function checkFieldAccessToken(string $token, PanelContext $context): bool
    {
        $token = trim($token);

        if ($token === '') {
            return false;
        }

        $user = $this->resolvePanelUser($context);

        if (! str_contains($token, ':')) {
            return $this->userHasPermission($user, $token);
        }

        [$prefix, $value] = explode(':', $token, 2);
        $prefix = strtolower(trim($prefix));
        $value = trim($value);

        return match ($prefix) {
            'perm', 'permission' => $this->userHasPermission($user, $value),
            'role' => $this->userHasRole($user, $value),
            'user' => $this->userMatches($user, $value),
            'group' => $this->userHasGroup($user, $value),
            default => $this->userHasPermission($user, $token),
        };
    }

    protected function resolvePanelUser(PanelContext $context): ?object
    {
        $user = $context->request()->user();

        if ($user) {
            return $user;
        }

        if (function_exists('auth')) {
            return auth()->user();
        }

        return null;
    }

    protected function userHasPermission(?object $user, string $permission): bool
    {
        if (! $user || $permission === '') {
            return false;
        }

        if (method_exists($user, 'can')) {
            try {
                return (bool) $user->can($permission);
            } catch (\Throwable) {
                return false;
            }
        }

        if (method_exists($user, 'hasPermissionTo')) {
            try {
                return (bool) $user->hasPermissionTo($permission);
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    protected function userHasRole(?object $user, string $role): bool
    {
        if (! $user || $role === '') {
            return false;
        }

        if (method_exists($user, 'hasRole')) {
            try {
                if ($user->hasRole($role)) {
                    return true;
                }
            } catch (\Throwable) {
                // fallback below
            }
        }

        if (method_exists($user, 'roles')) {
            try {
                $roles = $user->roles;

                if (is_object($roles) && method_exists($roles, 'contains')) {
                    if (is_numeric($role) && $roles->contains('id', (int) $role)) {
                        return true;
                    }

                    if ($roles->contains('name', $role)) {
                        return true;
                    }
                }
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    protected function userMatches(?object $user, string $value): bool
    {
        if (! $user || $value === '') {
            return false;
        }

        $id = null;

        if (method_exists($user, 'getAuthIdentifier')) {
            $id = $user->getAuthIdentifier();
        } elseif (method_exists($user, 'getKey')) {
            $id = $user->getKey();
        }

        if ($id !== null && (string) $id === $value) {
            return true;
        }

        foreach (['name', 'username', 'email'] as $attribute) {
            if (isset($user->{$attribute}) && (string) $user->{$attribute} === $value) {
                return true;
            }
        }

        // Compatibility shortcut: user:group_name
        return $this->userHasGroup($user, $value);
    }

    protected function userHasGroup(?object $user, string $group): bool
    {
        if (! $user || $group === '') {
            return false;
        }

        foreach (['hasGroup', 'inGroup', 'hasAnyGroup', 'inAnyGroup'] as $method) {
            if (! method_exists($user, $method)) {
                continue;
            }

            try {
                if ((bool) $user->{$method}($group)) {
                    return true;
                }
            } catch (\Throwable) {
                // fallback below
            }
        }

        if (method_exists($user, 'groups')) {
            try {
                $groups = $user->groups;

                if (is_object($groups) && method_exists($groups, 'contains')) {
                    if (is_numeric($group) && $groups->contains('id', (int) $group)) {
                        return true;
                    }

                    if ($groups->contains('name', $group)) {
                        return true;
                    }
                }
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    protected function filterByOperation(array $components, PanelContext $context, ?array $accessMap = null): array
    {
        $accessMap ??= $this->resolveFieldAccessMap($context);
        $filtered = [];

        foreach ($components as $component) {
            if (! is_object($component)) {
                continue;
            }

            $onlyOn = method_exists($component, 'getOnlyOn') ? $component->getOnlyOn() : null;
            $exceptOn = method_exists($component, 'getExceptOn') ? $component->getExceptOn() : null;

            if ($onlyOn !== null && !in_array($context->operationType(), $onlyOn)) {
                continue;
            }

            if ($exceptOn !== null && in_array($context->operationType(), $exceptOn)) {
                continue;
            }

            if ($component instanceof FieldComponent) {
                $mode = $this->resolveFieldMode($component->getName(), $accessMap, $context);
                $component->prop('_fieldAccessMode', $mode);

                if ($mode === 'hidden') {
                    continue;
                }

                if ($mode === 'view') {
                    $component->prop('readonly', true);
                    $component->prop('disabled', true);
                }
            }

            if (!empty($component->children)) {
                $component->children = $this->filterByOperation(
                    $component->children,
                    $context,
                    $accessMap
                );
            }

            if (!empty($component->slots)) {
                foreach ($component->slots as $key => $slot) {
                    $component->slots[$key] = $this->filterByOperation(
                        $slot,
                        $context,
                        $accessMap
                    );
                }
            }

            $filtered[] = $component;
        }

        return $filtered;
    }

    protected function isFormLike(): bool
    {
        return in_array(
            $this->execution(),
            [ExecutionMode::FORM, ExecutionMode::DUPLICATE]
        );
    }

    protected function render(PanelContext $context, ...$args): ComponentResult
    {
        if (!method_exists($this, 'schema')) {
            abort(204);
        }

        $schema = $this->getSchema($context, ...$args);
        $schema = $this->filterByOperation($schema, $context);

        $this->hydrateFields($schema, $args, $context);

        if ($this->isFormLike()) {

            $actions = $this->formActions();

            if ($this->hasSubmit()) {

                $actions[] = Button::make(
                    $this->submitOptions()[$this->defaultSubmitAction()]
                )
                    ->type('submit')
                    ->name('_action')
                    ->value($this->defaultSubmitAction())
                    ->prop('options', $this->submitOptions())
                    ->prop('active', $this->defaultSubmitAction());
            }

            $schema = Form::make()
                ->method('POST')
                ->content($schema)
                ->footer($actions);
        }

        $result = new ComponentResult(
            Flex::make()->content($schema),
            static::$layout
        );

        $result->meta('renderMode', $this->renderMode());

        if (method_exists($this, 'title')) {
            $result->meta('title', $this->call('title', $context, ...$args));
        }

        if (method_exists($this, 'breadcrumbs')) {
            $result->meta('breadcrumbs', $this->call('breadcrumbs', $context, ...$args));
        }

        return $result;
    }

    protected function call(string $method, PanelContext $context, ...$routeArgs)
    {
        $ref = new \ReflectionMethod($this, $method);
        $params = [];

        foreach ($ref->getParameters() as $parameter) {

            $type = $parameter->getType()?->getName();

            if ($type === PanelContext::class) {
                $params[] = $context;
                continue;
            }

            foreach ($routeArgs as $arg) {
                if ($type && $arg instanceof $type) {
                    $params[] = $arg;
                    continue 2;
                }
            }

            $params[] = $type ? app($type) : null;
        }

        return $this->$method(...$params);
    }
}
