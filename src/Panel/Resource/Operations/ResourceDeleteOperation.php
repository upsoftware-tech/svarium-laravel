<?php

namespace Upsoftware\Svarium\Panel\Resource\Operations;

use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\JsonResult;
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

    public function apiRun(PanelContext $context, ...$args): mixed
    {
        $record = null;

        foreach ($args as $arg) {
            if ($arg instanceof Model) {
                $record = $arg;
                break;
            }
        }

        if (! $record instanceof Model) {
            return JsonResult::make([
                'status' => 'not_found',
                'message' => __('Record not found.'),
            ], 404);
        }

        $resource = $this->resource();

        if (method_exists($resource, 'beforeDelete')) {
            $resource->beforeDelete($record);
        }

        $deletedId = $record->getKey();
        $record->delete();

        if (method_exists($resource, 'afterDelete')) {
            $resource->afterDelete($record);
        }

        return JsonResult::make([
            'status' => 'deleted',
            'id' => $deletedId,
        ]);
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
