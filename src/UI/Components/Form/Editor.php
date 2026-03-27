<?php

namespace Upsoftware\Svarium\UI\Components\Form;

class Editor extends Textarea
{
    public function __construct(?string $name = null)
    {
        parent::__construct($name);

        $this->editor('tiptap');
    }

    public function variables(array $variables): static
    {
        $normalized = array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $variables
        ), static fn (string $item): bool => $item !== ''));

        return $this->prop('variables', $normalized);
    }

    public function variableGroups(array $groups): static
    {
        $normalized = array_values(array_filter(array_map(
            static function (mixed $group): ?array {
                if (! is_array($group)) {
                    return null;
                }

                $label = trim((string) ($group['label'] ?? ''));
                $items = $group['items'] ?? [];

                if (! is_array($items)) {
                    return null;
                }

                $normalizedItems = array_values(array_filter(array_map(
                    static function (mixed $item): ?array {
                        if (is_string($item) || is_numeric($item)) {
                            $value = trim((string) $item);

                            if ($value === '') {
                                return null;
                            }

                            return [
                                'value' => $value,
                                'label' => $value,
                            ];
                        }

                        if (! is_array($item)) {
                            return null;
                        }

                        $value = trim((string) ($item['value'] ?? ''));
                        $label = trim((string) ($item['label'] ?? $value));

                        if ($value === '') {
                            return null;
                        }

                        return [
                            'value' => $value,
                            'label' => $label !== '' ? $label : $value,
                        ];
                    },
                    $items
                ), static fn (?array $item): bool => $item !== null));

                if ($normalizedItems === []) {
                    return null;
                }

                return [
                    'label' => $label !== '' ? $label : 'Variables',
                    'items' => $normalizedItems,
                ];
            },
            $groups
        ), static fn (?array $group): bool => $group !== null));

        return $this->prop('variableGroups', $normalized);
    }

    public function placeholders(array $placeholders): static
    {
        return $this->variables($placeholders);
    }
}
