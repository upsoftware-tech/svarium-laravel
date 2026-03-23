<?php

namespace Upsoftware\Svarium\UI\Components;

use Closure;
use Illuminate\Support\Collection;
use Upsoftware\Svarium\UI\Appearance;
use Upsoftware\Svarium\UI\Component;

class Repeater extends FieldComponent
{
    protected Component|array|string|Closure|null $template = null;
    protected Component|array|string|Closure|null $separatorTemplate = null;
    protected Component|array|string|Closure|null $modalTemplate = null;

    public function cols(int|string $cols): static
    {
        return $this->prop('cols', $cols);
    }

    public function columns(int|string $cols): static
    {
        return $this->cols($cols);
    }

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

    public function modal(bool|Component|array|string|Closure $enabled = true): static
    {
        if (is_bool($enabled)) {
            $this->prop('modal', $enabled);

            if ($enabled) {
                $this->prop('startWithOne', false);
            }

            return $this;
        }

        $this->prop('modal', true);
        $this->prop('startWithOne', false);
        $this->modalTemplate = $enabled;

        return $this;
    }

    public function modalTemplate(Component|array|string|Closure $template): static
    {
        $this->modalTemplate = $template;

        return $this
            ->prop('modal', true)
            ->prop('startWithOne', false);
    }

    public function modalMaxWidth(int|string|null $width): static
    {
        if ($width === null) {
            return $this->prop('modalMaxWidth', null);
        }

        return $this->prop('modalMaxWidth', is_int($width) ? $width : trim((string) $width));
    }

    public function modalWidth(int|string|null $width): static
    {
        return $this->modalMaxWidth($width);
    }

    public function mode(string $mode): static
    {
        $normalized = strtolower(trim($mode));
        if ($normalized === '') {
            $normalized = 'table';
        }

        return $this->prop('mode', $normalized);
    }

    public function delete(bool $enabled = true): static
    {
        return $this->prop('delete', $enabled);
    }

    public function border(string|int|float|null $border = null): static
    {
        if (is_string($border)) {
            $normalized = strtolower(trim($border));
            if ($normalized === 'none') {
                $border = '0';
            }
        }

        $appearance = Appearance::make()->border($border);

        return $this->mergeItemAppearance($appearance->toArray());
    }

    public function padding(string|int|float $padding): static
    {
        $appearance = Appearance::make()->padding($padding);

        return $this->mergeItemAppearance($appearance->toArray());
    }

    public function created(bool $enabled = true): static
    {
        return $this->prop('created', $enabled);
    }

    public function separator(bool $enabled = true): static
    {
        return $this->prop('separator', $enabled);
    }

    public function separatorTemplate(Component|array|string|Closure|null $template = null): static
    {
        $this->separatorTemplate = $template ?? Separator::make()->margin('my-4');

        return $this;
    }

    public function separatorPosition(string $position): static
    {
        $normalized = strtolower(trim($position));
        if (! in_array($normalized, ['top', 'bottom', 'both'], true)) {
            $normalized = 'bottom';
        }

        return $this->prop('separatorPosition', $normalized);
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

    public function editLabel(string|bool|null $label): static
    {
        if ($label === false || $label === null) {
            return $this->prop('editLabel', false);
        }

        $normalized = trim((string) $label);
        if (strtolower($normalized) === 'none') {
            return $this->prop('editLabel', false);
        }

        return $this->prop('editLabel', $normalized);
    }

    public function editIcon(string $icon): static
    {
        return $this->prop('editIcon', trim($icon));
    }

    public function deleteLabel(string|bool|null $label): static
    {
        if ($label === false || $label === null) {
            return $this->prop('deleteLabel', false);
        }

        $normalized = trim((string) $label);
        if (strtolower($normalized) === 'none') {
            return $this->prop('deleteLabel', false);
        }

        return $this->prop('deleteLabel', $normalized);
    }

    public function deleteIcon(string $icon): static
    {
        return $this->prop('deleteIcon', trim($icon));
    }

    public function editAppearance(array|Appearance|string $appearance): static
    {
        return $this->prop('editAppearance', $this->normalizeAppearanceProp($appearance));
    }

    public function editApperance(array|Appearance|string $appearance): static
    {
        return $this->editAppearance($appearance);
    }

    public function deleteAppearance(array|Appearance|string $appearance): static
    {
        return $this->prop('deleteAppearance', $this->normalizeAppearanceProp($appearance));
    }

    public function actionAppearance(array|Appearance|string $appearance): static
    {
        return $this->prop('actionAppearance', $this->normalizeAppearanceProp($appearance));
    }

    public function actionApperance(array|Appearance|string $appearance): static
    {
        return $this->actionAppearance($appearance);
    }

    public function emptyAppearance(array|Appearance|string $appearance): static
    {
        return $this->prop('emptyAppearance', $this->normalizeAppearanceProp($appearance));
    }

    public function emptyApperance(array|Appearance|string $appearance): static
    {
        return $this->emptyAppearance($appearance);
    }

    public function deleteApperance(array|Appearance|string $appearance): static
    {
        return $this->deleteAppearance($appearance);
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
        $resolvedTemplateForModal = null;

        if (! $this->getProp('templateComponents')) {
            $templateSource = $this->template;

            if ($templateSource === null && $this->getChildrenComponents() !== []) {
                $templateSource = $this->getChildrenComponents();
                $this->setChildrenComponents([]);
            }

            $resolvedTemplate = $this->normalizeTemplateComponents($templateSource);
            $resolvedTemplateForModal = $this->normalizeTemplateComponents($templateSource, false);
            $this->prop('templateComponents', $resolvedTemplate);
        }

        if (! $this->getProp('separatorTemplateComponents') && $this->separatorTemplate !== null) {
            $this->prop('separatorTemplateComponents', $this->normalizeTemplateComponents($this->separatorTemplate));
        }

        if ($this->getProp('modal') === true && ! $this->getProp('modalTemplateComponents')) {
            $resolvedModalTemplate = $this->modalTemplate !== null
                ? $this->normalizeTemplateComponents($this->modalTemplate, false)
                : ($resolvedTemplateForModal ?? (array) ($this->getProp('templateComponents') ?? []));

            $modalAddLabel = trim((string) ($this->getProp('modalAddLabel') ?? ''));
            if ($modalAddLabel === '') {
                $modalAddLabel = trim((string) ($this->getProp('addLabel') ?? ''));
            }
            if ($modalAddLabel === '') {
                $modalAddLabel = __('Add');
            }

            $this->prop('modalTemplateComponents', $resolvedModalTemplate);
            $this->prop('modalAddLabel', $modalAddLabel);
            $this->prop('modalEditLabel', trim((string) ($this->getProp('editLabel') ?? '')) !== ''
                ? (string) $this->getProp('editLabel')
                : __('Edit'));
            $this->prop('modalSaveLabel', __('Save'));
            $this->prop('modalCancelLabel', __('Cancel'));
            $this->prop('modalTitleCreate', __('Add item'));
            $this->prop('modalTitleEdit', __('Edit item'));
        }

        $array = parent::toArray();

        if (! array_key_exists('values', $array['props']) && array_key_exists('value', $array['props'])) {
            $array['props']['values'] = $this->normalizeValues($array['props']['value']);
            unset($array['props']['value']);
        }

        return $array;
    }

    protected function normalizeTemplateComponents(Component|array|string|Closure|null $template, bool $applyTemplateDefaults = true): array
    {
        if ($template === null) {
            return [];
        }

        if ($template instanceof Closure) {
            $template = $this->invokeTemplateClosure($template);
        }

        if (is_string($template) && class_exists($template)) {
            if (is_subclass_of($template, Component::class)) {
                try {
                    $instance = app($template);
                } catch (\Throwable) {
                    $instance = null;
                }

                if ($instance instanceof Component) {
                    $node = $instance->toArray();

                    return [$applyTemplateDefaults ? $this->applyTemplateDefaults($node) : $node];
                }
            }

            if (method_exists($template, 'make')) {
                try {
                    $resolved = $template::make();
                } catch (\Throwable) {
                    $resolved = [];
                }

                return $this->normalizeTemplateComponents($resolved, $applyTemplateDefaults);
            }

            try {
                $instance = app($template);
            } catch (\Throwable) {
                $instance = null;
            }

            if ($instance !== null && method_exists($instance, 'make')) {
                try {
                    $resolved = $instance->make();
                } catch (\Throwable) {
                    $resolved = [];
                }

                return $this->normalizeTemplateComponents($resolved, $applyTemplateDefaults);
            }

            return [];
        }

        if ($template instanceof Component) {
            $node = $template->toArray();

            return [$applyTemplateDefaults ? $this->applyTemplateDefaults($node) : $node];
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

                $node = $item->toArray();
                $normalized[] = $applyTemplateDefaults ? $this->applyTemplateDefaults($node) : $node;
                continue;
            }

            if (is_array($item) && isset($item['type'])) {
                $normalized[] = $applyTemplateDefaults ? $this->applyTemplateDefaults($item) : $item;
            }
        }

        return array_values($normalized);
    }

    protected function mergeItemAppearance(array $appearance): static
    {
        $current = $this->getProp('itemAppearance', []);
        if (! is_array($current)) {
            $current = [];
        }

        $mergedClass = trim((string) ($current['class'] ?? ''));
        $incomingClass = trim((string) ($appearance['class'] ?? ''));

        if ($incomingClass !== '') {
            $tokens = preg_split('/\s+/', $mergedClass.' '.$incomingClass) ?: [];
            $tokens = array_values(array_filter(array_map(static fn (string $token): string => trim($token), $tokens), static fn (string $token): bool => $token !== ''));
            $mergedClass = implode(' ', array_values(array_unique($tokens)));
        }

        $merged = [
            ...$current,
            ...$appearance,
        ];

        if ($mergedClass !== '') {
            $merged['class'] = $mergedClass;
        }

        return $this->prop('itemAppearance', $merged);
    }

    protected function normalizeAppearanceProp(array|Appearance|string $appearance): array
    {
        if (is_string($appearance)) {
            $appearance = trim($appearance);

            if ($appearance === '') {
                return [];
            }

            return Appearance::make()->class($appearance)->toArray();
        }

        if ($appearance instanceof Appearance) {
            return $appearance->toArray();
        }

        return $appearance;
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
