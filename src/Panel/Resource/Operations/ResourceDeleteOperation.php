<?php

namespace Upsoftware\Svarium\Panel\Resource\Operations;

use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;

class ResourceDeleteOperation extends Operation
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
        return ExecutionMode::ACTION;
    }

    public function authorize(PanelContext $context): bool
    {
        return (bool) $this->resource()->canDelete($context);
    }

    protected function run(PanelContext $context, Model $record): RedirectResult
    {
        $resource = $this->resource();

        if (method_exists($resource, 'beforeDelete')) {
            $resource->beforeDelete($record);
        }

        $record->delete();

        if (method_exists($resource, 'afterDelete')) {
            $resource->afterDelete($record);
        }

        $slug = $resource::slug();
        $panelPrefix = trim($context->panel()->prefixName(), '/');

        $base = $panelPrefix
            ? "{$panelPrefix}/{$slug}"
            : $slug;

        return RedirectResult::to($base)
            ->success('Rekord usunięty');
    }
}
