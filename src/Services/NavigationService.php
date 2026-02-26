<?php

namespace Upsoftware\Svarium\Services;

use Illuminate\Support\Collection;

class NavigationService
{
    protected Collection $items;

    public function __construct()
    {
        $this->items = collect();
    }

    public static function make(): self
    {
        return new static();
    }

    protected function getModelClass(): string
    {
        return config('svarium.models.navigation', \Upsoftware\Svarium\Models\Navigation::class);
    }

    /**
     * Pobiera drzewo z bazy danych i opcjonalnie łączy je z elementami statycznymi.
     */
    public function getTree(string|int $id = null): array
    {
        $modelClass = $this->getModelClass();
        $query = $modelClass::with('children')
            ->where('is_active', true)
            ->orderBy('order');

        if ($id === null) {
            $query->whereNull('parent_id');

            return $query->get()
                ->map(fn ($item) => $this->formatItem($item))
                ->toArray();
        }

        $item = is_string($id)
            ? $query->where('label', $id)->first()
            : $query->where('id', $id)->first();

        return $item
            ? $this->formatItem($item)
            : [];
    }

    /**
     * Formatuje pojedynczy element (rekurencyjnie dla dzieci)
     */
    protected function formatItem($item): array
    {
        if ($item->type === 'root') {
            return [
                'id' => $item->hash,
                'label' => $item->label,
                'children' => collect($item->children)
                    ->map(fn($child) => $this->formatItem($child))
                    ->toArray(),
            ];
        } else if ($item->type === 'label') {
            return [
                'type' => 'label',
                'label' => $item->label,
            ];
        } else if ($item->type === 'separator') {
            return [
                'type' => 'separator',
            ];
        } else {
            return [
                'id' => $item->hash,
                'label' => $item->label,
                'icon' => $item->icon ? ['type' => $this->resolveIconType($item->icon), 'value' => $item->icon] : null,
                'url' => $item->route_name ? route($item->route_name, [], false) : $item->url,
                'children' => collect($item->children)
                    ->map(fn($child) => $this->formatItem($child))
                    ->toArray(),
            ];
        }
    }

    public function root() {
        return [

        ];
    }

    public function resolveIconType(string $value): string
    {
        if (preg_match('~^lucide:[a-z0-9-]+$~i', $value)) {
            return 'icon';
        }

        if (preg_match('~^(?:[a-z0-9_-]+/)*[a-z0-9_-]+\.(png|svg|jpg|jpeg|webp)$~i', $value)) {
            return 'path';
        }

        return 'invalid';
    }

    public function addItem(string $label, string $route, string $icon = null): self
    {
        $this->items->push([
            'label' => $label,
            'route' => $route,
            'icon'  => $icon,
            'children' => []
        ]);

        return $this;
    }
}
