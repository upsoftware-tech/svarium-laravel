<?php

namespace Upsoftware\Svarium\Panel\Resource\Operations;

use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\OperationResult;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Table\BulkAction;
use Upsoftware\Svarium\Panel\Table\TableBuilder;

class ResourceListOperation extends Operation
{
    protected string $resourceClass;

    public function setResource(string $resourceClass): void
    {
        $this->resourceClass = $resourceClass;
    }

    public function for(string $resourceClass): void
    {
        $this->resourceClass = $resourceClass;
    }

    public static function methods(): array
    {
        return ['GET', 'POST'];
    }

    protected function resource()
    {
        return app($this->resourceClass);
    }

    public function execution(): ExecutionMode
    {
        return ExecutionMode::TABLE;
    }

    public function table(PanelContext $context): ?TableBuilder
    {
        $resource = $this->resource();
        $builder = $resource->table();

        if (method_exists($resource, 'canDelete') && ! $resource->canDelete($context)) {
            $builder->disableDefaultActions(['delete']);
            $builder->disableDefaultBulkActions(['delete']);
        }

        if (method_exists($resource, 'canDuplicate') && ! $resource->canDuplicate($context)) {
            $builder->disableDefaultActions(['duplicate']);
            $builder->disableDefaultBulkActions(['duplicate']);
        }

        return $builder;
    }

    protected function handleTable(PanelContext $context, ...$args): OperationResult
    {
        if (! $context->isPost()) {
            return parent::handleTable($context, ...$args);
        }

        $builder = $this->table($context);

        if (! $builder) {
            return parent::handleTable($context, ...$args);
        }

        $this->applyTableAccess($builder, $context);

        $bulkAction = trim((string) $context->input->get('_bulk_action', ''));

        if ($bulkAction === '') {
            return parent::handleTable($context, ...$args);
        }

        return $this->runBulkAction($context, $builder, $bulkAction);
    }

    protected function runBulkAction(PanelContext $context, TableBuilder $builder, string $bulkActionKey): RedirectResult
    {
        $actionsByKey = [];

        foreach ($builder->resolveBulkActions() as $action) {
            if (! $action instanceof BulkAction) {
                continue;
            }

            $actionsByKey[$action->getKey()] = $action;
        }

        if (! isset($actionsByKey[$bulkActionKey])) {
            return RedirectResult::to($this->listUrl($context))
                ->error(__('Nieznana akcja masowa.'));
        }

        $selection = $this->normalizeSelection(
            $context->input->get('_bulk_selection', $context->input->get('bulk_row_selection', []))
        );

        if ($selection === []) {
            return RedirectResult::to($this->listUrl($context))
                ->warning(__('Zaznacz co najmniej jeden rekord.'));
        }

        $resource = $this->resource();

        if ($bulkActionKey === 'delete' && method_exists($resource, 'canDelete') && ! $resource->canDelete($context)) {
            return RedirectResult::to($this->listUrl($context))
                ->error(__('Brak uprawnień do usuwania rekordów.'));
        }

        if ($bulkActionKey === 'duplicate' && method_exists($resource, 'canDuplicate') && ! $resource->canDuplicate($context)) {
            return RedirectResult::to($this->listUrl($context))
                ->error(__('Brak uprawnień do duplikowania rekordów.'));
        }

        $action = $actionsByKey[$bulkActionKey];
        $affected = $action->run(clone $builder->getQuery(), $selection, $context, $resource);

        return RedirectResult::to($this->listUrl($context))
            ->success($action->resolveSuccessMessage($affected));
    }

    protected function normalizeSelection(mixed $selection): array
    {
        if (is_scalar($selection)) {
            $selection = [$selection];
        }

        if (! is_array($selection)) {
            return [];
        }

        $normalized = [];

        array_walk_recursive($selection, static function ($value) use (&$normalized): void {
            if (! is_scalar($value)) {
                return;
            }

            $id = trim((string) $value);

            if ($id !== '') {
                $normalized[] = $id;
            }
        });

        return array_values(array_unique($normalized));
    }

    protected function listUrl(PanelContext $context): string
    {
        $slug = $this->resource()::slug();
        $panelPrefix = trim($context->panel()->prefixName(), '/');

        return $panelPrefix
            ? "{$panelPrefix}/{$slug}"
            : $slug;
    }
}
