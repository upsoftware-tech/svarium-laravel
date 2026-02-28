<?php

namespace Upsoftware\Svarium\Panel\Resource\Operations;

use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;

class ResourceDuplicateOperation extends Operation
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
        return ExecutionMode::DUPLICATE;
    }

    public function authorize(PanelContext $context): bool
    {
        return (bool) $this->resource()->canDuplicate($context);
    }

    protected function schema(PanelContext $context, Model $record): array
    {
        $resource = $this->resource();
        $this->applyTitleIfEmpty($resource->duplicateTitle($context, $record));

        $clone = $record->replicate();

        if (method_exists($resource, 'duplicateForm')) {
            return $resource->duplicateForm($clone);
        }

        return $resource->form($clone);
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

    protected function save(PanelContext $context, Model $record): RedirectResult
    {
        $resource = $this->resource();

        $clone = $record->replicate();

        $schema = $this->getSchema($context, $record);
        $schema = $this->filterByOperation($schema, $context);

        $fieldNames = $this->collectFieldNames($schema);

        $data = collect($context->all())
            ->only($fieldNames)
            ->toArray();

        $clone->fill($data);

        $clone->save();

        $slug = $resource::slug();
        $panelPrefix = trim($context->panel()->prefixName(), '/');

        $base = $panelPrefix
            ? "{$panelPrefix}/{$slug}"
            : $slug;

        $action = $context->input->get('_action');

        return match ($action) {

            'save_and_edit' => RedirectResult::to(
                "{$base}/{$clone->getKey()}/edit"
            )->success('Skopiowano rekord'),

            'save_and_new' => RedirectResult::to(
                "{$base}/create"
            )->success('Skopiowano rekord'),

            default => RedirectResult::to(
                "{$base}"
            )->success('Skopiowano rekord'),
        };
    }
}
