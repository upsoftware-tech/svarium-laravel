<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class EmptyState extends Component
{
    use HasChildren;

    public static function make(?string $name = null): static
    {
        return parent::make($name);
    }

    public function icon(string|bool|null $icon = true): static
    {
        if (is_bool($icon) || $icon === null) {
            return $this->prop('showIcon', (bool) ($icon ?? true));
        }

        $value = trim($icon);

        if ($value === '') {
            return $this->prop('icon', '')
                ->prop('showIcon', false);
        }

        return $this->prop('icon', $value)
            ->prop('showIcon', true);
    }

    public function iconColor(string $color): static
    {
        return $this->prop('iconColor', trim($color));
    }

    public function title(string|int|float|bool|null $title): static
    {
        return $this->prop('title', $title === null ? '' : (string) $title);
    }

    public function badge(string|int|float|bool|null $badge): static
    {
        return $this->prop('badge', $badge === null ? '' : (string) $badge);
    }

    public function subtitle(string|int|float|bool|null $subtitle): static
    {
        return $this->prop('subtitle', $subtitle === null ? '' : (string) $subtitle);
    }

    public function description(string|int|float|bool|null $description): static
    {
        return $this->prop('description', $description === null ? '' : (string) $description);
    }

    public function descriontion(string|int|float|bool|null $description): static
    {
        // Backward-compatible alias kept intentionally (typo in public API usage).
        return $this->description($description);
    }

    public function toArray(): array
    {
        $array = parent::toArray();
        $array['type'] = 'Empty';

        return $array;
    }
}
