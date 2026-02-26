<?php

namespace Upsoftware\Svarium\Panel\Resource\Operations;

use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;

class ResourceEditOperation extends Operation
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
        return app($this->resourceClass); // ← TU MA BYĆ $this
    }

    public function execution(): ExecutionMode
    {
        return ExecutionMode::FORM;
    }

    public function authorize(PanelContext $context): bool
    {
        return (bool) $this->resource()->canEdit($context);
    }

    protected function schema(PanelContext $context, Model $record): array
    {
        $context->setOperationType('edit');
        $resource = $this->resource();

        if (method_exists($resource, 'editForm')) {
            return $resource->editForm($record);
        }

        return $resource->form($record);
    }

    protected function save(PanelContext $context, Model $record): RedirectResult
    {
        $schema = $this->getSchema($context, $record);
        $schema = $this->filterByOperation($schema, $context);

        $fieldNames = $this->collectFieldNames($schema);

        $data = collect($context->all())
            ->only($fieldNames)
            ->toArray();
        $resource = $this->resource();

        if (method_exists($resource, 'beforeSave')) {
            $resource->beforeSave($record, $data);
        }

        $record->fill($data)->save();

        if (method_exists($resource, 'afterSave')) {
            $resource->afterSave($record);
        }

        $action = $context->input->get('_action', 'save_and_back');

        $slug = $resource::slug();
        $panelPrefix = trim($context->panel()->prefixName(), '/');

        $base = $panelPrefix
            ? "{$panelPrefix}/{$slug}"
            : $slug;
        $encodedId = $record->getKey();

        return match ($action) {

            'save_and_edit' => RedirectResult::to("{$base}/{$encodedId}/edit")
                ->success('Zapisano'),

            'save_and_new' => RedirectResult::to("{$base}/create")
                ->success('Zapisano'),

            default => RedirectResult::to($base)
                ->success('Zapisano'),
        };
    }
}
