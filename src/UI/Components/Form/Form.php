<?php

namespace Upsoftware\Svarium\UI\Components\Form;

use Upsoftware\Svarium\UI\Component;

class Form extends Component
{
    protected array $footer = [];

    public function requiredIndicator(bool $enabled = true): static
    {
        return $this->prop('requiredIndicatorEnabled', $enabled);
    }

    public function requiredIndicatorLabel(string|bool|null $label = true): static
    {
        return $this->prop('requiredIndicatorLabel', $label);
    }

    public function requiredIndicatorPosition(string $position = 'left'): static
    {
        $normalized = strtolower(trim($position));
        if (! in_array($normalized, ['left', 'right'], true)) {
            $normalized = 'left';
        }

        return $this->prop('requiredIndicatorPosition', $normalized);
    }

    public function footer(array $buttons): static
    {
        $this->slots['footer'] = $buttons;
        return $this;
    }

    public function method(string $method): static
    {
        return $this->prop('method', strtoupper($method));
    }

    public function action(string $action): static
    {
        return $this->prop('action', $action);
    }

    public function submitLabel(string $label): static
    {
        return $this->prop('submitLabel', $label);
    }

    public function toArray(): array
    {
        if (! array_key_exists('requiredIndicatorEnabled', $this->props)) {
            $this->prop('requiredIndicatorEnabled', (bool) config('upsoftware.form.required_indicator.enabled', false));
        }

        if (! array_key_exists('requiredIndicatorLabel', $this->props)) {
            $this->prop('requiredIndicatorLabel', config('upsoftware.form.required_indicator.label', false));
        }

        if (! array_key_exists('requiredIndicatorPosition', $this->props)) {
            $position = strtolower(trim((string) config('upsoftware.form.required_indicator.position', 'left')));
            if (! in_array($position, ['left', 'right'], true)) {
                $position = 'left';
            }

            $this->prop('requiredIndicatorPosition', $position);
        }

        return parent::toArray();
    }
}
