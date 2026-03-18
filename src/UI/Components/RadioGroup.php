<?php

namespace Upsoftware\Svarium\UI\Components;

use Closure;
use RuntimeException;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class RadioGroup extends FieldComponent
{
    use HasChildren;

    protected Component|array|string|Closure|null $itemTemplate = null;

    public function options(array $options): static
    {
        return $this->prop('options', $options);
    }

    public function hint(string $hint): static
    {
        return $this->prop('hint', $hint);
    }

    public function defaultValue(string|int|float|bool|null $value): static
    {
        return $this->prop('defaultValue', $value);
    }

    public function inline(bool $enabled = true): static
    {
        return $this->prop('inline', $enabled);
    }

    public function template(Component|array|string|Closure $template): static
    {
        $this->itemTemplate = $template;

        return $this;
    }

    public function itemTemplate(Component|array|string|Closure $template): static
    {
        return $this->template($template);
    }

    public function toArray(): array
    {
        $this->buildTemplateChildren();

        return parent::toArray();
    }

    protected function buildTemplateChildren(): void
    {
        if ($this->itemTemplate === null) {
            return;
        }

        if ($this->children !== []) {
            return;
        }

        $options = $this->normalizedOptions();

        foreach ($options as $index => $option) {
            $resolved = $this->resolveTemplateNode($option, (int) $index);

            if ($resolved === null) {
                continue;
            }

            if ($resolved instanceof Component) {
                $this->child($resolved);
                continue;
            }

            if (is_array($resolved)) {
                foreach ($resolved as $node) {
                    if ($node instanceof Component) {
                        $this->child($node);
                    }
                }
            }
        }
    }

    protected function resolveTemplateNode(array $option, int $index): Component|array|null
    {
        if ($this->itemTemplate instanceof Closure) {
            return ($this->itemTemplate)($option, $index, $this);
        }

        if (is_string($this->itemTemplate)) {
            if (! class_exists($this->itemTemplate)) {
                return null;
            }

            $instance = app($this->itemTemplate);
            if (! $instance instanceof Component) {
                throw new RuntimeException('RadioGroup template class must be a Component.');
            }

            return $this->applyOptionDefaultsToTemplate($instance, $option);
        }

        if ($this->itemTemplate instanceof Component) {
            return $this->applyOptionDefaultsToTemplate(clone $this->itemTemplate, $option);
        }

        if (is_array($this->itemTemplate)) {
            return array_map(function ($node) use ($option) {
                if ($node instanceof Component) {
                    return $this->applyOptionDefaultsToTemplate(clone $node, $option);
                }

                return null;
            }, $this->itemTemplate);
        }

        return null;
    }

    protected function applyOptionDefaultsToTemplate(Component $template, array $option): Component
    {
        if ($template instanceof RadioItem) {
            $value = $template->getProp('value');
            if ($value === null && array_key_exists('value', $option)) {
                $template->value($option['value']);
            }

            $label = $template->getProp('label');
            if (($label === null || $label === '') && array_key_exists('label', $option)) {
                $template->label((string) $option['label']);
            }

            $disabled = $template->getProp('disabled');
            if ($disabled === null && array_key_exists('disabled', $option)) {
                $template->disabled((bool) $option['disabled']);
            }
        }

        return $template;
    }

    protected function normalizedOptions(): array
    {
        $options = $this->getProp('options', []);

        if (! is_array($options) || $options === []) {
            return [];
        }

        if (! $this->isAssoc($options)) {
            return array_values(
                array_filter(
                    array_map(
                        fn (mixed $option, int $index) => $this->normalizeListOption($option, $index),
                        $options,
                        array_keys($options)
                    ),
                    fn (mixed $option) => is_array($option)
                )
            );
        }

        return array_values(
            array_filter(
                array_map(
                    fn (mixed $option, string|int $key, int $index) => $this->normalizeMappedOption($key, $option, $index),
                    $options,
                    array_keys($options),
                    array_keys(array_values($options))
                ),
                fn (mixed $option) => is_array($option)
            )
        );
    }

    protected function normalizeListOption(mixed $option, int $index): ?array
    {
        if (is_array($option)) {
            $value = $option['value'] ?? null;

            if ($value === null && array_key_exists('id', $option)) {
                $value = $option['id'];
            }

            if ($value === null) {
                return null;
            }

            return [
                ...$option,
                'value' => $value,
                'label' => (string) ($option['label'] ?? $value),
                '_index' => $index,
            ];
        }

        if (is_string($option) || is_numeric($option) || is_bool($option)) {
            return [
                'value' => $option,
                'label' => (string) $option,
                '_index' => $index,
            ];
        }

        return null;
    }

    protected function normalizeMappedOption(string|int $key, mixed $option, int $index): ?array
    {
        if (is_array($option)) {
            $value = $option['value'] ?? $key;

            return [
                ...$option,
                'value' => $value,
                'label' => (string) ($option['label'] ?? $value),
                '_index' => $index,
            ];
        }

        if (is_string($option) || is_numeric($option) || is_bool($option)) {
            return [
                'value' => $key,
                'label' => (string) $option,
                '_index' => $index,
            ];
        }

        return null;
    }
}
