<?php

namespace Upsoftware\Svarium\UI\Components;

class TreeSortable extends Sortable
{
    public function columns(array $columns): static
    {
        return $this->prop('columns', $this->normalizeColumns($columns));
    }

    public function header(array $columns): static
    {
        return $this->columns($columns);
    }

    protected function normalizeColumns(array $columns): array
    {
        $normalized = [];

        foreach ($columns as $key => $column) {
            if (is_string($column)) {
                $value = trim($column);
                if ($value === '') {
                    continue;
                }

                $normalized[] = [
                    'key' => is_string($key) ? trim($key) : '',
                    'label' => $value,
                ];

                continue;
            }

            if (! is_array($column)) {
                continue;
            }

            $columnKey = trim((string) ($column['key'] ?? (is_string($key) ? $key : '')));
            $columnLabel = trim((string) ($column['label'] ?? $columnKey));

            if ($columnLabel === '') {
                continue;
            }

            $item = [
                'key' => $columnKey,
                'label' => $columnLabel,
            ];

            if (isset($column['width']) && (is_string($column['width']) || is_int($column['width']) || is_float($column['width']))) {
                $item['width'] = $column['width'];
            }

            if (isset($column['align']) && is_string($column['align'])) {
                $item['align'] = trim($column['align']);
            }

            if (isset($column['class']) && is_string($column['class'])) {
                $item['class'] = trim($column['class']);
            }

            $normalized[] = $item;
        }

        return array_values($normalized);
    }
}
