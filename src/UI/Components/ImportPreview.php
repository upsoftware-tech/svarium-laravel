<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class ImportPreview extends Component
{
    public static function make(?string $name = null): static
    {
        return new static;
    }

    public function title(?string $title): static
    {
        return $this->prop('title', (string) ($title ?? ''));
    }

    public function headers(array $headers): static
    {
        $normalized = array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $headers
        ), static fn (string $value): bool => $value !== ''));

        return $this->prop('headers', $normalized);
    }

    public function rows(array $rows): static
    {
        return $this->prop('rows', array_values($rows));
    }

    public function totalRows(?int $count): static
    {
        return $this->prop('totalRows', $count);
    }

    public function importableRows(?int $count): static
    {
        return $this->prop('importableRows', $count);
    }

    public function previewRows(?int $count): static
    {
        return $this->prop('previewRows', $count);
    }

    public function maxHeight(string $maxHeight): static
    {
        return $this->prop('maxHeight', trim($maxHeight));
    }
}
