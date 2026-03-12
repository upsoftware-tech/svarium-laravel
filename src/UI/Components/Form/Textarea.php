<?php

namespace Upsoftware\Svarium\UI\Components\Form;

use Upsoftware\Svarium\UI\Components\FieldComponent;

class Textarea extends FieldComponent
{
    public function editor(string $editor): static
    {
        $normalized = strtolower(trim($editor));

        if ($normalized === '') {
            $normalized = 'plain';
        }

        return $this->prop('editor', $normalized);
    }

    public function tiptap(bool $enabled = true): static
    {
        return $this->editor($enabled ? 'tiptap' : 'plain');
    }

    public function placeholders(array $placeholders): static
    {
        $normalized = array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $placeholders
        ), static fn (string $item): bool => $item !== ''));

        return $this->prop('placeholders', $normalized);
    }
}
