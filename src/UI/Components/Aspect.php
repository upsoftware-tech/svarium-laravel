<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Appearance;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class Aspect extends Component
{
    use HasChildren;

    public static function make(string|int|float|null $value = 'auto', Component|array|null $content = null): static
    {
        $instance = parent::make();

        if ($content instanceof Component || is_array($content)) {
            $instance->children($content);
        }

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return $instance->auto();
        }

        return $instance->value($value);
    }

    public function value(string|int|float $value): static
    {
        return $this->prop('value', $value);
    }

    public function ratio(string|int|float $value): static
    {
        return $this->value($value);
    }

    public function custom(string|int|float $value): static
    {
        return $this->value($value);
    }

    public function square(): static
    {
        return $this->value('square');
    }

    public function video(): static
    {
        return $this->value('video');
    }

    public function auto(): static
    {
        return $this->value('auto');
    }

    public function flex(string|int|float|bool $value = true): static
    {
        if (is_bool($value)) {
            return $value ? $this->appearance('flex') : $this;
        }

        if (is_int($value) || is_float($value)) {
            return $this
                ->appearance('flex')
                ->appearance(Appearance::make()->flex($value));
        }

        $normalized = strtolower(trim((string) $value));

        if ($normalized === '' || $normalized === 'true') {
            return $this->appearance('flex');
        }

        return match ($normalized) {
            'center' => $this->appearance('flex items-center justify-center'),
            'x-center', 'justify-center' => $this->appearance('flex justify-center'),
            'y-center', 'items-center', 'align-center' => $this->appearance('flex items-center'),
            'between' => $this->appearance('flex items-center justify-between'),
            default => is_numeric($normalized)
                ? $this->appearance('flex')->appearance(Appearance::make()->flex($normalized))
                : $this->appearance('flex '.$normalized),
        };
    }

    public function align(string $value = 'center'): static
    {
        $normalized = strtolower(trim($value));

        $class = match ($normalized) {
            'start', 'top' => 'items-start',
            'end', 'bottom' => 'items-end',
            'baseline' => 'items-baseline',
            'stretch' => 'items-stretch',
            default => 'items-center',
        };

        return $this->appearance('flex '.$class);
    }

    public function justify(string $value = 'center'): static
    {
        $normalized = strtolower(trim($value));

        $class = match ($normalized) {
            'start', 'left' => 'justify-start',
            'end', 'right' => 'justify-end',
            'between' => 'justify-between',
            'around' => 'justify-around',
            'evenly' => 'justify-evenly',
            default => 'justify-center',
        };

        return $this->appearance('flex '.$class);
    }
}
