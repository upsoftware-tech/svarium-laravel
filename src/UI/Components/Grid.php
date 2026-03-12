<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Appearance;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class Grid extends Component
{
    use HasChildren;

    public static function make(Component|array|string|int|float|bool|null $content = null): static
    {
        $instance = parent::make()->appendAppearanceClass('grid');

        if ($content === null) {
            return $instance;
        }

        if ($content instanceof Component || is_array($content)) {
            $instance->children($content);

            return $instance;
        }

        $instance->child(Text::make((string) $content));

        return $instance;
    }

    public function appearance(array|Appearance|string $appearance): static
    {
        parent::appearance($appearance);

        return $this->appendAppearanceClass('grid');
    }

    public function class(string $class): static
    {
        return $this->appendAppearanceClasses($class);
    }

    public function inline(bool $enabled = true): static
    {
        return $enabled
            ? $this->appendAppearanceClass('inline-grid')
            : $this->appendAppearanceClass('grid');
    }

    public function columns(string|int|array $columns): static
    {
        return $this->cols($columns);
    }

    public function cols(string|int|array $cols): static
    {
        return $this->prop('cols', $this->normalizeResponsiveScale($cols));
    }

    public function col(string $breakpoint, string|int $cols): static
    {
        $key = $this->normalizeBreakpoint($breakpoint);
        $scale = $this->normalizeScaleValue($cols);

        if ($key === null || $scale === null) {
            return $this;
        }

        $current = $this->getProp('cols', []);
        if (! is_array($current)) {
            $current = [];
        }

        $current[$key] = $scale;

        return $this->prop('cols', $current);
    }

    public function colXs(string|int $cols): static
    {
        return $this->col('xs', $cols);
    }

    public function colSm(string|int $cols): static
    {
        return $this->col('sm', $cols);
    }

    public function colMd(string|int $cols): static
    {
        return $this->col('md', $cols);
    }

    public function colLg(string|int $cols): static
    {
        return $this->col('lg', $cols);
    }

    public function colXl(string|int $cols): static
    {
        return $this->col('xl', $cols);
    }

    public function col2xl(string|int $cols): static
    {
        return $this->col('2xl', $cols);
    }

    public function rows(string|int|array $rows): static
    {
        return $this->prop('rows', $this->normalizeResponsiveScale($rows));
    }

    public function row(string $breakpoint, string|int $rows): static
    {
        $key = $this->normalizeBreakpoint($breakpoint);
        $scale = $this->normalizeScaleValue($rows);

        if ($key === null || $scale === null) {
            return $this;
        }

        $current = $this->getProp('rows', []);
        if (! is_array($current)) {
            $current = [];
        }

        $current[$key] = $scale;

        return $this->prop('rows', $current);
    }

    public function rowXs(string|int $rows): static
    {
        return $this->row('xs', $rows);
    }

    public function rowSm(string|int $rows): static
    {
        return $this->row('sm', $rows);
    }

    public function rowMd(string|int $rows): static
    {
        return $this->row('md', $rows);
    }

    public function rowLg(string|int $rows): static
    {
        return $this->row('lg', $rows);
    }

    public function rowXl(string|int $rows): static
    {
        return $this->row('xl', $rows);
    }

    public function row2xl(string|int $rows): static
    {
        return $this->row('2xl', $rows);
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

    public function header(
        Component|array|string|\Closure|null $content,
        string $position = 'after'
    ): static
    {
        if (strtolower(trim($position)) === 'before') {
            return $this->slot('header_before', $content, 'header', 'before');
        }

        return $this->slot('header', $content);
    }

    public function body(Component|array|string|\Closure|null $content): static
    {
        return $this->slot('body', $content);
    }

    public function footer(
        Component|array|string|\Closure|null $content,
        string $position = 'after'
    ): static
    {
        if (strtolower(trim($position)) === 'before') {
            return $this->slot('footer_before', $content, 'footer', 'before');
        }

        return $this->slot('footer', $content);
    }

    public function top(
        Component|array|string|\Closure|null $content,
        string $position = 'after'
    ): static
    {
        return $this->slot('top', $content, 'header', $position);
    }

    public function bottom(
        Component|array|string|\Closure|null $content,
        string $position = 'before'
    ): static
    {
        return $this->slot('bottom', $content, 'footer', $position);
    }

    protected function normalizeResponsiveScale(string|int|array $value): array
    {
        if (! is_array($value)) {
            return ['default' => $this->normalizeScaleValue($value)];
        }

        $normalized = [];

        foreach ($value as $breakpoint => $cols) {
            $key = $this->normalizeBreakpoint((string) $breakpoint);
            $scale = $this->normalizeScaleValue($cols);

            if ($key === null || $scale === null) {
                continue;
            }

            $normalized[$key] = $scale;
        }

        return $normalized;
    }

    protected function normalizeBreakpoint(string $breakpoint): ?string
    {
        $value = strtolower(trim($breakpoint));

        return match ($value) {
            '', 'default', 'base', 'xs' => 'default',
            'sm' => 'sm',
            'md' => 'md',
            'lg' => 'lg',
            'xl' => 'xl',
            '2xl', 'xxl' => 'xxl',
            default => null,
        };
    }

    protected function normalizeScaleValue(string|int|float|bool|null $value): ?int
    {
        if (is_bool($value) || $value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        $result = (int) $normalized;

        if ($result < 1 || $result > 12) {
            return null;
        }

        return $result;
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
