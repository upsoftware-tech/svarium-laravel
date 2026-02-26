<?php

namespace Upsoftware\Svarium\Panel\Table;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Resource;

class BulkAction
{
    protected string $key;

    protected ?string $label = null;

    protected ?string $icon = null;

    protected string $variant = 'outline';

    protected array|bool|null $confirm = null;

    protected ?Closure $handler = null;

    protected string|Closure|null $successMessage = null;

    public static function make(string $key): static
    {
        $instance = new static;

        $instance->key = trim($key);

        return $instance;
    }

    public static function delete(): static
    {
        return static::make('delete')
            ->label(__('Usuń'))
            ->icon('lucide:trash')
            ->variant('destructive')
            ->confirm([
                'title' => __('Czy na pewno?'),
                'description' => __('Tej operacji nie można cofnąć.'),
                'cancel' => __('Anuluj'),
                'ok' => __('Usuń'),
            ])
            ->successMessage(fn (int $count) => __('Usunięto :count rekordów', ['count' => $count]));
    }

    public static function duplicate(): static
    {
        return static::make('duplicate')
            ->label(__('Duplikuj'))
            ->icon('lucide:copy')
            ->variant('outline')
            ->successMessage(fn (int $count) => __('Zduplikowano :count rekordów', ['count' => $count]));
    }

    public static function fromArray(array $definition): static
    {
        $key = trim((string) ($definition['key'] ?? $definition['type'] ?? ''));

        if ($key === '') {
            throw new \InvalidArgumentException('Bulk action key is required.');
        }

        $instance = static::make($key);

        if (isset($definition['label']) && is_string($definition['label'])) {
            $instance->label($definition['label']);
        }

        if (array_key_exists('icon', $definition)) {
            $icon = $definition['icon'];
            $instance->icon(is_string($icon) ? $icon : null);
        }

        if (isset($definition['variant']) && is_string($definition['variant'])) {
            $instance->variant($definition['variant']);
        }

        if (array_key_exists('confirm', $definition) && (is_bool($definition['confirm']) || is_array($definition['confirm']) || $definition['confirm'] === null)) {
            $instance->confirm($definition['confirm']);
        }

        if (isset($definition['handler']) && $definition['handler'] instanceof Closure) {
            $instance->handler($definition['handler']);
        }

        if (isset($definition['success']) && (is_string($definition['success']) || $definition['success'] instanceof Closure)) {
            $instance->successMessage($definition['success']);
        }

        return $instance;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function variant(string $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function confirm(array|bool|null $confirm): static
    {
        $this->confirm = $confirm;

        return $this;
    }

    public function handler(Closure $handler): static
    {
        $this->handler = $handler;

        return $this;
    }

    public function successMessage(string|Closure $message): static
    {
        $this->successMessage = $message;

        return $this;
    }

    public function resolveSuccessMessage(int $count): string
    {
        if ($this->successMessage instanceof Closure) {
            return (string) ($this->successMessage)($count, $this);
        }

        if (is_string($this->successMessage) && trim($this->successMessage) !== '') {
            return str_replace(':count', (string) $count, $this->successMessage);
        }

        return __('Wykonano akcję masową na :count rekordach', ['count' => $count]);
    }

    public function run(Builder $query, array $ids, PanelContext $context, ?Resource $resource = null): int
    {
        $ids = $this->normalizeIds($ids);

        if ($ids === []) {
            return 0;
        }

        if ($this->handler instanceof Closure) {
            $result = ($this->handler)($query, $ids, $context, $resource, $this);

            return is_numeric($result) ? (int) $result : 0;
        }

        return match ($this->key) {
            'delete' => $this->runDelete($query, $ids, $resource),
            'duplicate' => $this->runDuplicate($query, $ids),
            default => 0,
        };
    }

    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'label' => $this->label ?? ucfirst($this->key),
            'icon' => $this->icon,
            'variant' => $this->variant,
            'confirm' => $this->confirm,
        ], static fn ($value) => $value !== null);
    }

    protected function runDelete(Builder $query, array $ids, ?Resource $resource = null): int
    {
        $records = (clone $query)->whereKey($ids)->get();
        $count = 0;

        foreach ($records as $record) {
            if (! $record instanceof Model) {
                continue;
            }

            if ($resource && method_exists($resource, 'beforeDelete')) {
                $resource->beforeDelete($record);
            }

            $record->delete();

            if ($resource && method_exists($resource, 'afterDelete')) {
                $resource->afterDelete($record);
            }

            $count++;
        }

        return $count;
    }

    protected function runDuplicate(Builder $query, array $ids): int
    {
        $records = (clone $query)->whereKey($ids)->get();
        $count = 0;

        foreach ($records as $record) {
            if (! $record instanceof Model) {
                continue;
            }

            $clone = $record->replicate();
            $clone->save();
            $count++;
        }

        return $count;
    }

    protected function normalizeIds(array $ids): array
    {
        $normalized = array_map(static function ($id) {
            if ($id === null) {
                return '';
            }

            if (is_scalar($id)) {
                return trim((string) $id);
            }

            return '';
        }, $ids);

        $normalized = array_values(array_filter($normalized, static fn (string $id) => $id !== ''));

        return array_values(array_unique($normalized));
    }
}
