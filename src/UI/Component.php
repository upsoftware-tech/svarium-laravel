<?php

namespace Upsoftware\Svarium\UI;

abstract class Component
{
    protected array $props = [];

    protected array $children = [];

    protected array $slots = [];

    protected ?array $onlyOn = null;

    protected ?array $exceptOn = null;

    protected ?string $label = null;

    protected ?string $type = null;

    protected ?string $name = null;
    protected ?string $value = null;

    public static function make(?string $name = null): static
    {
        return new static;
    }

    public function label(string $label): static
    {
        $this->label = $label;
        $this->prop('label', $label);
        return $this;
    }

    public function getLabel(): string
    {
        return $this->props['label']
            ?? ucfirst(str_replace('_', ' ', $this->key));
    }

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function onlyOn(string|array $types): static
    {
        $this->onlyOn = (array) $types;

        return $this;
    }

    public function exceptOn(string|array $types): static
    {
        $this->exceptOn = (array) $types;

        return $this;
    }

    public function getOnlyOn(): ?array
    {
        return $this->onlyOn;
    }

    public function getExceptOn(): ?array
    {
        return $this->exceptOn;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function prop(string $key, mixed $value): static
    {
        $this->props[$key] = $value;

        return $this;
    }

    public function getProp(string $key, mixed $default = null): mixed {
        return $this->props[$key] ?? $default;
    }

    public function props(array $props): static
    {
        foreach ($props as $key => $value) {
            $this->prop($key, $value);
        }

        return $this;
    }

    public function appearance(array|Appearance $appearance): static
    {
        if ($appearance instanceof Appearance) {
            $appearance = $appearance->toArray();
        }

        $current = $this->getProp('appearance', []);

        if (! is_array($current)) {
            $current = [];
        }

        return $this->prop('appearance', [
            ...$current,
            ...$appearance,
        ]);
    }

    public function slot(string $name, Component|array|string|\Closure|null $content): static
    {
        $this->slots[$name] = $content;
        return $this;
    }


    public function content(array|Component $children): static
    {
        if ($children instanceof Component) {
            $this->children = [$children];
        } else {
            $this->children = $children;
        }

        return $this;
    }

    protected function resolveSlot(mixed $content): array
    {
        // jeśli podano nazwę klasy
        if (is_string($content) && class_exists($content)) {
            $instance = app($content);

            // LayoutSection → budujemy
            if ($instance instanceof \Upsoftware\Svarium\UI\Contracts\LayoutSection) {
                $content = $instance->build();
            }
            // Component → używamy bezpośrednio
            elseif ($instance instanceof Component) {
                $content = $instance;
            } else {
                return [];
            }
        }

        // closure
        if ($content instanceof \Closure) {
            $content = $content();
        }

        // pojedynczy komponent
        if ($content instanceof Component) {
            $array = $content->toArray();

            // jeżeli komponent ma slot 'content' i brak children → traktuj jak wrapper
            if (! empty($content->slots['content'] ?? null)) {
                $array['slots']['content'] = $this->serializeComponentNodes($content->slots['content']);
            }

            return [$array];
        }

        // tablica komponentów
        if (is_array($content)) {
            return $this->serializeComponentNodes($content);
        }

        return [];
    }

    protected function serializeComponentNodes(array $nodes): array
    {
        return array_values(
            array_filter(
                array_map(function ($node) {
                    if ($node instanceof Component) {
                        return $node->toArray();
                    }

                    if (is_object($node) && method_exists($node, 'toArray')) {
                        return $node->toArray();
                    }

                    if (is_array($node)) {
                        return $node;
                    }

                    return null;
                }, $nodes)
            )
        );
    }

    protected function slotOrChildren(string $name): array
    {
        if (! empty($this->slots[$name])) {
            return $this->serializeComponentNodes($this->slots[$name]);
        }

        return $this->serializeComponentNodes($this->children);
    }

    public function toArray(): array
    {
        return [
            'type' => class_basename(static::class),
            'props' => $this->props,
            'children' => array_values(
                array_filter(
                    array_map(function ($child) {

                        if ($child instanceof Component) {
                            return $child->toArray();
                        }

                        if (is_array($child)) {
                            return $child;
                        }

                        return null;

                    }, $this->children)
                )
            ),
            'slots' => collect($this->slots)->map(
                fn ($content) => $this->resolveSlot($content)
            )->toArray(),
        ];
    }
}
