<?php

namespace Upsoftware\Svarium\UI;

class Appearance
{
    protected array $props = [];

    protected const CSS_GLOBAL_VALUES = [
        'inherit',
        'initial',
        'unset',
        'revert',
        'revert-layer',
    ];

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

    public function padding(string|int|float $padding): static
    {
        if (is_int($padding)) {
            return $this->appendClass('p-'.$padding);
        }

        if (is_float($padding)) {
            return $this->style([
                'padding' => $padding.'px',
            ]);
        }

        $value = trim($padding);
        if ($value === '') {
            return $this;
        }

        $normalized = strtolower($value);

        if (str_starts_with($normalized, 'p-') || preg_match('/^p[trblxyse]-.+$/', $normalized)) {
            return $this->appendClass($normalized);
        }

        if (preg_match('/^(x|y|t|r|b|l|s|e)-.+$/', $normalized)) {
            return $this->appendClass('p-'.$normalized);
        }

        if (is_numeric($normalized)) {
            return $this->appendClass('p-'.$normalized);
        }

        if ($this->shouldTreatAsCssValue($value)) {
            return $this->style([
                'padding' => $value,
            ]);
        }

        return $this->appendClass('p-'.$normalized);
    }

    public function justifyContent(string|int|float $value): static
    {
        return $this->applyUtilityProperty($value, 'justify', 'justifyContent', [
            'start' => 'justify-start',
            'flex-start' => 'justify-start',
            'justify-start' => 'justify-start',
            'end' => 'justify-end',
            'flex-end' => 'justify-end',
            'justify-end' => 'justify-end',
            'end-safe' => 'justify-end-safe',
            'safe-end' => 'justify-end-safe',
            'safe flex-end' => 'justify-end-safe',
            'safe-flex-end' => 'justify-end-safe',
            'justify-end-safe' => 'justify-end-safe',
            'center' => 'justify-center',
            'justify-center' => 'justify-center',
            'center-safe' => 'justify-center-safe',
            'safe-center' => 'justify-center-safe',
            'safe center' => 'justify-center-safe',
            'justify-center-safe' => 'justify-center-safe',
            'between' => 'justify-between',
            'space-between' => 'justify-between',
            'justify-between' => 'justify-between',
            'around' => 'justify-around',
            'space-around' => 'justify-around',
            'justify-around' => 'justify-around',
            'evenly' => 'justify-evenly',
            'space-evenly' => 'justify-evenly',
            'justify-evenly' => 'justify-evenly',
            'stretch' => 'justify-stretch',
            'justify-stretch' => 'justify-stretch',
            'baseline' => 'justify-baseline',
            'justify-baseline' => 'justify-baseline',
            'normal' => 'justify-normal',
            'justify-normal' => 'justify-normal',
        ]);
    }

    public function justifyItems(string|int|float $value): static
    {
        return $this->applyUtilityProperty($value, 'justify-items', 'justifyItems', [
            'start' => 'justify-items-start',
            'flex-start' => 'justify-items-start',
            'justify-items-start' => 'justify-items-start',
            'end' => 'justify-items-end',
            'flex-end' => 'justify-items-end',
            'justify-items-end' => 'justify-items-end',
            'center' => 'justify-items-center',
            'justify-items-center' => 'justify-items-center',
            'stretch' => 'justify-items-stretch',
            'justify-items-stretch' => 'justify-items-stretch',
            'normal' => 'justify-items-normal',
            'justify-items-normal' => 'justify-items-normal',
        ]);
    }

    public function justifySelf(string|int|float $value): static
    {
        return $this->applyUtilityProperty($value, 'justify-self', 'justifySelf', [
            'auto' => 'justify-self-auto',
            'justify-self-auto' => 'justify-self-auto',
            'start' => 'justify-self-start',
            'flex-start' => 'justify-self-start',
            'justify-self-start' => 'justify-self-start',
            'end' => 'justify-self-end',
            'flex-end' => 'justify-self-end',
            'justify-self-end' => 'justify-self-end',
            'center' => 'justify-self-center',
            'justify-self-center' => 'justify-self-center',
            'stretch' => 'justify-self-stretch',
            'justify-self-stretch' => 'justify-self-stretch',
        ]);
    }

    public function alignContent(string|int|float $value): static
    {
        return $this->applyUtilityProperty($value, 'content', 'alignContent', [
            'start' => 'content-start',
            'flex-start' => 'content-start',
            'content-start' => 'content-start',
            'end' => 'content-end',
            'flex-end' => 'content-end',
            'content-end' => 'content-end',
            'center' => 'content-center',
            'content-center' => 'content-center',
            'between' => 'content-between',
            'space-between' => 'content-between',
            'content-between' => 'content-between',
            'around' => 'content-around',
            'space-around' => 'content-around',
            'content-around' => 'content-around',
            'evenly' => 'content-evenly',
            'space-evenly' => 'content-evenly',
            'content-evenly' => 'content-evenly',
            'baseline' => 'content-baseline',
            'content-baseline' => 'content-baseline',
            'stretch' => 'content-stretch',
            'content-stretch' => 'content-stretch',
            'normal' => 'content-normal',
            'content-normal' => 'content-normal',
        ]);
    }

    public function alignItems(string|int|float $value): static
    {
        return $this->applyUtilityProperty($value, 'items', 'alignItems', [
            'start' => 'items-start',
            'flex-start' => 'items-start',
            'items-start' => 'items-start',
            'end' => 'items-end',
            'flex-end' => 'items-end',
            'items-end' => 'items-end',
            'center' => 'items-center',
            'items-center' => 'items-center',
            'baseline' => 'items-baseline',
            'items-baseline' => 'items-baseline',
            'stretch' => 'items-stretch',
            'items-stretch' => 'items-stretch',
        ]);
    }

    public function alignSelf(string|int|float $value): static
    {
        return $this->applyUtilityProperty($value, 'self', 'alignSelf', [
            'auto' => 'self-auto',
            'self-auto' => 'self-auto',
            'start' => 'self-start',
            'flex-start' => 'self-start',
            'self-start' => 'self-start',
            'end' => 'self-end',
            'flex-end' => 'self-end',
            'self-end' => 'self-end',
            'center' => 'self-center',
            'self-center' => 'self-center',
            'baseline' => 'self-baseline',
            'self-baseline' => 'self-baseline',
            'stretch' => 'self-stretch',
            'self-stretch' => 'self-stretch',
        ]);
    }

    protected function applyUtilityProperty(
        string|int|float $value,
        string $classPrefix,
        string $styleProperty,
        array $aliases = []
    ): static {
        if (is_int($value) || is_float($value)) {
            return $this->style([
                $styleProperty => $value.'px',
            ]);
        }

        $raw = trim($value);
        if ($raw === '') {
            return $this;
        }

        $normalized = strtolower($raw);
        if (isset($aliases[$normalized])) {
            return $this->appendClass($aliases[$normalized]);
        }

        if (str_starts_with($normalized, $classPrefix.'-')) {
            return $this->appendClass($normalized);
        }

        return $this->style([
            $styleProperty => $raw,
        ]);
    }

    protected function shouldTreatAsCssValue(string $value): bool
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return false;
        }

        if (in_array($normalized, self::CSS_GLOBAL_VALUES, true)) {
            return true;
        }

        if (preg_match('/^-?\d+(\.\d+)?(px|rem|em|%|vw|vh|vmin|vmax|ch|ex)$/i', $normalized)) {
            return true;
        }

        if (str_contains($normalized, ' ')) {
            return true;
        }

        return str_starts_with($normalized, 'var(')
            || str_starts_with($normalized, 'calc(')
            || str_starts_with($normalized, 'clamp(')
            || str_starts_with($normalized, 'min(')
            || str_starts_with($normalized, 'max(');
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
