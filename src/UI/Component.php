<?php

namespace Upsoftware\Svarium\UI;

abstract class Component
{
    protected array $props = [];

    protected array $children = [];

    protected array $slots = [];
    protected array $slotPlacement = [];
    protected int $slotPlacementOrder = 0;

    protected ?array $onlyOn = null;

    protected ?array $exceptOn = null;

    protected ?string $label = null;

    protected ?string $type = null;

    protected ?string $name = null;
    protected mixed $value = null;
    protected bool $phpIf = true;

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
        $this->prop('type', $type);

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

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function prop(string $key, mixed $value): static
    {
        $this->props[$key] = $value;

        return $this;
    }

    public function if(bool|\Closure $condition): static
    {
        $this->phpIf = $this->evaluateCondition($condition);

        return $this;
    }

    public function phpIf(bool|\Closure $condition): static
    {
        return $this->if($condition);
    }

    public function unless(bool|\Closure $condition): static
    {
        $this->phpIf = ! $this->evaluateCondition($condition);

        return $this;
    }

    public function phpUnless(bool|\Closure $condition): static
    {
        return $this->unless($condition);
    }

    public function vIf(string|bool|int|float|null $condition): static
    {
        return $this->prop('vIf', $condition);
    }

    public function shouldRender(): bool
    {
        return $this->phpIf;
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

    public function appearance(array|Appearance|string $appearance): static
    {
        if (is_string($appearance)) {
            $classes = trim($appearance);

            if ($classes === '') {
                return $this;
            }

            $appearance = Appearance::make()
                ->class($classes)
                ->toArray();
        }

        if ($appearance instanceof Appearance) {
            $appearance = $appearance->toArray();
        }

        $current = $this->getProp('appearance', []);

        if (! is_array($current)) {
            $current = [];
        }

        $mergedClass = $this->mergeAppearanceClasses(
            $current['class'] ?? '',
            $appearance['class'] ?? ''
        );

        if ($mergedClass !== '') {
            $current['class'] = $mergedClass;
            unset($appearance['class']);
        }

        return $this->prop('appearance', [
            ...$current,
            ...$appearance,
        ]);
    }

    public function __call(string $method, array $arguments): mixed
    {
        if (! method_exists(Appearance::class, $method)) {
            throw new \BadMethodCallException(sprintf(
                'Call to undefined method %s::%s()',
                static::class,
                $method
            ));
        }

        $reflectionMethod = new \ReflectionMethod(Appearance::class, $method);

        if (! $reflectionMethod->isPublic() || $reflectionMethod->isStatic()) {
            throw new \BadMethodCallException(sprintf(
                'Call to undefined method %s::%s()',
                static::class,
                $method
            ));
        }

        $appearance = Appearance::make();
        $appearance->{$method}(...$arguments);

        return $this->appearance($appearance);
    }

    public function slot(
        string $name,
        Component|array|string|\Closure|null $content,
        ?string $anchor = null,
        string $position = 'after',
        int|string|null $priority = null
    ): static
    {
        if ($anchor === null) {
            $this->slots[$name] = $content;
            unset($this->slotPlacement[$name]);
            return $this;
        }

        $slotName = $this->resolvePlacedSlotName($name);
        $this->slots[$slotName] = $content;

        $normalizedAnchor = strtolower(trim($anchor));
        if (! in_array($normalizedAnchor, ['header', 'footer'], true)) {
            $normalizedAnchor = 'header';
        }

        $normalizedPosition = strtolower(trim($position));
        if (! in_array($normalizedPosition, ['before', 'after'], true)) {
            $normalizedPosition = 'after';
        }

        $normalizedPriority = null;
        if (is_int($priority)) {
            $normalizedPriority = $priority;
        } elseif (is_string($priority)) {
            $trimmedPriority = trim($priority);
            if ($trimmedPriority !== '' && is_numeric($trimmedPriority)) {
                $normalizedPriority = (int) $trimmedPriority;
            }
        }

        $order = $this->slotPlacementOrder;
        $this->slotPlacementOrder++;

        $this->slotPlacement[$slotName] = [
            'anchor' => $normalizedAnchor,
            'position' => $normalizedPosition,
            'priority' => $normalizedPriority,
            'order' => $order,
        ];

        return $this;
    }

    protected function resolvePlacedSlotName(string $name): string
    {
        $base = trim($name);
        if ($base === '') {
            $base = 'slot';
        }

        if (! array_key_exists($base, $this->slots)) {
            return $base;
        }

        $counter = 1;
        do {
            $candidate = "{$base}_{$counter}";
            $counter++;
        } while (array_key_exists($candidate, $this->slots));

        return $candidate;
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
            if (! $content->shouldRender()) {
                return [];
            }

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
                        if (! $node->shouldRender()) {
                            return null;
                        }

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

    protected function mergeAppearanceClasses(mixed $current, mixed $incoming): string
    {
        $currentTokens = preg_split('/\s+/', trim((string) $current)) ?: [];
        $incomingTokens = preg_split('/\s+/', trim((string) $incoming)) ?: [];

        $tokens = [];

        foreach (array_merge($currentTokens, $incomingTokens) as $token) {
            $token = trim($token);

            if ($token === '') {
                continue;
            }

            if (! in_array($token, $tokens, true)) {
                $tokens[] = $token;
            }
        }

        return implode(' ', $tokens);
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
        $props = $this->props;

        if ($this->slotPlacement !== []) {
            $props['__slotPlacement'] = $this->slotPlacement;
        }

        return [
            'type' => class_basename(static::class),
            'props' => $props,
            'children' => array_values(
                array_filter(
                    array_map(function ($child) {

                        if ($child instanceof Component) {
                            if (! $child->shouldRender()) {
                                return null;
                            }

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

    protected function evaluateCondition(bool|\Closure $condition): bool
    {
        if ($condition instanceof \Closure) {
            return (bool) $condition($this);
        }

        return (bool) $condition;
    }
}
