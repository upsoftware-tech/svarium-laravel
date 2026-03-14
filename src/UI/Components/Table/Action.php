<?php

namespace Upsoftware\Svarium\UI\Components\Table;

use Upsoftware\Svarium\Security\RecordIdentifier;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\Panel\ResourceRegistry;

class Action
{
    protected string $type;

    protected ?string $uri = null;

    protected ?string $baseUri = null;

    protected ?string $icon = null;

    protected ?string $label = null;

    protected string $component = 'Button';

    protected string $method = 'GET';

    protected array|bool|null $confirm = null;
    protected ?string $variant = null;
    protected ?string $size = null;

    protected $visibleCallback = null;
    protected static ?array $resourceModelBySlug = null;

    /*
    |--------------------------------------------------------------------------
    | FACTORIES
    |--------------------------------------------------------------------------
    */

    public static function custom(Component $component): static
    {
        return app($component);
    }

    public static function create(?string $uri = null): static
    {
        $instance = new static;

        $instance->type = 'create';
        $instance->uri = $uri ?? 'create';
        $instance->icon = 'lucide:plus';
        $instance->label = __('Create');
        $instance->method = 'GET';
        $instance->component = 'Button';

        return $instance;
    }

    public static function edit(?string $uri = null): static
    {
        $instance = new static;
        $instance->type = 'edit';
        $instance->uri = $uri;
        $instance->icon = 'lucide:pencil';
        $instance->label = __('Edit');

        return $instance;
    }

    public static function view(?string $uri = null): static
    {
        $instance = new static;
        $instance->type = 'view';
        $instance->uri = $uri;
        $instance->icon = 'lucide:search';
        $instance->label = __('Preview');

        return $instance;
    }

    public static function delete(?string $uri = null): static
    {
        $instance = new static;

        $instance->type = 'delete';
        $instance->uri = $uri;
        $instance->icon = 'lucide:trash';
        $instance->label = __('Delete');
        $instance->method = 'DELETE';

        $instance->confirm = [
            'title' => __('Are you sure you want to delete this record?'),
            'description' => __('This operation cannot be undone.'),
            'cancel' => __('Cancel'),
            'ok' => __('Delete'),
            'variant' => 'danger',
        ];

        return $instance;
    }

    public static function duplicate(?string $uri = null): static
    {
        $instance = new static;
        $instance->type = 'duplicate';
        $instance->uri = $uri;
        $instance->icon = 'lucide:copy';
        $instance->label = __('Duplicate');
        $instance->method = 'GET';
        $instance->component = 'Button';

        return $instance;
    }

    /*
    |--------------------------------------------------------------------------
    | Fluent overrides
    |--------------------------------------------------------------------------
    */

    public function component(string $component): static
    {
        $this->component = $component;

        return $this;
    }

    public function variant(string $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function size(string $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getComponent(): string
    {
        return $this->component;
    }

    public function type(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function baseUri(string $uri): static
    {
        $this->baseUri = trim($uri, '/');

        return $this;
    }

    public function url(string $uri): static
    {
        $this->uri = $uri;

        return $this;
    }

    public function route_module(
        string $module,
        ?string $action = null,
        string|int|null $id = 'id',
        ?string $panel = null
    ): static {
        return $this->routeModule($module, $action, $id, $panel);
    }

    public function routeModule(
        string $module,
        ?string $action = null,
        string|int|null $id = 'id',
        ?string $panel = null
    ): static {
        $resolvedId = $this->resolveRouteModuleIdentifier($id);
        $uri = module_route($module, $action, $resolvedId, $panel);

        return $this->url('/'.ltrim($uri, '/'));
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function method(string $method): static
    {
        $this->method = strtoupper($method);
        return $this;
    }

    public function confirm(array|bool $config = true): static
    {
        if ($config === false) {
            $this->confirm = false;

            return $this;
        }

        if ($config === true) {
            return $this; // zostaw domyślny
        }

        // 🔥 merge z istniejącym confirm
        $this->confirm = array_merge(
            $this->confirm ?? [],
            $config
        );

        return $this;
    }

    public function show(callable $callback): static
    {
        $this->visibleCallback = $callback;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    protected function buildComponentInstance(string $uri)
    {
        $componentClass = $this->resolveComponentClass();

        $component = $componentClass::make();

        // dynamiczne ustawianie propsów jeśli istnieją metody

        if (method_exists($component, 'href')) {
            $component->href($uri);
        }

        if (method_exists($component, 'icon') && $this->icon) {
            $component->icon($this->icon);
        }

        if (method_exists($component, 'label')) {
            $component->label($this->label ?? $this->defaultLabel());
        }

        if (method_exists($component, 'method')) {
            $component->method($this->method);
        }

        if (method_exists($component, 'confirm') && $this->confirm) {
            $component->confirm($this->confirm);
        }

        if (method_exists($component, 'variant') && $this->variant) {
            $component->variant($this->variant);
        }

        if (method_exists($component, 'size') && $this->size) {
            $component->size($this->size);
        }

        if (method_exists($component, 'prop')) {
            $component->prop('actionType', $this->type);
        }

        return $component;
    }

    protected function resolveComponentClass(): string
    {
        // jeśli podano pełną klasę
        if (class_exists($this->component)) {
            return $this->component;
        }

        // domyślna przestrzeń nazw
        $namespace = 'Upsoftware\\Svarium\\UI\\Components\\';

        $class = $namespace.$this->component;

        if (! class_exists($class)) {
            throw new \RuntimeException(
                "Component {$this->component} not found."
            );
        }

        return $class;
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve URI for specific row
    |--------------------------------------------------------------------------
    */

    protected function resolveUri(array $row): string
    {
        $uriPattern = $this->uri ?? $this->defaultUri();
        $uriModel = $this->resolveUriModel($uriPattern);

        if ($this->baseUri && ! $this->isAbsoluteUri($uriPattern)) {
            $uriPattern = '/'.trim($this->baseUri, '/')
                .'/'.ltrim($uriPattern, '/');
        }

        return preg_replace_callback('/\{([^}]+)\}/', function ($matches) use ($row, $uriModel) {

            $key = $matches[1];
            $value = data_get($row, $key);

            if ($value !== null && $this->shouldEncodePlaceholder($key)) {
                $hashModel = $this->resolveHashModel($key, $row, $uriModel);
                if (is_string($hashModel) && trim($hashModel) !== '') {
                    $value = RecordIdentifier::encode($hashModel, $value);
                }
            }

            return $value;

        }, $uriPattern);
    }

    protected function shouldEncodePlaceholder(string $key): bool
    {
        $normalized = strtolower(trim($key));

        if ($normalized === 'id') {
            return true;
        }

        return str_ends_with($normalized, '_id') || str_ends_with($normalized, '.id');
    }

    protected function resolveHashModel(string $key, array $row, ?string $uriModel): ?string
    {
        if ($uriModel !== null) {
            return $uriModel;
        }

        if (strtolower(trim($key)) === 'id') {
            $rowModel = (string) ($row['_model'] ?? '');
            if ($rowModel !== '') {
                return $rowModel;
            }
        }

        return null;
    }

    protected function resolveUriModel(string $uriPattern): ?string
    {
        $segments = array_values(array_filter(
            explode('/', trim($uriPattern, '/')),
            static fn (string $segment): bool => $segment !== ''
        ));

        if ($segments === []) {
            return null;
        }

        foreach ($segments as $index => $segment) {
            if (preg_match('/^\{[^}]+\}$/', $segment) !== 1) {
                continue;
            }

            $slug = $segments[$index - 1] ?? null;
            if (! is_string($slug) || trim($slug) === '') {
                continue;
            }

            $model = $this->modelByResourceSlug($slug);
            if (is_string($model) && trim($model) !== '') {
                return $model;
            }
        }

        return null;
    }

    protected function modelByResourceSlug(string $slug): ?string
    {
        if (self::$resourceModelBySlug === null) {
            self::$resourceModelBySlug = [];

            foreach (app(ResourceRegistry::class)->all() as $resourceClass) {
                if (! is_string($resourceClass) || ! class_exists($resourceClass)) {
                    continue;
                }

                if (! method_exists($resourceClass, 'slug') || ! method_exists($resourceClass, 'model')) {
                    continue;
                }

                $resourceSlug = trim((string) $resourceClass::slug(), '/');
                $resourceModel = trim((string) $resourceClass::model(), '\\');

                if ($resourceSlug === '' || $resourceModel === '') {
                    continue;
                }

                self::$resourceModelBySlug[strtolower($resourceSlug)] = $resourceModel;
            }
        }

        return self::$resourceModelBySlug[strtolower(trim($slug))] ?? null;
    }

    protected function isAbsoluteUri(string $uri): bool
    {
        $trimmed = trim($uri);

        if ($trimmed === '') {
            return false;
        }

        if (str_starts_with($trimmed, '/')) {
            return true;
        }

        return preg_match('/^[a-z][a-z0-9+\-.]*:\/\//i', $trimmed) === 1;
    }

    protected function resolveRouteModuleIdentifier(string|int|null $id): string|int|null
    {
        if (! is_string($id)) {
            return $id;
        }

        $value = trim($id);
        if ($value === '') {
            return null;
        }

        if (str_contains($value, '{') && str_contains($value, '}')) {
            return $value;
        }

        if ($value === 'id') {
            return '{id}';
        }

        if (str_ends_with($value, '_id') || str_ends_with($value, '.id')) {
            return '{'.$value.'}';
        }

        return '{'.$value.'_id}';
    }

    public function resolve(array $row): ?Component
    {
        if ($this->visibleCallback) {
            if (! call_user_func($this->visibleCallback, $row)) {
                return null;
            }
        }

        $uri = $this->resolveUri($row);

        return $this->buildComponentInstance($uri);
    }

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */

    protected function defaultUri(): string
    {
        return match ($this->type) {
            'create' => 'create',
            'edit' => '{id}/edit',
            'view' => '{id}/preview',
            'delete' => '{id}/delete',
            'duplicate' => '{id}/duplicate',
            default => '{id}',
        };
    }

    protected function defaultLabel(): string
    {
        return match ($this->type) {
            'create' => __('Create'),
            'edit' => __('Edit'),
            'view' => __('View'),
            'delete' => __('Delete'),
            'duplicate' => __('Duplicate'),
            default => __(ucfirst($this->type)),
        };
    }

    public function toArray(): array
    {
        $uri = $this->resolveUri([]);

        return $this->buildComponentInstance($uri)->toArray();
    }
}
