<?php

namespace Upsoftware\Svarium\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ModelOptionsBuilder implements Arrayable
{
    protected array $orders = [];
    protected array $wheres = [];

    protected ?int $limitValue = null;

    protected mixed $mapUsing = null;

    public function __construct(
        protected string $modelClass,
        protected string $valueColumn = 'id',
        protected string $labelColumn = 'name',
    ) {}

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $column = trim($column);
        if ($column === '') {
            return $this;
        }

        $direction = strtolower(trim($direction)) === 'desc' ? 'desc' : 'asc';
        $this->orders[] = [$column, $direction];

        return $this;
    }

    public function limit(?int $limit = null): static
    {
        if ($limit === null || $limit <= 0) {
            $this->limitValue = null;

            return $this;
        }

        $this->limitValue = $limit;

        return $this;
    }

    public function where(string $column, mixed $value, string $operator = '='): static
    {
        $column = trim($column);
        if ($column === '') {
            return $this;
        }

        $operator = trim($operator);
        if ($operator === '') {
            $operator = '=';
        }

        if (is_array($value)) {
            $this->wheres[] = [
                'type' => 'in',
                'column' => $column,
                'value' => $value,
            ];

            return $this;
        }

        if ($value === null) {
            $this->wheres[] = [
                'type' => in_array($operator, ['!=', '<>', 'not null'], true) ? 'not_null' : 'null',
                'column' => $column,
            ];

            return $this;
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        $column = trim($column);
        if ($column === '') {
            return $this;
        }

        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'value' => array_values($values),
        ];

        return $this;
    }

    public function mapUsing(callable $callback): static
    {
        $this->mapUsing = $callback;

        return $this;
    }

    public function toArray(): array
    {
        if (! $this->safeClassExists($this->modelClass)) {
            return [];
        }

        if (! is_subclass_of($this->modelClass, Model::class)) {
            return [];
        }

        /** @var Builder $query */
        $query = $this->modelClass::query();
        $this->applyWithRelations($query);
        $this->applyWheres($query);

        foreach ($this->orders as [$column, $direction]) {
            $query->orderBy($column, $direction);
        }

        if (is_int($this->limitValue) && $this->limitValue > 0) {
            $query->limit($this->limitValue);
        }

        $items = [];
        $records = $query->get();

        foreach ($records as $record) {
            $value = data_get($record, $this->valueColumn);
            if ($value === null) {
                continue;
            }

            $label = data_get($record, $this->labelColumn, $value);
            $option = [
                'value' => $value,
                'label' => $this->resolveLocalizedLabel($label, $value),
            ];

            if (is_callable($this->mapUsing)) {
                $mapped = ($this->mapUsing)($record, $option);
                if (is_array($mapped) && $mapped !== []) {
                    $option = [
                        ...$option,
                        ...$mapped,
                    ];
                }
            }

            $items[] = $option;
        }

        return $items;
    }

    protected function resolveLocalizedLabel(mixed $label, mixed $fallback): string
    {
        if (is_array($label)) {
            return $this->resolveLabelFromArray($label, $fallback);
        }

        if (is_string($label)) {
            $trimmed = trim($label);

            if ($trimmed !== '' && (($trimmed[0] ?? '') === '{' || ($trimmed[0] ?? '') === '[')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return $this->resolveLabelFromArray($decoded, $fallback);
                }
            }

            return $label;
        }

        if (is_scalar($label)) {
            return (string) $label;
        }

        return is_scalar($fallback) ? (string) $fallback : '';
    }

    protected function resolveLabelFromArray(array $label, mixed $fallback): string
    {
        if ($label === []) {
            return is_scalar($fallback) ? (string) $fallback : '';
        }

        $locale = strtolower(trim((string) app()->getLocale()));
        $fallbackLocale = strtolower(trim((string) config('app.fallback_locale', 'en')));
        $fallbackShort = strtolower(trim((string) strtok($fallbackLocale, '_')));
        $localeShort = strtolower(trim((string) strtok($locale, '_')));

        foreach ([$locale, $localeShort, $fallbackLocale, $fallbackShort, 'en'] as $key) {
            if ($key === '' || ! array_key_exists($key, $label)) {
                continue;
            }

            $value = $label[$key];
            if (is_scalar($value) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        foreach ($label as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return is_scalar($fallback) ? (string) $fallback : '';
    }

    protected function safeClassExists(string $class): bool
    {
        set_error_handler(static function (): bool {
            // Composer autoload can emit include warnings when classmap is stale.
            // We treat such cases as "class does not exist" and return empty options.
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

    protected function applyWithRelations(Builder $query): void
    {
        $relations = [];

        foreach ([$this->valueColumn, $this->labelColumn] as $column) {
            if (! is_string($column) || ! str_contains($column, '.')) {
                continue;
            }

            $relation = trim(strtok($column, '.'));
            if ($relation === '') {
                continue;
            }

            $relations[] = $relation;
        }

        if ($relations !== []) {
            $query->with(array_values(array_unique($relations)));
        }
    }

    protected function applyWheres(Builder $query): void
    {
        foreach ($this->wheres as $where) {
            $type = $where['type'] ?? 'basic';
            $column = (string) ($where['column'] ?? '');

            if ($column === '') {
                continue;
            }

            if ($type === 'null') {
                $query->whereNull($column);
                continue;
            }

            if ($type === 'not_null') {
                $query->whereNotNull($column);
                continue;
            }

            if ($type === 'in') {
                $values = $where['value'] ?? [];
                if (! is_array($values) || $values === []) {
                    continue;
                }

                $query->whereIn($column, $values);
                continue;
            }

            $operator = (string) ($where['operator'] ?? '=');
            $query->where($column, $operator, $where['value'] ?? null);
        }
    }
}
