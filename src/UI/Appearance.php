<?php

namespace Upsoftware\Svarium\UI;

class Appearance
{
    protected array $props = [];

    protected function appendClass(string $token): static
    {
        $token = trim($token);
        if ($token === '') {
            return $this;
        }

        $current = (string) ($this->props['class'] ?? '');
        $parts = preg_split('/\s+/', trim($current)) ?: [];
        $parts = array_filter($parts);

        if (! in_array($token, $parts, true)) {
            $parts[] = $token;
        }

        $this->props['class'] = implode(' ', $parts);

        return $this;
    }

    public static function make(): static
    {
        return new static;
    }

    public function prop(string $key, mixed $value): static
    {
        $this->props[$key] = $value;

        return $this;
    }

    public function props(array $props): static
    {
        $this->props = [
            ...$this->props,
            ...$props,
        ];

        return $this;
    }

    public function fontWeight(string|int $fontWeight): static
    {
        return $this->prop('fontWeight', static::normalizeFontWeight($fontWeight));
    }

    public static function normalizeFontWeight(string|int $fontWeight): string
    {
        $value = strtolower(trim((string) $fontWeight));

        $map = [
            'thin' => '100',
            '100' => '100',

            'extralight' => '200',
            'extra-light' => '200',
            'ultralight' => '200',
            'ultra-light' => '200',
            '200' => '200',

            'light' => '300',
            '300' => '300',

            'normal' => '400',
            'regular' => '400',
            '400' => '400',

            'medium' => '500',
            '500' => '500',

            'semibold' => '600',
            'semi-bold' => '600',
            'demibold' => '600',
            '600' => '600',

            'bold' => '700',
            '700' => '700',

            'extrabold' => '800',
            'extra-bold' => '800',
            'ultrabold' => '800',
            'ultra-bold' => '800',
            '800' => '800',

            'black' => '900',
            'heavy' => '900',
            '900' => '900',
        ];

        return $map[$value] ?? $value;
    }

    public function fontSize(string|int $fontSize): static
    {
        return $this->prop('fontSize', (string) $fontSize);
    }

    public function textColor(string $light, ?string $dark = null): static
    {
        $light = trim($light);
        if ($light === '') {
            return $this;
        }

        if ($dark !== null) {
            $dark = trim($dark);
        }

        if ($dark !== null && $dark !== '') {
            return $this->prop('textColor', [
                'light' => $light,
                'dark' => $dark,
            ]);
        }

        return $this->prop('textColor', $light);
    }

    public function fontColor(string $fontColor): static
    {
        return $this->textColor($fontColor);
    }

    public function bgColor(string $light, ?string $dark = null): static
    {
        $light = trim($light);
        if ($light === '') {
            return $this;
        }

        if ($dark !== null) {
            $dark = trim($dark);
        }

        if ($dark !== null && $dark !== '') {
            return $this->prop('bgColor', [
                'light' => $light,
                'dark' => $dark,
            ]);
        }

        return $this->prop('bgColor', $light);
    }

    public function bg(string $light, ?string $dark = null): static
    {
        return $this->bgColor($light, $dark);
    }

    public function border(string|int|float|null $border = null): static
    {
        if ($border === null) {
            return $this->prop('borderWidth', 'border');
        }

        $value = trim((string) $border);
        if ($value === '') {
            return $this->prop('borderWidth', 'border');
        }

        return $this->prop('borderWidth', $value);
    }

    public function borderWidth(string|int|float $borderWidth): static
    {
        return $this->border($borderWidth);
    }

    public function borderRadius(string|int|float $borderRadius): static
    {
        $value = trim((string) $borderRadius);
        if ($value === '') {
            return $this;
        }

        return $this->prop('borderRadius', $value);
    }

    public function borderColor(string $light, ?string $dark = null): static
    {
        $light = trim($light);
        if ($light === '') {
            return $this;
        }

        if ($dark !== null) {
            $dark = trim($dark);
        }

        if ($dark !== null && $dark !== '') {
            return $this->prop('borderColor', [
                'light' => $light,
                'dark' => $dark,
            ]);
        }

        return $this->prop('borderColor', $light);
    }

    public function borderStyle(string $borderStyle): static
    {
        $value = trim($borderStyle);
        if ($value === '') {
            return $this;
        }

        return $this->prop('borderStyle', $value);
    }

    public function outline(string|int|float|null $outline = null): static
    {
        if ($outline === null) {
            return $this->prop('outlineWidth', 'outline');
        }

        $value = trim((string) $outline);
        if ($value === '') {
            return $this->prop('outlineWidth', 'outline');
        }

        return $this->prop('outlineWidth', $value);
    }

    public function outlineWidth(string|int|float $outlineWidth): static
    {
        return $this->outline($outlineWidth);
    }

    public function outlineColor(string $light, ?string $dark = null): static
    {
        $light = trim($light);
        if ($light === '') {
            return $this;
        }

        if ($dark !== null) {
            $dark = trim($dark);
        }

        if ($dark !== null && $dark !== '') {
            return $this->prop('outlineColor', [
                'light' => $light,
                'dark' => $dark,
            ]);
        }

        return $this->prop('outlineColor', $light);
    }

    public function outlineStyle(string $outlineStyle): static
    {
        $value = trim($outlineStyle);
        if ($value === '') {
            return $this;
        }

        return $this->prop('outlineStyle', $value);
    }

    public function outlineOffset(string|int|float $outlineOffset): static
    {
        $value = trim((string) $outlineOffset);
        if ($value === '') {
            return $this;
        }

        return $this->prop('outlineOffset', $value);
    }

    public function class(string $class): static
    {
        return $this->prop('class', $class);
    }

    public function width(string|int|float $width): static
    {
        if (is_int($width) || is_float($width)) {
            return $this->style([
                'width' => $width.'px',
            ]);
        }

        $value = trim($width);
        if ($value === '') {
            return $this;
        }

        if (str_starts_with($value, 'w-')) {
            return $this->appendClass($value);
        }

        return $this->appendClass('w-'.$value);
    }

    public function display(string $display): static
    {
        $value = trim($display);
        if ($value === '') {
            return $this;
        }

        return $this->appendClass($value);
    }

    public function gap(string|int|float $gap): static
    {
        if (is_int($gap)) {
            return $this->appendClass('gap-'.$gap);
        }

        if (is_float($gap)) {
            return $this->style([
                'gap' => $gap.'px',
            ]);
        }

        $value = trim($gap);
        if ($value === '') {
            return $this;
        }

        if (is_numeric($value)) {
            return $this->appendClass('gap-'.$value);
        }

        if (str_starts_with($value, 'gap-')) {
            return $this->appendClass($value);
        }

        return $this->appendClass('gap-'.$value);
    }

    public function style(array $style): static
    {
        $current = $this->props['style'] ?? [];

        return $this->prop('style', [
            ...$current,
            ...$style,
        ]);
    }

    public function toArray(): array
    {
        return $this->props;
    }
}
