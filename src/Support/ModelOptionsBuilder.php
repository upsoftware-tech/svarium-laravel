<?php

namespace Upsoftware\Svarium\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ModelOptionsBuilder implements Arrayable
{
    protected array $orders = [];

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
                'label' => is_scalar($label) ? (string) $label : (string) $value,
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
}
