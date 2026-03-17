<?php

namespace Upsoftware\Svarium\UI\Components\Form;

use Upsoftware\Svarium\UI\Components\FieldComponent;

class SelectIcon extends FieldComponent
{
    public function __construct(?string $name = null)
    {
        parent::__construct($name);

        $configuredCollections = config('upsoftware.components.select_icon.collections', ['lucide']);
        if (is_array($configuredCollections) && $configuredCollections !== []) {
            $this->collections($configuredCollections);
        }

        $configuredIcons = config('upsoftware.components.select_icon.icons', []);
        $normalizedIcons = $this->normalizeConfiguredIcons($configuredIcons);

        if ($normalizedIcons !== []) {
            $this->icons($normalizedIcons);
        }
    }

    public function icons(array $icons): static
    {
        return $this->prop('icons', $icons);
    }

    public function collections(array $collections): static
    {
        return $this->prop('collections', array_values(array_filter(array_map(
            static fn ($collection): string => is_string($collection) ? trim($collection) : '',
            $collections
        ))));
    }

    public function placeholder(string $placeholder): static
    {
        return $this->prop('placeholder', $placeholder);
    }

    public function clear(bool $enabled = true): static
    {
        return $this->prop('clear', $enabled);
    }

    public function searchable(bool $enabled = true): static
    {
        return $this->prop('searchable', $enabled);
    }

    protected function normalizeConfiguredIcons(mixed $configured): array
    {
        if (! is_array($configured)) {
            return [];
        }

        if ($configured === []) {
            return [];
        }

        if (array_is_list($configured)) {
            return array_values(array_filter(array_map(
                static fn ($icon): string => is_string($icon) ? trim($icon) : '',
                $configured
            )));
        }

        $result = [];

        foreach ($configured as $collection => $icons) {
            $prefix = is_string($collection) ? trim($collection) : '';
            if ($prefix === '' || ! is_array($icons)) {
                continue;
            }

            foreach ($icons as $icon) {
                if (! is_string($icon)) {
                    continue;
                }

                $normalized = trim($icon);
                if ($normalized === '') {
                    continue;
                }

                $result[] = str_contains($normalized, ':')
                    ? $normalized
                    : "{$prefix}:{$normalized}";
            }
        }

        return array_values(array_unique($result));
    }
}
