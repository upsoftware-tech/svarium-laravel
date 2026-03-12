<?php

namespace Upsoftware\Svarium\UI\Components\Form;

use Upsoftware\Svarium\UI\Components\FieldComponent;

class InputFile extends FieldComponent
{
    public function autostart(bool $enabled = true): static
    {
        return $this->prop('autostart', $enabled);
    }

    public function extensions(array|string $extensions): static
    {
        $values = is_array($extensions) ? $extensions : [$extensions];
        $normalized = array_values(array_filter(array_map(function (mixed $value): string {
            $extension = trim((string) $value);
            $extension = ltrim($extension, '.');

            return strtolower($extension);
        }, $values), static fn (string $extension): bool => $extension !== ''));

        return $this->prop('extensions', $normalized);
    }

    public function fileType(array|string $type): static
    {
        $values = is_array($type) ? $type : [$type];
        $normalized = array_values(array_filter(array_map(function (mixed $value): string {
            return strtolower(trim((string) $value));
        }, $values), static fn (string $value): bool => $value !== ''));

        return $this->prop('fileType', $normalized);
    }

    public function uploadUrl(string $url): static
    {
        return $this->prop('uploadUrl', trim($url));
    }

    public function panelHref(string $path = '', ?string $panel = null): static
    {
        return $this->uploadUrl(panel_href($path, $panel));
    }

    public function afterUpload(string|array $afterUpload, mixed $payload = null): static
    {
        if (is_array($afterUpload)) {
            return $this->prop('afterUpload', $afterUpload);
        }

        $value = trim($afterUpload);
        if ($value === '') {
            return $this;
        }

        if (preg_match('/^(https?:)?\/\//i', $value) === 1 || str_starts_with($value, '/')) {
            return $this->afterUploadRedirect($value);
        }

        return $this->afterUploadEvent($value, $payload);
    }

    public function afterUploadEvent(string|array $event, mixed $payload = null, string $target = 'window'): static
    {
        if (is_array($event)) {
            return $this->prop('afterUpload', [
                ...$event,
                'type' => 'event',
            ]);
        }

        $eventName = trim($event);
        if ($eventName === '') {
            return $this;
        }

        $normalizedTarget = strtolower(trim($target));
        if (! in_array($normalizedTarget, ['window', 'document'], true)) {
            $normalizedTarget = 'window';
        }

        return $this->prop('afterUpload', [
            'type' => 'event',
            'name' => $eventName,
            'payload' => $payload,
            'target' => $normalizedTarget,
        ]);
    }

    public function afterUploadRedirect(string $url): static
    {
        $value = trim($url);
        if ($value === '') {
            return $this;
        }

        return $this->prop('afterUpload', [
            'type' => 'redirect',
            'url' => $value,
        ]);
    }

    public function afterUploadPanelHref(string $path = '', ?string $panel = null): static
    {
        return $this->afterUploadRedirect(panel_href($path, $panel));
    }

    public function multiple(bool $enabled = true): static
    {
        return $this->prop('multiple', $enabled);
    }

    public function progress(bool $enabled = true): static
    {
        return $this->prop('progress', $enabled);
    }

    public function preview(bool|array $config = true): static
    {
        if (is_array($config)) {
            return $this->prop('preview', [
                ...$config,
                'enabled' => (bool) ($config['enabled'] ?? true),
            ]);
        }

        return $this->prop('preview', [
            'enabled' => $config,
        ]);
    }

    public function previewLayout(string $layout): static
    {
        $value = strtolower(trim($layout));
        if (! in_array($value, ['list', 'grid'], true)) {
            $value = 'list';
        }

        return $this->prop('previewLayout', $value);
    }

    public function previewPosition(string $position): static
    {
        $value = strtolower(trim($position));
        if (! in_array($value, ['top', 'bottom'], true)) {
            $value = 'bottom';
        }

        return $this->prop('previewPosition', $value);
    }

    public function previewColumns(int|string $columns): static
    {
        return $this->prop('previewColumns', max(1, (int) $columns));
    }

    public function previewImportTile(bool $enabled = true): static
    {
        return $this->prop('previewImportTile', $enabled);
    }

    public function previewImportTilePosition(string $position): static
    {
        $value = strtolower(trim($position));
        if (! in_array($value, ['first', 'last'], true)) {
            $value = 'last';
        }

        return $this->prop('previewImportTilePosition', $value);
    }

    public function maxFile(int|string $count): static
    {
        return $this->prop('maxFile', max(1, (int) $count));
    }
}

