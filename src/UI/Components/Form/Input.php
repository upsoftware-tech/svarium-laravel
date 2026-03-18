<?php

namespace Upsoftware\Svarium\UI\Components\Form;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Upsoftware\Svarium\Panel\OperationRegistry;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Resource;
use Upsoftware\Svarium\UI\Concerns\Props\HasVariant;
use Upsoftware\Svarium\UI\Components\FieldComponent;

class Input extends FieldComponent
{
    use HasVariant;

    public function textAlign(string $alignment): static
    {
        return $this->prop('textAlign', $alignment);
    }

    public function prepend(mixed $value): static
    {
        return $this->prop('prepend', $value);
    }

    public function append(mixed $value): static
    {
        return $this->prop('append', $value);
    }

    public function format(string $format): static
    {
        return $this->prop('format', $format);
    }

    public function calendarPosition(string $position): static
    {
        return $this->prop('calendarPosition', $position);
    }

    /**
     * Configure number input for sortable position with auto max/value.
     *
     * Examples:
     *  ->positionField()
     *  ->positionField(LocationCountry::class)
     *  ->positionField(LocationCountry::class, 'position', 'parent_id', 15)
     *  ->positionField(LocationCountry::class, 'position', ['parent_id' => 15, 'tenant_id' => 2])
     */
    public function positionField(
        ?string $modelClass = null,
        string $column = 'position',
        string|array|null $scope = null,
        mixed $scopeValue = null,
        bool $autoValue = true
    ): static {
        $column = trim($column);
        $resolvedModelClass = $this->resolvePositionModelClass($modelClass);

        // Always force numeric position field behaviour.
        $this->type('number')
            ->step(1)
            ->prop('min', 1)
            ->rule('numeric')
            ->min(1);

        if (
            $column === ''
            || ! is_string($resolvedModelClass)
            || $resolvedModelClass === ''
            || ! $this->isValidEloquentModelClass($resolvedModelClass)
        ) {
            if ($autoValue && ! $this->hasExplicitValue()) {
                $this->value(1);
            }

            return $this;
        }

        $query = $resolvedModelClass::query();

        $this->applyPositionScope(
            $query,
            $scope,
            $scopeValue,
            func_num_args() >= 4
        );

        $maxPosition = $this->resolvePositionMax($query, $column);
        $nextPosition = max(1, $maxPosition + 1);

        $this->prop('max', $nextPosition)
            ->rule('numeric')
            ->min(1)
            ->max($nextPosition);

        if ($autoValue && ! $this->hasExplicitValue()) {
            $this->value($nextPosition);
        }

        return $this;
    }

    protected function isValidEloquentModelClass(string $class): bool
    {
        $normalized = trim($class, '\\ ');
        if ($normalized === '') {
            return false;
        }

        if (! $this->safeClassExists($normalized)) {
            return false;
        }

        return is_subclass_of($normalized, EloquentModel::class);
    }

    protected function safeClassExists(string $class): bool
    {
        set_error_handler(static function (): bool {
            // Suppress include/autoload warnings for missing class files.
            return true;
        });

        try {
            return class_exists($class);
        } catch (\Throwable) {
            return false;
        } finally {
            restore_error_handler();
        }
    }

    protected function resolvePositionModelClass(?string $modelClass = null): ?string
    {
        $candidate = is_string($modelClass) ? trim($modelClass) : '';
        if ($candidate !== '') {
            return $candidate;
        }

        if (! app()->bound(PanelContext::class)) {
            return null;
        }

        try {
            /** @var PanelContext $context */
            $context = app(PanelContext::class);
            $request = $context->request();
            $panelName = trim((string) ($request->attributes->get('panel') ?? $context->panel()->name ?? ''));
            if ($panelName === '') {
                return null;
            }

            $path = trim((string) $request->path(), '/');
            $prefix = trim($context->panel()->prefixName(), '/');

            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                $path = trim((string) substr($path, strlen($prefix)), '/');
            }

            if ($path === '') {
                return null;
            }

            if (! app()->bound(OperationRegistry::class)) {
                return null;
            }

            /** @var OperationRegistry $registry */
            $registry = app(OperationRegistry::class);
            $resolved = $registry->resolve($panelName, strtoupper((string) $request->method()), $path);
            if (! is_array($resolved)) {
                return null;
            }

            $resourceClass = trim((string) (($resolved['meta']['resource'] ?? null) ?: ''));
            if (
                $resourceClass === ''
                || ! $this->safeClassExists($resourceClass)
                || ! is_subclass_of($resourceClass, Resource::class)
                || ! method_exists($resourceClass, 'model')
            ) {
                return null;
            }

            $resolvedModel = trim((string) $resourceClass::model(), '\\');

            return $resolvedModel !== '' ? $resolvedModel : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function applyPositionScope(
        Builder $query,
        string|array|null $scope,
        mixed $scopeValue,
        bool $scopeValueProvided = false
    ): void {
        if (is_array($scope)) {
            foreach ($scope as $column => $value) {
                $name = is_string($column) ? trim($column) : '';
                if ($name === '') {
                    continue;
                }

                $resolvedValue = $value instanceof Closure ? $value() : $value;

                if ($resolvedValue === null) {
                    $query->whereNull($name);
                    continue;
                }

                $query->where($name, $resolvedValue);
            }

            return;
        }

        if (! is_string($scope) || trim($scope) === '') {
            return;
        }

        $column = trim($scope);
        $resolvedValue = $scopeValue instanceof Closure ? $scopeValue() : $scopeValue;

        if (! $scopeValueProvided) {
            $resolvedValue = request()->input($column);
            if ($resolvedValue === null) {
                return;
            }
        }

        if ($resolvedValue === null) {
            $query->whereNull($column);
            return;
        }

        $query->where($column, $resolvedValue);
    }

    protected function resolvePositionMax(Builder $query, string $column): int
    {
        try {
            $max = $query->max($column);
        } catch (\Throwable) {
            return 0;
        }

        if ($max === null || $max === '') {
            return 0;
        }

        return is_numeric($max) ? (int) $max : 0;
    }

    protected function hasExplicitValue(): bool
    {
        $value = $this->getValue();

        return ! ($value === null || (is_string($value) && trim($value) === ''));
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        if ((bool) ($array['props']['language'] ?? false)) {
            $array['type'] = 'InputLanguage';
        }

        return $array;
    }
}
