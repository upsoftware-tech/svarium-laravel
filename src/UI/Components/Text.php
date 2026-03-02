<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class Text extends Component
{
    public static function make(?string $text = null): static
    {
        $instance = parent::make();

        if ($text !== null) {
            $instance->text($text);
        }

        return $instance;
    }

    public function text(string $text): static
    {
        return $this->prop('text', $text);
    }

    public function as(string $tag): static
    {
        $normalizedTag = $this->normalizeTag($tag);

        // Keep both keys for backward compatibility with existing frontend usage.
        $this->prop('as', $normalizedTag);

        return $this->prop('tag', $normalizedTag);
    }

    public function tag(string $tag): static
    {
        return $this->as($tag);
    }

    public function span(): static
    {
        return $this->as('span');
    }

    public function paragraph(): static
    {
        return $this->as('p');
    }

    public function b(): static
    {
        return $this->as('b');
    }

    public function strong(): static
    {
        return $this->as('strong');
    }

    public function i(): static
    {
        return $this->as('i');
    }

    public function em(): static
    {
        return $this->as('em');
    }

    public function mark(): static
    {
        return $this->as('mark');
    }

    public function small(): static
    {
        return $this->as('small');
    }

    public function del(): static
    {
        return $this->as('del');
    }

    public function ins(): static
    {
        return $this->as('ins');
    }

    public function sub(): static
    {
        return $this->as('sub');
    }

    public function sup(): static
    {
        return $this->as('sup');
    }

    public function headline(string|int $level = 'h2'): static
    {
        return $this->as($this->normalizeHeadlineTag($level));
    }

    public function class(string $class): static
    {
        return $this->prop('class', $class);
    }

    public function html(bool $enabled = true): static
    {
        return $this->prop('html', $enabled);
    }

    public function variant(string $variant): static
    {
        return $this->prop('variant', $variant);
    }

    public function content(array|Component|string $children): static
    {
        if (is_string($children)) {
            return $this->text($children);
        }

        return parent::content($children);
    }

    protected function normalizeTag(string $tag): string
    {
        $normalizedTag = strtolower(trim($tag));

        return $normalizedTag === '' ? 'span' : $normalizedTag;
    }

    protected function normalizeHeadlineTag(string|int $level): string
    {
        if (is_int($level)) {
            $normalizedLevel = max(1, min(6, $level));
            return "h{$normalizedLevel}";
        }

        $normalizedLevel = strtolower(trim($level));

        if (preg_match('/^h([1-6])$/', $normalizedLevel, $matches) === 1) {
            return "h{$matches[1]}";
        }

        if (ctype_digit($normalizedLevel)) {
            $numericLevel = max(1, min(6, (int) $normalizedLevel));
            return "h{$numericLevel}";
        }

        return 'h2';
    }
}
