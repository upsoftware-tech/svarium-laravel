<?php

namespace Upsoftware\Svarium\Panel;

use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Enums\TableActionDisplay;
use Upsoftware\Svarium\Http\ComponentResult;
use Upsoftware\Svarium\Http\OperationResult;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\Button;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\FieldComponent;
use Upsoftware\Svarium\UI\Components\Form\Form;
use Upsoftware\Svarium\Support\PermissionMatcher;
use Upsoftware\Svarium\Support\ShowWhenEvaluator;
use Upsoftware\Svarium\Widgets\WidgetRegistry;

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

    protected function layoutProps(PanelContext $context, ...$args): array
    {
        return [];
    }

    protected function layoutSlots(PanelContext $context, ...$args): array
    {
        return [];
    }

    public static function methods(): array
    {
        return ['GET'];
    }

    /**
     * Enable automatic API registration for this operation.
     *
     * Supported values:
     * - false (default): no API route.
     * - true: API route = "{api.prefix}/{uri()}", methods = methods().
     * - array:
     *   [
     *     'enabled' => true,
     *     'uri' => 'pages',
     *     'methods' => ['GET'],
     *     'prefix' => true, // prepend upsoftware.api.prefix
     *     'middleware' => ['auth:sanctum'], // optional route middleware override
     *   ]
     */
    public static function api(): bool|array
    {
        return false;
    }

    /**
     * Optional OpenAPI summary override used by `svarium:api.docs`.
     * Applies to this operation class when route-level docs are not provided.
     */
    public static function apiSummary(): ?string
    {
        return null;
    }

    /**
     * Optional OpenAPI description override used by `svarium:api.docs`.
     * Applies to this operation class when route-level docs are not provided.
     */
    public static function apiDescription(): ?string
    {
        return null;
    }

    /**
     * Optional dedicated API handler.
     *
     * Return one of:
     * - OperationResult (recommended: JsonResult)
     * - array (auto-converted to JSON 200)
     * - Symfony Response
     * - null (fallback to default handle())
     */
    public function apiRun(PanelContext $context, ...$args): mixed
    {
        return null;
    }

    /**
     * Optional operation route suffix used for named route aliases.
     *
     * Example:
     * - module: ksef
     * - routeName(): documents.import
     * Final named routes:
     * - module:ksef.documents.import
     * - module:ksef.documents.import.get / .post (method aliases)
     */
    public static function routeName(): ?string
    {
        return null;
    }

    public static function menu(): array
    {
        return [];
    }

    public static function widgets(): array
    {
        return [];
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

    protected function resolvedSubmitOptions(PanelContext $context): array
    {
        $options = $this->submitOptions();

        if (! is_array($options)) {
            $options = [];
        }

        $normalized = [];
        foreach ($options as $key => $label) {
            $optionKey = trim((string) $key);
            if ($optionKey === '') {
                continue;
            }

            $normalized[$optionKey] = trim((string) $label);
        }

        if ($normalized === []) {
            $normalized = [
                'save_and_back' => 'Zapisz i wróć',
            ];
        }

        $formSubmitLabel = trim((string) ($context->request()->attributes->get(\Upsoftware\Svarium\Panel\Form\Form::REQUEST_SUBMIT_LABEL_KEY, '') ?? ''));
        if ($formSubmitLabel !== '' && array_key_exists('save_and_back', $normalized)) {
            $normalized['save_and_back'] = $formSubmitLabel;
        }

        return $normalized;
    }

    protected function resolveActiveSubmitAction(array $options): string
    {
        $default = session(
            static::class . '_submit_action',
            array_key_first($options)
        );

        $candidate = trim((string) $default);
        if ($candidate !== '' && array_key_exists($candidate, $options)) {
            return $candidate;
        }

        return (string) array_key_first($options);
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
        $permission = app(\Upsoftware\Svarium\Roles\RolePermissionCatalog::class)
            ->operationPermissionName(static::class, method_exists(static::class, 'uri') ? (string) static::uri() : null);

        if ($permission === null) {
            return true;
        }

        $user = $this->resolvePanelUser($context);
        if (! is_object($user)) {
            return false;
        }

        if ($this->userHasRole($user, 'superadmin')) {
            return true;
        }

        return $this->userHasPermission($user, $permission);
    }

    public function delegatedAuthorize(PanelContext $context): bool
    {
        return $this->authorize($context);
    }

    public function delegatedSchema(PanelContext $context, ...$args): array
    {
        return $this->getSchema($context, ...$args);
    }

    public function delegatedValidationRules(PanelContext $context, ...$args): array
    {
        return $this->validationRules($context, ...$args);
    }

    public function delegatedValidationAttributes(PanelContext $context, ...$args): array
    {
        return $this->validationAttributes($context, ...$args);
    }

    public function delegatedValidationMessages(PanelContext $context, ...$args): array
    {
        return $this->validationMessages($context, ...$args);
    }

    public function delegatedSave(PanelContext $context, ...$args): ?OperationResult
    {
        if (! method_exists($this, 'save')) {
            return null;
        }

        $result = $this->call('save', $context, ...$args);

        if ($result === null) {
            return null;
        }

        if (! $result instanceof OperationResult) {
            throw new \RuntimeException(
                static::class . '::save() must return OperationResult.'
            );
        }

        return $result;
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
        $builder->applyAutoWithFromColumns($query);

        /*
        |--------------------------------------------------------------------------
        | SAVED VIEW (TABLE TAB)
        |--------------------------------------------------------------------------
        */
        $requestedView = trim((string) $context->request()->get('view', ''));
        if ($requestedView !== '') {
            $builder->applySavedView(
                $query,
                $requestedView,
                ! $context->request()->filled('sort')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */
        $search = $context->request()->get('q');

        if (! is_string($search) || trim($search) === '') {
            $search = $context->request()->get('search');
        }

        if (is_string($search) && trim($search) !== '') {
            $builder->applySearch($query, $search);
        }

        /*
        |--------------------------------------------------------------------------
        | DROPDOWN SEARCH FILTERS
        |--------------------------------------------------------------------------
        */
        $builder->applyDropdownSearchFilters($query, $context->request());

        /*
        |--------------------------------------------------------------------------
        | SORT
        |--------------------------------------------------------------------------
        */
        if ($sort = $context->request()->get('sort')) {
            $builder->applySort($query, $sort);
        } else {
            $builder->applyDefaultSort($query);
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

                    $name = $component->getName();
                    if (! is_string($name) || trim($name) === '') {
                        continue;
                    }

                    $componentRules = $component->getValidationRules();
                    $showWhen = $component->getProp('showWhen');

                    if ($showWhen !== null) {
                        array_unshift($componentRules, Rule::excludeIf(
                            fn () => ! ShowWhenEvaluator::matches($showWhen, request()->all())
                        ));
                    }

                    if (!empty($componentRules)) {
                        $ruleKey = $this->isLanguageFieldComponent($component)
                            ? $name.'.*'
                            : $name;

                        $rules[$ruleKey] = $componentRules;
                    }
                }

                $children = $this->getComponentChildren($component);
                if ($children !== []) {
                    $walk($children);
                }

                $slots = $this->getComponentSlots($component);
                if ($slots !== []) {
                    foreach ($slots as $slot) {
                        $walk($this->normalizeComponentNodes($slot));
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

                    $attribute = $this->resolveValidationAttributeLabel($component, $name);

                    if ($this->isLanguageFieldComponent($component)) {
                        // Keep both wildcard and base key to avoid Laravel fallback
                        // to generic attributes (e.g. "name" => "Name") for name.pl.
                        $attributes[$name] = $attribute;
                        $attributes[$name.'.*'] = $attribute;

                        $requestLocalizedValues = request()->input($name);
                        if (is_array($requestLocalizedValues)) {
                            foreach (array_keys($requestLocalizedValues) as $localeKey) {
                                $normalizedLocaleKey = trim((string) $localeKey);
                                if ($normalizedLocaleKey === '') {
                                    continue;
                                }

                                $attributes[$name.'.'.$normalizedLocaleKey] = $attribute;
                            }
                        }
                    } else {
                        $attributes[$name] = $attribute;
                    }
                }

                foreach ($this->getComponentChildren($component) as $child) {
                    $walk([$child]);
                }

                foreach ($this->getComponentSlots($component) as $slot) {
                    $walk($this->normalizeComponentNodes($slot));
                }
            }
        };

        $walk($schema);

        return $attributes;
    }

    protected function resolveValidationAttributeLabel(FieldComponent $component, string $name): string
    {
        $explicitAttribute = trim((string) ($component->getValidationAttribute() ?? ''));
        if ($explicitAttribute !== '') {
            return $this->translateAttributeToken($explicitAttribute);
        }

        $explicitLabel = trim((string) ($component->getLabel() ?? ''));
        if ($explicitLabel !== '') {
            return $this->translateAttributeToken($explicitLabel);
        }

        $normalized = trim(str_replace(['.', '_', '-'], ' ', $name));
        if ($normalized === '') {
            return $name;
        }

        $headline = (string) str($normalized)->headline()->trim();

        if ($headline === '') {
            return $name;
        }

        $locales = $this->resolveValidationAttributeLocales();
        $baseName = trim((string) str($name)->before('.')->before('[')->snake());

        if ($baseName !== '') {
            $validationKeys = array_values(array_unique(array_filter([
                'validation.attributes.'.$baseName,
                $baseName,
            ], static fn ($value) => trim((string) $value) !== '')));

            foreach ($validationKeys as $validationKey) {
                foreach ($locales as $locale) {
                    $translated = $locale !== null
                        ? (string) trans($validationKey, [], $locale)
                        : (string) __($validationKey);

                    if (
                        $translated !== $validationKey
                        && $this->isAcceptableAttributeTranslation($translated, $headline, $locale)
                    ) {
                        return $translated;
                    }
                }
            }
        }

        $headlineLower = trim((string) str($headline)->lower());

        foreach ($locales as $locale) {
            $translated = $locale !== null
                ? (string) trans($headline, [], $locale)
                : (string) __($headline);

            if (
                $translated !== $headline
                && $this->isAcceptableAttributeTranslation($translated, $headline, $locale)
            ) {
                return $translated;
            }

            if ($headlineLower !== '' && $headlineLower !== $headline) {
                $translatedLower = $locale !== null
                    ? (string) trans($headlineLower, [], $locale)
                    : (string) __($headlineLower);

                if (
                    $translatedLower !== $headlineLower
                    && $this->isAcceptableAttributeTranslation($translatedLower, $headline, $locale)
                ) {
                    return $translatedLower;
                }
            }
        }

        $packageKeys = array_values(array_unique(array_filter([
            "svarium::messages.{$headline}",
            $headlineLower !== '' ? "svarium::messages.{$headlineLower}" : null,
            "messages.{$headline}",
            $headlineLower !== '' ? "messages.{$headlineLower}" : null,
        ], static fn ($value) => is_string($value) && trim($value) !== '')));

        foreach ($packageKeys as $packageKey) {
            foreach ($locales as $locale) {
                $packageTranslated = $locale !== null
                    ? (string) trans($packageKey, [], $locale)
                    : (string) __($packageKey);

                if (
                    $packageTranslated !== $packageKey
                    && $this->isAcceptableAttributeTranslation($packageTranslated, $headline, $locale)
                ) {
                    return $packageTranslated;
                }
            }
        }

        $dictionaryFallback = $this->resolveValidationAttributeDictionaryFallback($name, $locales);
        if ($dictionaryFallback !== null) {
            return $dictionaryFallback;
        }

        return $headline;
    }

    protected function translateAttributeToken(string $token): string
    {
        $normalizedToken = trim($token);
        if ($normalizedToken === '') {
            return $token;
        }

        $locales = $this->resolveValidationAttributeLocales();

        foreach ($locales as $locale) {
            $translated = $locale !== null
                ? (string) trans($normalizedToken, [], $locale)
                : (string) __($normalizedToken);

            if (
                $translated !== $normalizedToken
                && $this->isAcceptableAttributeTranslation($translated, $normalizedToken, $locale)
            ) {
                return $translated;
            }
        }

        // Package/domain fallback for token-like labels, e.g. "Name".
        $packageKey = "svarium::messages.{$normalizedToken}";
        foreach ($locales as $locale) {
            $translated = $locale !== null
                ? (string) trans($packageKey, [], $locale)
                : (string) __($packageKey);

            if (
                $translated !== $packageKey
                && $this->isAcceptableAttributeTranslation($translated, $normalizedToken, $locale)
            ) {
                return $translated;
            }
        }

        return $token;
    }

    /**
     * @return array<int, string|null>
     */
    protected function resolveValidationAttributeLocales(): array
    {
        $requested = trim((string) request()->header(
            'X-Svarium-Locale',
            request()->query('_locale', (string) request()->input('_locale', ''))
        ));
        $appLocale = trim((string) app()->getLocale());
        $fallbackLocale = trim((string) config('app.fallback_locale', ''));
        $hasRequested = $requested !== '';

        $candidates = $hasRequested
            ? [
                $requested,
                $this->normalizeLocale($requested),
                null,
            ]
            : [
                $appLocale,
                $this->normalizeLocale($appLocale),
                $fallbackLocale,
                $this->normalizeLocale($fallbackLocale),
                null,
            ];

        $resolved = [];
        $seen = [];

        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                if (! isset($seen['__null__'])) {
                    $seen['__null__'] = true;
                    $resolved[] = null;
                }

                continue;
            }

            $normalized = trim((string) $candidate);
            if ($normalized === '' || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $resolved[] = $normalized;
        }

        return $resolved;
    }

    protected function normalizeLocale(?string $locale): string
    {
        $value = trim((string) $locale);
        if ($value === '') {
            return '';
        }

        $value = str_replace('_', '-', $value);
        $primary = trim((string) explode('-', $value)[0]);

        return strtolower($primary);
    }

    protected function isAcceptableAttributeTranslation(string $translation, string $headline, ?string $locale): bool
    {
        $value = trim($translation);
        if ($value === '') {
            return false;
        }

        $normalizedLocale = $this->normalizeLocale($locale);
        if ($normalizedLocale === '' || $normalizedLocale === 'en') {
            return true;
        }

        return mb_strtolower($value) !== mb_strtolower(trim($headline));
    }

    /**
     * @param array<int, string|null> $locales
     */
    protected function resolveValidationAttributeDictionaryFallback(string $name, array $locales): ?string
    {
        $baseName = trim((string) str($name)->before('.')->before('[')->snake());
        if ($baseName === '') {
            return null;
        }

        $defaults = [
            'pl' => [
                'name' => 'Nazwa',
            ],
        ];

        $configured = config('upsoftware.validation.attribute_fallbacks', []);
        $dictionary = is_array($configured)
            ? array_replace_recursive($defaults, $configured)
            : $defaults;

        foreach ($locales as $locale) {
            $normalizedLocale = $this->normalizeLocale($locale);
            if ($normalizedLocale === '' || ! isset($dictionary[$normalizedLocale]) || ! is_array($dictionary[$normalizedLocale])) {
                continue;
            }

            $label = trim((string) ($dictionary[$normalizedLocale][$baseName] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }

        return null;
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
                        if ($this->isLanguageFieldComponent($component)) {
                            $messages["{$name}.*.{$rule}"] = $text;
                        } else {
                            $messages["{$name}.{$rule}"] = $text;
                        }
                    }
                }

                foreach ($this->getComponentChildren($component) as $child) {
                    $walk([$child]);
                }

                foreach ($this->getComponentSlots($component) as $slot) {
                    $walk($this->normalizeComponentNodes($slot));
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

                $children = $this->getComponentChildren($component);
                if ($children !== []) {
                    $walk($children);
                }

                $slots = $this->getComponentSlots($component);
                if ($slots !== []) {
                    foreach ($slots as $slot) {
                        $walk($this->normalizeComponentNodes($slot));
                    }
                }
            }
        };

        $walk($schema);

        return $names;
    }

    protected function collectLanguageFieldNames(array $schema): array
    {
        $names = [];

        $walk = function ($components) use (&$names, &$walk): void {
            foreach ($components as $component) {
                if ($component instanceof FieldComponent && $this->isLanguageFieldComponent($component)) {
                    $name = trim((string) $component->getName());
                    if ($name !== '') {
                        $names[$name] = $name;
                    }
                }

                $children = $this->getComponentChildren($component);
                if ($children !== []) {
                    $walk($children);
                }

                $slots = $this->getComponentSlots($component);
                if ($slots !== []) {
                    foreach ($slots as $slot) {
                        $walk($this->normalizeComponentNodes($slot));
                    }
                }
            }
        };

        $walk($schema);

        return array_values($names);
    }

    /**
     * Normalize language payload for models that do not cast translatable fields.
     * Prevents Laravel insert grammar from treating associative attributes as
     * multi-row insert when the first attribute value is an array.
     */
    protected function normalizeLanguagePayloadForModel(array $data, array $schema, object $model): array
    {
        if ($data === []) {
            return $data;
        }

        $languageFields = $this->collectLanguageFieldNames($schema);
        if ($languageFields === []) {
            return $data;
        }

        foreach ($languageFields as $fieldName) {
            if (! array_key_exists($fieldName, $data) || ! is_array($data[$fieldName])) {
                continue;
            }

            $attributeName = trim((string) str($fieldName)->before('.')->before('['));
            if ($attributeName === '') {
                continue;
            }

            if (method_exists($model, 'hasCast') && $model->hasCast($attributeName)) {
                continue;
            }

            $mutatorMethod = 'set'.str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $attributeName))).'Attribute';
            if (method_exists($model, $mutatorMethod)) {
                continue;
            }

            $encoded = json_encode($data[$fieldName], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($encoded)) {
                $data[$fieldName] = $encoded;
            }
        }

        return $data;
    }

    protected function isLanguageFieldComponent(FieldComponent $component): bool
    {
        return (bool) $component->getProp('language', false);
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

        $requestModel = request()?->attributes->get(\Upsoftware\Svarium\Panel\Form\Form::REQUEST_MODEL_KEY);
        if (is_object($requestModel) && method_exists($requestModel, 'getKey')) {
            return $requestModel;
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
                    $path = $this->normalizeHydrationPath($name);
                    $value = data_get($model, $path);

                    if ($value === null && is_string($path) && trim($path) !== '') {
                        $value = data_get($model, 'value.'.$path);
                    }

                    if ($value !== null && $this->isLanguageFieldComponent($component)) {
                        $value = $this->normalizeHydratedLanguageValue($value);
                    }

                    if ($value !== null) {
                        $component->value($value);
                    }
                }

                $children = $this->getComponentChildren($component);
                if ($children !== []) {
                    $walk($children);
                }

                $slots = $this->getComponentSlots($component);
                if ($slots !== []) {
                    foreach ($slots as $slot) {
                        $walk($this->normalizeComponentNodes($slot));
                    }
                }
            }
        };

        $walk($schema);
    }

    protected function normalizeHydrationPath(?string $name): string
    {
        $normalized = trim((string) $name);
        if ($normalized === '') {
            return '';
        }

        return str_replace(['[', ']'], ['.', ''], $normalized);
    }

    protected function normalizeHydratedLanguageValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return $value;
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded) || $decoded === []) {
            return $value;
        }

        // Keep only associative payloads like {"pl":"...", "en":"..."}.
        if (array_is_list($decoded)) {
            return $value;
        }

        return $decoded;
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

    protected function clearResolvedSchema(): void
    {
        $this->resolvedSchema = null;
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
        return PermissionMatcher::hasPermission($user, $permission);
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

                    if ($roles->contains('role_key', $role) || $roles->contains('role_key', strtolower($role))) {
                        return true;
                    }

                    if ($roles->contains(fn ($assignedRole) => $this->roleMatchesToken($assignedRole, $role))) {
                        return true;
                    }
                }
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    protected function roleMatchesToken(mixed $assignedRole, string $role): bool
    {
        $token = strtolower(trim($role));
        if ($token === '' || ! is_object($assignedRole)) {
            return false;
        }

        $roleKey = strtolower(trim((string) ($assignedRole->role_key ?? '')));
        if ($roleKey !== '' && $roleKey === $token) {
            return true;
        }

        // Legacy fallback: only when role_key is missing (old, not-yet-migrated rows).
        if ($roleKey !== '') {
            return false;
        }

        $name = $assignedRole->name ?? null;

        if (is_array($name)) {
            foreach ($name as $value) {
                if (strtolower(trim((string) $value)) === $token) {
                    return true;
                }
            }

            return false;
        }

        if (! is_string($name)) {
            return false;
        }

        $decoded = json_decode($name, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            foreach ($decoded as $value) {
                if (strtolower(trim((string) $value)) === $token) {
                    return true;
                }
            }

            return false;
        }

        return strtolower(trim($name)) === $token;
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

            $children = $this->getComponentChildren($component);
            if ($children !== []) {
                $this->setComponentChildren($component, $this->filterByOperation(
                    $children,
                    $context,
                    $accessMap
                ));
            }

            $slots = $this->getComponentSlots($component);
            if ($slots !== []) {
                foreach ($slots as $key => $slot) {
                    $this->setComponentSlot($component, (string) $key, $this->filterByOperation(
                        $this->normalizeComponentNodes($slot),
                        $context,
                        $accessMap
                    ));
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

    /**
     * @return array<int, mixed>
     */
    protected function getComponentChildren(object $component): array
    {
        if (method_exists($component, 'getChildrenComponents')) {
            $children = $component->getChildrenComponents();

            return is_array($children) ? $children : [];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getComponentSlots(object $component): array
    {
        if (method_exists($component, 'getSlotsComponents')) {
            $slots = $component->getSlotsComponents();

            return is_array($slots) ? $slots : [];
        }

        return [];
    }

    /**
     * @param array<int, mixed> $children
     */
    protected function setComponentChildren(object $component, array $children): void
    {
        if (method_exists($component, 'setChildrenComponents')) {
            $component->setChildrenComponents($children);
        }
    }

    /**
     * @param array<int, mixed> $slot
     */
    protected function setComponentSlot(object $component, string $name, array $slot): void
    {
        if (method_exists($component, 'setSlotComponents')) {
            $component->setSlotComponents($name, $slot);
        }
    }

    /**
     * @return array<int, mixed>
     */
    protected function normalizeComponentNodes(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return [$value];
        }

        return [];
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
            $submitOptions = $this->resolvedSubmitOptions($context);
            $activeSubmitAction = $this->resolveActiveSubmitAction($submitOptions);

            $actions = array_values(array_filter(
                $this->formActions(),
                static fn (mixed $action): bool => is_object($action) && method_exists($action, 'toArray')
            ));

            if ($this->hasSubmit()) {

                $actions[] = Button::make(
                    (string) ($submitOptions[$activeSubmitAction] ?? $this->submitLabel())
                )
                    ->type('submit')
                    ->name('_action')
                    ->value($activeSubmitAction)
                    ->prop('options', $submitOptions)
                    ->prop('active', $activeSubmitAction);
            }

            $schema = Form::make()
                ->method('POST')
                ->content($schema)
                ->footer($actions);
        }

        $schema = $this->appendWidgetsToSchema($schema, $context, ...$args);

        $result = new ComponentResult(
            Block::make()->content($schema),
            static::$layout
        );

        $result->meta('renderMode', $this->renderMode());

        if (method_exists($this, 'title')) {
            $resolvedTitle = $this->call('title', $context, ...$args);
            $result->meta('title', $resolvedTitle);

            $normalizedTitle = trim((string) $resolvedTitle);
            if (
                $normalizedTitle !== ''
                && function_exists('get_title')
                && function_exists('set_title')
                && trim((string) get_title()) === ''
            ) {
                set_title($normalizedTitle);
            }
        }

        if (method_exists($this, 'breadcrumbs')) {
            $result->meta('breadcrumbs', $this->call('breadcrumbs', $context, ...$args));
        }

        $layoutProps = $this->layoutProps($context, ...$args);
        if (is_array($layoutProps) && $layoutProps !== []) {
            $result->layoutProps($layoutProps);
        }

        $layoutSlots = $this->layoutSlots($context, ...$args);
        if (is_array($layoutSlots) && $layoutSlots !== []) {
            $this->applyLayoutSlots($result, $layoutSlots);
        }

        return $result;
    }

    /**
     * @param  array<int, mixed>|object  $schema
     * @return array<int, object>
     */
    protected function appendWidgetsToSchema(mixed $schema, PanelContext $context, ...$args): array
    {
        $baseSchema = [];

        if (is_array($schema)) {
            $baseSchema = $schema;
        } elseif (is_object($schema)) {
            $baseSchema = [$schema];
        }

        if (! app()->bound(WidgetRegistry::class)) {
            return $baseSchema;
        }

        $contexts = $this->resolveWidgetContexts($context);
        if ($contexts === []) {
            return $baseSchema;
        }

        $widgets = app(WidgetRegistry::class)->componentsForContexts($contexts, $context, $args);

        if ($widgets === []) {
            return $baseSchema;
        }

        if ($this->shouldWrapWidgetsInDashboardGrid($contexts)) {
            $widgets = $this->wrapWidgetsInDashboardGrid($widgets);
        }

        return [...$baseSchema, ...$widgets];
    }

    /**
     * @return array<int, string>
     */
    protected function resolveWidgetContexts(PanelContext $context): array
    {
        $requestPath = trim((string) $context->request()->path(), '/');
        $panelPrefix = trim((string) $context->panel()->prefixName(), '/');

        if (
            $panelPrefix !== ''
            && ($requestPath === $panelPrefix || str_starts_with($requestPath, $panelPrefix.'/'))
        ) {
            $requestPath = ltrim(substr($requestPath, strlen($panelPrefix)), '/');
        }

        if ($requestPath === '') {
            return ['dashboard'];
        }

        $paramValues = array_map(
            static fn ($value): string => is_scalar($value) ? trim((string) $value) : '',
            $context->params
        );
        $paramValues = array_filter($paramValues, static fn (string $value): bool => $value !== '');

        $segments = array_values(array_filter(
            explode('/', $requestPath),
            static fn (string $segment): bool => trim($segment) !== ''
        ));

        $segments = array_values(array_filter(
            $segments,
            static fn (string $segment): bool => ! in_array(trim($segment), $paramValues, true)
        ));

        if ($segments === []) {
            return ['dashboard'];
        }

        $dot = implode('.', $segments);
        $contexts = [$dot];

        if (count($segments) === 1) {
            $contexts[] = $segments[0].'.index';
            $contexts[] = $segments[0];
        }

        return array_values(array_unique(array_filter($contexts)));
    }

    /**
     * @param  array<int, string>  $contexts
     */
    protected function shouldWrapWidgetsInDashboardGrid(array $contexts): bool
    {
        return in_array('dashboard', $contexts, true);
    }

    /**
     * @param  array<int, object>  $widgets
     * @return array<int, object>
     */
    protected function wrapWidgetsInDashboardGrid(array $widgets): array
    {
        $items = [];

        foreach ($widgets as $widget) {
            if (! $widget instanceof \Upsoftware\Svarium\UI\Component) {
                continue;
            }

            $span = $this->resolveWidgetSpan($widget);
            $cell = Block::make()
                ->style([
                    'gridColumn' => 'span '.$span.' / span '.$span,
                ]);

            $cardMode = $this->resolveWidgetCardMode($widget);

            if ($cardMode === false) {
                $cell->children([$widget]);
                $items[] = $cell;
                continue;
            }

            $card = Block::make()
                ->border()
                ->borderColor('slate-200', 'zinc-800')
                ->bg('white', 'zinc-950')
                ->rounded('xl')
                ->padding(4)
                ->children([$widget]);

            if (is_string($cardMode) && in_array($cardMode, ['dashed', 'dotted', 'double'], true)) {
                $card->borderStyle($cardMode);
            }

            $cell->children([$card]);
            $items[] = $cell;
        }

        if ($items === []) {
            return [];
        }

        return [
            Block::make()
                ->grid()
                ->style([
                    'gridTemplateColumns' => 'repeat(12, minmax(0, 1fr))',
                    'gap' => '1rem',
                ])
                ->children($items),
        ];
    }

    protected function resolveWidgetSpan(\Upsoftware\Svarium\UI\Component $widget): int
    {
        $meta = $widget->getProp('widget', []);
        if (! is_array($meta)) {
            return 4;
        }

        $span = $meta['span'] ?? 4;
        if (! is_numeric($span)) {
            return 4;
        }

        $resolved = (int) $span;

        if ($resolved < 1 || $resolved > 12) {
            return 4;
        }

        return $resolved;
    }

    protected function resolveWidgetCardMode(\Upsoftware\Svarium\UI\Component $widget): bool|string
    {
        $meta = $widget->getProp('widget', []);
        if (! is_array($meta)) {
            return true;
        }

        $card = $meta['card'] ?? true;

        if (is_bool($card)) {
            return $card;
        }

        if (! is_string($card)) {
            return true;
        }

        $normalized = strtolower(trim($card));

        if (in_array($normalized, ['false', '0', 'no', 'off', 'none'], true)) {
            return false;
        }

        if (in_array($normalized, ['dashed', 'dotted', 'double'], true)) {
            return $normalized;
        }

        return true;
    }

    protected function applyLayoutSlots(ComponentResult $result, array $slots): void
    {
        foreach ($slots as $slot => $content) {
            if (! is_string($slot)) {
                continue;
            }

            $slot = trim($slot);
            if ($slot === '') {
                continue;
            }

            $method = str_contains($slot, '_')
                ? lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $slot))))
                : $slot;

            if (! method_exists($result, $method)) {
                continue;
            }

            $result->{$method}($content);
        }
    }

    protected function call(string $method, PanelContext $context, ...$routeArgs)
    {
        $ref = new \ReflectionMethod($this, $method);
        $params = [];
        $consumedRouteArgs = [];

        foreach ($ref->getParameters() as $parameter) {

            $reflectionType = $parameter->getType();
            $namedType = $reflectionType instanceof \ReflectionNamedType ? $reflectionType : null;
            $type = $namedType?->getName();
            $isBuiltin = $namedType?->isBuiltin() ?? false;
            $parameterName = $parameter->getName();

            if ($type === PanelContext::class) {
                $params[] = $context;
                continue;
            }

            if (array_key_exists($parameterName, $context->params)) {
                $params[] = $context->params[$parameterName];
                continue;
            }

            if ($type !== null && ! $isBuiltin) {
                foreach ($routeArgs as $index => $arg) {
                    if (isset($consumedRouteArgs[$index])) {
                        continue;
                    }

                    if (is_object($arg) && is_a($arg, $type)) {
                        $params[] = $arg;
                        $consumedRouteArgs[$index] = true;
                        continue 2;
                    }
                }
            }

            foreach ($routeArgs as $index => $arg) {
                if (isset($consumedRouteArgs[$index])) {
                    continue;
                }

                if (! $this->parameterAcceptsRouteArg($parameter, $arg, $type, $isBuiltin)) {
                    continue;
                }

                $params[] = $arg;
                $consumedRouteArgs[$index] = true;
                continue 2;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $params[] = $parameter->getDefaultValue();
                continue;
            }

            if ($reflectionType?->allowsNull()) {
                $params[] = null;
                continue;
            }

            if ($type !== null && ! $isBuiltin && class_exists($type)) {
                $params[] = app($type);
                continue;
            }

            $params[] = null;
        }

        return $this->$method(...$params);
    }

    protected function parameterAcceptsRouteArg(
        \ReflectionParameter $parameter,
        mixed $arg,
        ?string $type,
        bool $isBuiltin
    ): bool {
        if ($arg instanceof PanelContext) {
            return false;
        }

        if ($type === null) {
            return true;
        }

        if (! $isBuiltin) {
            return is_object($arg) && is_a($arg, $type);
        }

        if ($arg === null) {
            return $parameter->allowsNull();
        }

        return match ($type) {
            'string' => is_scalar($arg) || (is_object($arg) && method_exists($arg, '__toString')),
            'int' => is_int($arg) || (is_string($arg) && preg_match('/^-?\d+$/', trim($arg)) === 1),
            'float' => is_float($arg) || is_int($arg) || (is_string($arg) && is_numeric(trim($arg))),
            'bool' => is_bool($arg) || is_int($arg) || (is_string($arg) && in_array(strtolower(trim($arg)), ['0', '1', 'true', 'false', 'yes', 'no', 'on', 'off'], true)),
            'array' => is_array($arg),
            'mixed' => true,
            default => true,
        };
    }
}
