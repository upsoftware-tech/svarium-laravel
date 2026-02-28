<?php

namespace Upsoftware\Svarium\Panel\Resource\Operations;

use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\UI\Components\FieldComponent;

class ResourcePreviewOperation extends Operation
{
    protected string $resourceClass;

    public function setResource(string $resourceClass): void
    {
        $this->resourceClass = $resourceClass;
    }

    public function getResourceClass(): string
    {
        return $this->resourceClass;
    }

    protected function resource()
    {
        return app($this->resourceClass);
    }

    public function execution(): ExecutionMode
    {
        return ExecutionMode::VIEW;
    }

    public function authorize(PanelContext $context): bool
    {
        return (bool) $this->resource()->canPreview($context);
    }

    protected function schema(PanelContext $context, Model $record): array
    {
        // Preview reuses resource form schema, so match edit visibility rules
        // (e.g. ->onlyOn(['create', 'edit'])) and render fields readonly.
        $context->setOperationType('edit');

        $resource = $this->resource();
        $this->applyTitleIfEmpty($resource->previewTitle($context, $record));

        $schema = $resource->previewForm($record);
        $schema = is_array($schema) ? $schema : [$schema];

        return $this->markReadonly($schema);
    }

    protected function markReadonly(array $components): array
    {
        foreach ($components as $component) {
            if (! is_object($component)) {
                continue;
            }

            if ($component instanceof FieldComponent) {
                $component->prop('readonly', true);
                $component->prop('disabled', true);
            }

            if (! empty($component->children) && is_array($component->children)) {
                $component->children = $this->markReadonly($component->children);
            }

            if (! empty($component->slots) && is_array($component->slots)) {
                foreach ($component->slots as $slot => $children) {
                    if (is_array($children)) {
                        $component->slots[$slot] = $this->markReadonly($children);
                    }
                }
            }
        }

        return $components;
    }

    protected function applyTitleIfEmpty(string $title): void
    {
        if (! function_exists('set_title') || ! function_exists('get_title')) {
            return;
        }

        if (trim((string) get_title()) !== '') {
            return;
        }

        set_title($title);
    }
}
