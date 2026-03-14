<?php

namespace Upsoftware\Svarium\Panel\Resource\Operations;

use Illuminate\Http\UploadedFile;
use Throwable;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\JsonResult;
use Upsoftware\Svarium\Http\OperationResult;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\FieldAttributesRegistry;
use Upsoftware\Svarium\Panel\Table\BulkAction;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\Services\ResourceImportService;
use Upsoftware\Svarium\Support\FilePasswordProtectionDetector;

class ResourceListOperation extends Operation
{
    protected string $resourceClass;

    public function authorize(PanelContext $context): bool
    {
        $resource = $this->resource();

        if (method_exists($resource, 'canList')) {
            return (bool) $resource->canList($context);
        }

        return true;
    }

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
        $this->applyTitleIfEmpty($resource->listTitle($context));
        $fieldRegistry = app(FieldAttributesRegistry::class);
        $fieldRegistry->setDefinitions($resource->fields());
        $columnAttributes = $fieldRegistry->columnAttributes();

        try {
            $builder = $resource->table();
        } finally {
            $fieldRegistry->clear();
        }

        $builder->columnAttributes($columnAttributes);

        if (method_exists($resource, 'canPreview') && ! $resource->canPreview($context)) {
            $builder->disableDefaultActions(['view']);
        }

        if (method_exists($resource, 'canEdit') && ! $resource->canEdit($context)) {
            $builder->disableDefaultActions(['edit']);
        }

        if (method_exists($resource, 'canDelete') && ! $resource->canDelete($context)) {
            $builder->disableDefaultActions(['delete']);
            $builder->disableDefaultBulkActions(['delete']);
        }

        if (method_exists($resource, 'canDuplicate') && ! $resource->canDuplicate($context)) {
            $builder->disableDefaultActions(['duplicate']);
            $builder->disableDefaultBulkActions(['duplicate']);
        }

        if (method_exists($resource, 'canImport') && ! $resource->canImport($context)) {
            $builder->imported(false);
        }

        if (method_exists($resource, 'canExport') && ! $resource->canExport($context)) {
            $builder->exported(false);
        }

        $builder->exportUrl($this->exportUrl($context));

        return $builder;
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

        $tableAction = trim((string) $context->input->get('_table_action', ''));
        if ($tableAction === 'prepare_import') {
            return $this->runPrepareImportAction($context);
        }

        if ($tableAction === 'import') {
            return $this->runImportAction($context, $builder);
        }

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
                ->error(__('Unknown bulk action.'));
        }

        $selection = $this->normalizeSelection(
            $context->input->get('_bulk_selection', $context->input->get('bulk_row_selection', []))
        );

        if ($selection === []) {
            return RedirectResult::to($this->listUrl($context))
                ->warning(__('Select at least one record.'));
        }

        $resource = $this->resource();

        if ($bulkActionKey === 'delete' && method_exists($resource, 'canDelete') && ! $resource->canDelete($context)) {
            return RedirectResult::to($this->listUrl($context))
                ->error(__('No permission to delete selected records.'));
        }

        if ($bulkActionKey === 'duplicate' && method_exists($resource, 'canDuplicate') && ! $resource->canDuplicate($context)) {
            return RedirectResult::to($this->listUrl($context))
                ->error(__('No permission to duplicate selected records.'));
        }

        $action = $actionsByKey[$bulkActionKey];
        $affected = $action->run(clone $builder->getQuery(), $selection, $context, $resource);

        return RedirectResult::to($this->listUrl($context))
            ->success($action->resolveSuccessMessage($affected));
    }

    protected function runImportAction(PanelContext $context, TableBuilder $builder): RedirectResult
    {
        $resource = $this->resource();

        $fieldName = trim((string) $context->input->get('_import_field', 'import_file'));
        if ($fieldName === '') {
            $fieldName = 'import_file';
        }

        $files = $this->normalizeImportFiles(
            $context->request()->file($fieldName, $context->request()->file('import_file'))
        );

        if ($files === []) {
            $files = $this->normalizeImportFiles($context->request()->allFiles());
        }

        if ($files === []) {
            return RedirectResult::to($this->listUrl($context))
                ->warning(__('Choose a file to import.'));
        }

        $passwordProtected = $this->findFirstPasswordProtectedFile($files);
        if ($passwordProtected instanceof UploadedFile) {
            $name = trim((string) $passwordProtected->getClientOriginalName());
            if ($name === '') {
                $name = __('The selected file');
            }

            return RedirectResult::to($this->listUrl($context))
                ->warning(__('The file ":name" is password protected and cannot be imported.', ['name' => $name]));
        }

        try {
            if (method_exists($resource, 'import')) {
                $result = $resource->import($context, $files, $builder);
            } else {
                /** @var ResourceImportService $importer */
                $importer = app(ResourceImportService::class);
                $result = $importer->import($resource, $files);
            }
        } catch (Throwable $e) {
            report($e);

            return RedirectResult::to($this->listUrl($context))
                ->error(__('Import failed.'));
        }

        return $this->finalizeImportResult($context, $result);
    }

    protected function normalizeImportFiles(mixed $files): array
    {
        if ($files instanceof UploadedFile) {
            return $files->isValid() ? [$files] : [];
        }

        if (! is_array($files)) {
            return [];
        }

        $normalized = [];

        array_walk_recursive($files, static function ($file) use (&$normalized): void {
            if (! $file instanceof UploadedFile) {
                return;
            }

            if (! $file->isValid()) {
                return;
            }

            $normalized[] = $file;
        });

        return array_values($normalized);
    }

    protected function findFirstPasswordProtectedFile(array $files): ?UploadedFile
    {
        /** @var FilePasswordProtectionDetector $detector */
        $detector = app(FilePasswordProtectionDetector::class);

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            try {
                if ($detector->isPasswordProtected($file)) {
                    return $file;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    protected function finalizeImportResult(PanelContext $context, mixed $result): RedirectResult
    {
        if ($result instanceof RedirectResult) {
            return $result;
        }

        $redirect = $this->listUrl($context);

        if (is_array($result)) {
            $target = trim((string) ($result['redirect'] ?? ''));
            if ($target !== '') {
                $redirect = $target;
            }

            $response = RedirectResult::to($redirect);

            foreach (['success', 'info', 'warning', 'error'] as $type) {
                $message = $result[$type] ?? null;
                if (! is_string($message) || trim($message) === '') {
                    continue;
                }

                return $response->{$type}($message);
            }

            $message = $result['message'] ?? null;
            if (is_string($message) && trim($message) !== '') {
                return $response->success($message);
            }

            $count = $result['count'] ?? null;
            if (is_numeric($count)) {
                return $response->success(__('Imported :count record(s).', ['count' => (int) $count]));
            }

            return $response->success(__('Import completed.'));
        }

        if (is_string($result) && trim($result) !== '') {
            return RedirectResult::to($redirect)->success($result);
        }

        if (is_numeric($result)) {
            return RedirectResult::to($redirect)
                ->success(__('Imported :count record(s).', ['count' => (int) $result]));
        }

        if ($result === false) {
            return RedirectResult::to($redirect)->error(__('Import failed.'));
        }

        return RedirectResult::to($redirect)->success(__('Import completed.'));
    }

    protected function runPrepareImportAction(PanelContext $context): JsonResult
    {
        $file = $this->firstImportFile($context);

        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return JsonResult::make([
                'success' => false,
                'message' => __('Choose a file to import.'),
            ], 422);
        }

        /** @var FilePasswordProtectionDetector $detector */
        $detector = app(FilePasswordProtectionDetector::class);

        $isPasswordProtected = false;

        try {
            $isPasswordProtected = $detector->isPasswordProtected($file);
        } catch (Throwable) {
            $isPasswordProtected = false;
        }

        if ($isPasswordProtected) {
            $password = trim((string) $context->input->get('import_password', ''));

            if ($password === '') {
                return JsonResult::make([
                    'success' => true,
                    'requiresPassword' => true,
                    'message' => __('The selected file is password protected. Enter password to continue.'),
                ]);
            }

            if (! $this->validateProtectedFilePassword($file, $password)) {
                return JsonResult::make([
                    'success' => false,
                    'requiresPassword' => true,
                    'message' => __('Invalid file password.'),
                ], 422);
            }
        }

        return JsonResult::make([
            'success' => true,
            'redirect' => '/'.$this->importUrl($context),
        ]);
    }

    protected function validateProtectedFilePassword(UploadedFile $file, string $password): bool
    {
        $normalizedPassword = trim($password);
        if ($normalizedPassword === '') {
            return false;
        }

        // NOTE: current importer cannot decrypt protected spreadsheet content.
        // We only validate that password was provided to continue the flow to import page.
        return true;
    }

    protected function firstImportFile(PanelContext $context): ?UploadedFile
    {
        $file = $context->request()->file('import_file');

        if ($file instanceof UploadedFile) {
            return $file;
        }

        if (is_array($file)) {
            foreach ($file as $item) {
                if ($item instanceof UploadedFile) {
                    return $item;
                }
            }
        }

        return null;
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

    protected function importUrl(PanelContext $context): string
    {
        $slug = trim((string) $this->resource()::slug(), '/');
        $prefix = trim($context->panel()->prefixName(), '/');
        $path = "{$slug}/import";

        return $prefix !== '' ? "{$prefix}/{$path}" : $path;
    }

    protected function exportUrl(PanelContext $context): string
    {
        $slug = trim((string) $this->resource()::slug(), '/');
        $prefix = trim($context->panel()->prefixName(), '/');
        $path = "{$slug}/export";

        return $prefix !== '' ? "{$prefix}/{$path}" : $path;
    }
}
