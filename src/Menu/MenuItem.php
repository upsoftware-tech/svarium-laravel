<?php

namespace Upsoftware\Svarium\Menu;

class MenuItem
{
    protected array $data = [
        'type' => 'item',
        'children' => [],
    ];

    public static function make(?string $label = null): static
    {
        $instance = new static();

        if ($label !== null) {
            $instance->label($label);
        }

        return $instance;
    }

    public static function separator(): static
    {
        return static::make()->type('separator');
    }

    public static function labelItem(string $label): static
    {
        return static::make($label)->type('label');
    }

    public static function group(string $label): static
    {
        return static::make($label)->type('group');
    }

    public function key(string $key): static
    {
        $this->data['key'] = $key;

        return $this;
    }

    public function label(?string $label = null): static
    {
        if ($label === null) {
            $this->data['type'] = 'label';

            return $this;
        }

        $this->data['label'] = $label;

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->data['icon'] = $icon;

        return $this;
    }

    public function routeName(string $routeName): static
    {
        $this->data['route_name'] = $routeName;

        return $this;
    }

    public function permission(?string $permission): static
    {
        $this->data['permission'] = $permission;

        return $this;
    }

    public function url(string $url): static
    {
        $this->data['url'] = $url;

        return $this;
    }

    public function type(string $type): static
    {
        $this->data['type'] = $type;

        return $this;
    }

    public function order(int $order): static
    {
        $this->data['order'] = $order;

        return $this;
    }

    public function path(string|array $path): static
    {
        $this->data['path'] = $path;

        return $this;
    }

    public function pathIds(string|array $pathIds): static
    {
        $this->data['path_ids'] = $pathIds;

        return $this;
    }

    public function pathKeys(string|array $pathKeys): static
    {
        return $this->pathIds($pathKeys);
    }

    public function pathId(string $pathId): static
    {
        $this->data['path_id'] = $pathId;

        return $this;
    }

    public function id(string $id): static
    {
        return $this->pathId($id);
    }

    public function pathKey(string $pathKey): static
    {
        return $this->pathId($pathKey);
    }

    public function parent(string $parent): static
    {
        $this->data['parent'] = $parent;

        return $this;
    }

    public function under(string|array $path): static
    {
        return $this->path($path);
    }

    public function navigation(string|int $navigationId): static
    {
        $this->data['navigation_id'] = $navigationId;

        return $this;
    }

    public function menu(string|int $menuKey): static
    {
        return $this->navigation($menuKey);
    }

    public function children(array $children): static
    {
        $this->data['children'] = $children;

        return $this;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
