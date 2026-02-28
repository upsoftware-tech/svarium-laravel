<?php

namespace Upsoftware\Svarium\Panel\Resource\Operations;

use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Security\RecordIdentifier;

class ResourceCreateOperation extends Operation
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
        return ExecutionMode::FORM;
    }

    public function authorize(PanelContext $context): bool
    {
        return (bool) $this->resource()->canCreate($context);
    }

    protected function schema(PanelContext $context): array
    {
        $context->setOperationType('create');
        $resource = $this->resource();
        $this->applyTitleIfEmpty($resource->createTitle($context));

        if (method_exists($resource, 'createForm')) {
            return $resource->createForm();
        }

        return $resource->form(null);
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

    protected function save(PanelContext $context): RedirectResult
    {
        $resource = $this->resource();
        $modelClass = $resource::model();

        $schema = $this->getSchema($context);
        $schema = $this->filterByOperation($schema, $context);

        $fieldNames = $this->collectFieldNames($schema);

        $data = collect($context->all())
            ->only($fieldNames)
            ->toArray();

        $record = new $modelClass;

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

        $encodedId = RecordIdentifier::encode(
            $resource::model(),
            $record->getKey()
        );

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
