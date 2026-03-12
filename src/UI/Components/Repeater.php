<?php

namespace Upsoftware\Svarium\UI\Components;

use Closure;
use Illuminate\Support\Collection;
use Upsoftware\Svarium\UI\Component;

class Repeater extends FieldComponent
{
    protected Component|array|string|Closure|null $template = null;

    public function label(string $label, string ...$labels): static
    {
        parent::label($label);

        if ($labels !== []) {
            $tableLabels = array_values(array_filter(array_map(
                static fn (string $item): string => trim($item),
                [$label, ...$labels]
            ), static fn (string $item): bool => $item !== ''));

            if ($tableLabels !== []) {
                $this->prop('tableLabels', $tableLabels);
            }
        }

        return $this;
    }

    public function template(Component|array|string|Closure $template): static
    {
        $this->template = $template;

        return $this;
    }

    public function mode(string $mode): static
    {
        $normalized = strtolower(trim($mode));
        if ($normalized === '') {
            $normalized = 'table';
        }

        return $this->prop('mode', $normalized);
    }

    public function labels(string|array $labels, ?string $valueLabel = null): static
    {
        $key = null;
        $value = null;

        if (is_array($labels)) {
            if (array_key_exists('key', $labels)) {
                $key = (string) $labels['key'];
            }
            if (array_key_exists('value', $labels)) {
                $value = (string) $labels['value'];
            }

            if ($key === null && array_key_exists(0, $labels)) {
                $key = (string) $labels[0];
            }
            if ($value === null && array_key_exists(1, $labels)) {
                $value = (string) $labels[1];
            }
        } else {
            $key = $labels;
            $value = $valueLabel;
        }

        $resolved = [
            'key' => trim((string) ($key ?? 'Attribute')),
            'value' => trim((string) ($value ?? 'Value')),
        ];

        if ($resolved['key'] === '') {
            $resolved['key'] = 'Attribute';
        }
        if ($resolved['value'] === '') {
            $resolved['value'] = 'Value';
        }

        return $this->prop('labels', $resolved);
    }

    public function values(array|Collection $values): static
    {
        return $this->prop('values', $this->normalizeValues($values));
    }

    public function startWithOne(bool $enabled = true): static
    {
        return $this->prop('startWithOne', $enabled);
    }

    public function simple(bool $enabled = true): static
    {
        return $this->prop('simple', $enabled);
    }

    public function addLabel(string $label): static
    {
        return $this->prop('addLabel', $label);
    }

    public function showLabels(bool $enabled = true): static
    {
        return $this->prop('showLabels', $enabled);
    }

    public function searchable(bool $enabled = true): static
    {
        return $this->prop('searchable', $enabled);
    }

    public function removeLabel(string $label): static
    {
        return $this->prop('removeLabel', $label);
    }

    public function minItems(int $count): static
    {
        return $this->prop('minItems', max(0, $count));
    }

    public function maxItems(int $count): static
    {
        return $this->prop('maxItems', max(0, $count));
    }

    public function max(int $count): static
    {
        return $this->maxItems($count);
    }

    public function emptyState(string $text): static
    {
        return $this->prop('empty', trim($text));
    }

    public function __call(string $method, array $arguments): mixed
    {
        if ($method === 'empty') {
            return $this->emptyState((string) ($arguments[0] ?? ''));
        }

        return parent::__call($method, $arguments);
    }

    public function toArray(): array
    {
        if (! $this->getProp('templateComponents')) {
            $this->prop('templateComponents', $this->normalizeTemplateComponents($this->template));
        }

        $array = parent::toArray();

        if (! array_key_exists('values', $array['props']) && array_key_exists('value', $array['props'])) {
            $array['props']['values'] = $this->normalizeValues($array['props']['value']);
            unset($array['props']['value']);
        }

        return $array;
    }

    protected function normalizeTemplateComponents(Component|array|string|Closure|null $template): array
    {
        if ($template === null) {
            return [];
        }

        if ($template instanceof Closure) {
            $template = $this->invokeTemplateClosure($template);
        }

        if (is_string($template) && class_exists($template)) {
            $instance = app($template);

            if ($instance instanceof Component) {
                return [$this->applyTemplateDefaults($instance->toArray())];
            }

            return [];
        }

        if ($template instanceof Component) {
            return [$this->applyTemplateDefaults($template->toArray())];
        }

        if (! is_array($template)) {
            return [];
        }

        $normalized = [];

        foreach ($template as $item) {
            if ($item instanceof Component) {
                if (method_exists($item, 'shouldRender') && ! $item->shouldRender()) {
                    continue;
                }

                $normalized[] = $this->applyTemplateDefaults($item->toArray());
                continue;
            }

            if (is_array($item) && isset($item['type'])) {
                $normalized[] = $this->applyTemplateDefaults($item);
            }
        }

        return array_values($normalized);
    }

    protected function applyTemplateDefaults(array $node): array
    {
        $props = $node['props'] ?? null;

        if (is_array($props)) {
            $hasName = array_key_exists('name', $props)
                && trim((string) ($props['name'] ?? '')) !== '';
            $hasVariant = array_key_exists('variant', $props)
                && trim((string) ($props['variant'] ?? '')) !== '';

            if ($hasName && ! $hasVariant) {
                $props['variant'] = 'ghost';
            }

            $node['props'] = $props;
        }

        if (isset($node['children']) && is_array($node['children'])) {
            $node['children'] = array_map(function (mixed $child): mixed {
                if (is_array($child) && isset($child['type'])) {
                    return $this->applyTemplateDefaults($child);
                }

                return $child;
            }, $node['children']);
        }

        if (isset($node['slots']) && is_array($node['slots'])) {
            $slots = [];

            foreach ($node['slots'] as $slotName => $slotContent) {
                if (! is_array($slotContent)) {
                    $slots[$slotName] = $slotContent;
                    continue;
                }

                $slots[$slotName] = array_map(function (mixed $child): mixed {
                    if (is_array($child) && isset($child['type'])) {
                        return $this->applyTemplateDefaults($child);
                    }

                    return $child;
                }, $slotContent);
            }

            $node['slots'] = $slots;
        }

        return $node;
    }

    protected function invokeTemplateClosure(Closure $closure): mixed
    {
        $reflection = new \ReflectionFunction($closure);

        if ($reflection->getNumberOfParameters() === 0) {
            return $closure();
        }

        return $closure($this);
    }

    protected function normalizeValues(mixed $values): array
    {
        if ($values instanceof Collection) {
            $values = $values->toArray();
        }

        if (! is_array($values)) {
            return [];
        }

        return array_values(array_map(function (mixed $item): array {
            if ($item instanceof Collection) {
                $item = $item->toArray();
            }

            if (is_object($item)) {
                if ($item instanceof \JsonSerializable) {
                    $serialized = $item->jsonSerialize();
                    return is_array($serialized) ? $serialized : [];
                }

                if (method_exists($item, 'toArray')) {
                    $array = $item->toArray();
                    return is_array($array) ? $array : [];
                }

                return get_object_vars($item);
            }

            if (is_array($item)) {
                return $item;
            }

            return [];
        }, $values));
    }
}
