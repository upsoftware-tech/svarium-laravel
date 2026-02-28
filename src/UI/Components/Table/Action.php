<?php

namespace Upsoftware\Svarium\UI\Components\Table;

use Upsoftware\Svarium\Security\RecordIdentifier;
use Upsoftware\Svarium\UI\Component;

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

        if ($this->baseUri) {
            $uriPattern = '/'.trim($this->baseUri, '/')
                .'/'.ltrim($uriPattern, '/');
        }

        return preg_replace_callback('/\{([^}]+)\}/', function ($matches) use ($row) {

            $key = $matches[1];
            $value = $row[$key] ?? null;

            if ($key === 'id' && $value !== null) {
                $value = RecordIdentifier::encode($row['_model'] ?? '', $value);
            }

            return $value;

        }, $uriPattern);
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
