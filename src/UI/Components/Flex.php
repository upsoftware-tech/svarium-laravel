<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class Flex extends Component
{
    use HasChildren;

    public static function make(?string $name = null): static
    {
        return parent::make($name)->appendAppearanceClass('flex');
    }

    public function class(string $class): static
    {
        return $this->appendAppearanceClasses($class);
    }

    public function inline(bool $enabled = true): static
    {
        return $enabled
            ? $this->appendAppearanceClass('inline-flex')
            : $this->appendAppearanceClass('flex');
    }

    public function direction(string $direction): static
    {
        $value = strtolower(trim($direction));

        $map = [
            'row' => 'flex-row',
            'x' => 'flex-row',
            'horizontal' => 'flex-row',
            'row-reverse' => 'flex-row-reverse',
            'x-reverse' => 'flex-row-reverse',
            'horizontal-reverse' => 'flex-row-reverse',
            'col' => 'flex-col',
            'column' => 'flex-col',
            'y' => 'flex-col',
            'col-reverse' => 'flex-col-reverse',
            'column-reverse' => 'flex-col-reverse',
            'y-reverse' => 'flex-col-reverse',
        ];

        if (! isset($map[$value])) {
            return $this;
        }

        return $this->appendAppearanceClass($map[$value]);
    }

    public function justify(string $justify): static
    {
        $value = strtolower(trim($justify));

        $map = [
            'start' => 'justify-start',
            'left' => 'justify-start',
            'end' => 'justify-end',
            'right' => 'justify-end',
            'center' => 'justify-center',
            'between' => 'justify-between',
            'around' => 'justify-around',
            'evenly' => 'justify-evenly',
            'stretch' => 'justify-stretch',
            'normal' => 'justify-normal',
        ];

        if (! isset($map[$value])) {
            return $this;
        }

        return $this->appendAppearanceClass($map[$value]);
    }

    public function justtify(string $justify): static
    {
        return $this->justify($justify);
    }

    public function items(string $items): static
    {
        $value = strtolower(trim($items));

        $map = [
            'start' => 'items-start',
            'end' => 'items-end',
            'center' => 'items-center',
            'baseline' => 'items-baseline',
            'stretch' => 'items-stretch',
        ];

        if (! isset($map[$value])) {
            return $this;
        }

        return $this->appendAppearanceClass($map[$value]);
    }

    public function wrap(string|bool $wrap = true): static
    {
        if (is_bool($wrap)) {
            return $this->appendAppearanceClass($wrap ? 'flex-wrap' : 'flex-nowrap');
        }

        $value = strtolower(trim($wrap));

        $map = [
            'wrap' => 'flex-wrap',
            'nowrap' => 'flex-nowrap',
            'no-wrap' => 'flex-nowrap',
            'wrap-reverse' => 'flex-wrap-reverse',
        ];

        if (! isset($map[$value])) {
            return $this;
        }

        return $this->appendAppearanceClass($map[$value]);
    }

    public function gap(string|int|float $gap): static
    {
        return $this->appendScaleClass('gap', $gap);
    }

    public function gapX(string|int|float $gap): static
    {
        return $this->appendScaleClass('gap-x', $gap);
    }

    public function gapY(string|int|float $gap): static
    {
        return $this->appendScaleClass('gap-y', $gap);
    }

    public function grow(string|int|bool $grow = true): static
    {
        if (is_bool($grow)) {
            return $this->appendAppearanceClass($grow ? 'grow' : 'grow-0');
        }

        $value = trim((string) $grow);

        if ($value === '' || $value === '1') {
            return $this->appendAppearanceClass('grow');
        }

        if ($value === '0') {
            return $this->appendAppearanceClass('grow-0');
        }

        return $this->appendAppearanceClass("grow-{$value}");
    }

    public function shrink(string|int|bool $shrink = true): static
    {
        if (is_bool($shrink)) {
            return $this->appendAppearanceClass($shrink ? 'shrink' : 'shrink-0');
        }

        $value = trim((string) $shrink);

        if ($value === '' || $value === '1') {
            return $this->appendAppearanceClass('shrink');
        }

        if ($value === '0') {
            return $this->appendAppearanceClass('shrink-0');
        }

        return $this->appendAppearanceClass("shrink-{$value}");
    }

    public function center(): static
    {
        return $this->items('center')->justify('center');
    }

    protected function appendScaleClass(string $prefix, string|int|float $value): static
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return $this;
        }

        if (str_starts_with($normalized, "{$prefix}-")) {
            return $this->appendAppearanceClass($normalized);
        }

        return $this->appendAppearanceClass("{$prefix}-{$normalized}");
    }

    protected function appendAppearanceClasses(string $classes): static
    {
        $tokens = preg_split('/\s+/', trim($classes)) ?: [];

        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }

            $this->appendAppearanceClass($token);
        }

        return $this;
    }

    protected function appendAppearanceClass(string $class): static
    {
        $class = trim($class);

        if ($class === '') {
            return $this;
        }

        $appearance = $this->getProp('appearance', []);

        if (! is_array($appearance)) {
            $appearance = [];
        }

        $current = (string) ($appearance['class'] ?? '');
        $tokens = preg_split('/\s+/', trim($current)) ?: [];
        $tokens = array_values(array_filter($tokens));

        if (! in_array($class, $tokens, true)) {
            $tokens[] = $class;
        }

        $appearance['class'] = implode(' ', $tokens);

        return $this->prop('appearance', $appearance);
    }
}
