<?php

namespace Upsoftware\Svarium\Panel\Resource\Operations;

use Illuminate\Http\UploadedFile;
use Throwable;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Panel\FieldAttributesRegistry;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Services\ResourceImportService;
use Upsoftware\Svarium\Support\FilePasswordProtectionDetector;
use Upsoftware\Svarium\UI\Components\Button;
use Upsoftware\Svarium\UI\Components\Form\InputFile;
use Upsoftware\Svarium\UI\Components\ImportPreview;
use Upsoftware\Svarium\UI\Components\Text;

class ResourceImportOperation extends Operation
{
    protected string $resourceClass;

    protected bool $hasPreview = false;

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

    public static function methods(): array
    {
        return ['GET', 'POST'];
    }

    public function execution(): ExecutionMode
    {
        return ExecutionMode::FORM;
    }

    public function authorize(PanelContext $context): bool
    {
        $resource = $this->resource();

        return ! method_exists($resource, 'canImport') || (bool) $resource->canImport($context);
    }

    protected function hasSubmit(): bool
    {
        return false;
    }

    protected function formActions(): array
    {
        $actions = [
            Button::make(__('Preview data'))
                ->type('submit')
                ->name('_action')
                ->value('preview')
                ->variant('outline'),
        ];

        if ($this->hasPreview) {
            $actions[] = Button::make(__('Import data'))
                ->type('submit')
                ->name('_action')
                ->value('import');

            $actions[] = Button::make(__('Clear preview'))
                ->type('submit')
                ->name('_action')
                ->value('clear_preview')
                ->variant('ghost');
        }

        return $actions;
    }

    protected function schema(PanelContext $context): array
    {
        $context->setOperationType('import');
        $resource = $this->resource();
        $this->applyTitleIfEmpty($resource->importTitle($context));
        $fieldRegistry = app(FieldAttributesRegistry::class);
        $fieldRegistry->setDefinitions($resource->fields());

        try {
            $state = $this->previewState($context);
            $this->hasPreview = $this->hasValidPreview($state);

            $components = [
                Text::make(__('Data import'))->headline('h2')->appearance('text-lg font-semibold'),
                Text::make(__('Select the file you want to import.'))->appearance('text-sm text-slate-500'),
                InputFile::make('import_file')
                    ->label(__('Import file'))
                    ->hint(__('Select the file you want to import.'))
                    ->extensions(['csv', 'tsv', 'xlsx', 'xls', 'ods', 'json', 'xml', 'sql'])
                    ->multiple(false)
                    ->preview(true)
                    ->progress(false)
                    ->maxFile(1),
            ];

            if ($this->hasPreview) {
                $components[] = ImportPreview::make()
                    ->title(__('Import preview'))
                    ->headers((array) ($state['headers'] ?? []))
                    ->rows((array) ($state['rows'] ?? []))
                    ->totalRows((int) ($state['totalRows'] ?? 0))
                    ->importableRows((int) ($state['importableRows'] ?? 0))
                    ->previewRows((int) ($state['previewRows'] ?? 0))
                    ->maxHeight('420px');
            }

            return $components;
        } finally {
            $fieldRegistry->clear();
        }
    }

    protected function save(PanelContext $context): RedirectResult
    {
        $action = trim((string) $context->input->get('_action', 'preview'));
        $resource = $this->resource();

        if ($action === 'clear_preview') {
            session()->forget($this->previewSessionKey($context));

            return RedirectResult::to($this->importUrl($context))
                ->info(__('Preview has been cleared.'));
        }

        if ($action === 'import') {
            $state = $this->previewState($context);
            if (! $this->hasValidPreview($state)) {
                return RedirectResult::to($this->importUrl($context))
                    ->warning(__('Generate preview before import.'));
            }

            /** @var ResourceImportService $importer */
            $importer = app(ResourceImportService::class);
            $result = $importer->importFromStored($resource, $state);
            session()->forget($this->previewSessionKey($context));

            return $this->finalizeImportResult($context, $result);
        }

        $file = $this->firstImportFile($context);
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return RedirectResult::to($this->importUrl($context))
                ->warning(__('Choose a file to import.'));
        }

        /** @var FilePasswordProtectionDetector $detector */
        $detector = app(FilePasswordProtectionDetector::class);
        try {
            if ($detector->isPasswordProtected($file)) {
                $name = trim((string) $file->getClientOriginalName());
                if ($name === '') {
                    $name = __('The selected file');
                }

                return RedirectResult::to($this->importUrl($context))
                    ->warning(__('The file ":name" is password protected and cannot be imported.', ['name' => $name]));
            }
        } catch (Throwable) {
            // Ignore detector errors and continue with preview.
        }

        /** @var ResourceImportService $importer */
        $importer = app(ResourceImportService::class);
        $preview = $importer->previewAndStore($resource, $file, 30);

        if (isset($preview['error'])) {
            return RedirectResult::to($this->importUrl($context))
                ->error((string) $preview['error']);
        }

        if (isset($preview['warning'])) {
            return RedirectResult::to($this->importUrl($context))
                ->warning((string) $preview['warning']);
        }

        $state = (array) ($preview['state'] ?? []);
        $state['headers'] = (array) ($preview['headers'] ?? []);
        $state['rows'] = (array) ($preview['rows'] ?? []);
        $state['totalRows'] = (int) ($preview['totalRows'] ?? 0);
        $state['importableRows'] = (int) ($preview['importableRows'] ?? 0);
        $state['previewRows'] = (int) ($preview['previewRows'] ?? 0);

        session([$this->previewSessionKey($context) => $state]);

        return RedirectResult::to($this->importUrl($context))
            ->success(__('Preview is ready.'));
    }

    protected function finalizeImportResult(PanelContext $context, mixed $result): RedirectResult
    {
        $redirect = $this->listUrl($context);
        $response = RedirectResult::to($redirect);

        if (! is_array($result)) {
            return $response->success(__('Import completed.'));
        }

        foreach (['success', 'info', 'warning', 'error'] as $type) {
            $message = $result[$type] ?? null;
            if (! is_string($message) || trim($message) === '') {
                continue;
            }

            return $response->{$type}($message);
        }

        return $response->success(__('Import completed.'));
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

    protected function previewSessionKey(PanelContext $context): string
    {
        $panel = trim((string) $context->panel()->name, '/');
        $slug = trim((string) $this->resource()::slug(), '/');

        return "svarium.resource_import_preview.{$panel}.{$slug}";
    }

    protected function previewState(PanelContext $context): array
    {
        $state = session($this->previewSessionKey($context), []);

        return is_array($state) ? $state : [];
    }

    protected function hasValidPreview(array $state): bool
    {
        return trim((string) ($state['path'] ?? '')) !== '';
    }

    protected function listUrl(PanelContext $context): string
    {
        $slug = trim((string) $this->resource()::slug(), '/');
        $prefix = trim($context->panel()->prefixName(), '/');

        return $prefix !== '' ? "{$prefix}/{$slug}" : $slug;
    }

    protected function importUrl(PanelContext $context): string
    {
        $slug = trim((string) $this->resource()::slug(), '/');
        $prefix = trim($context->panel()->prefixName(), '/');
        $path = "{$slug}/import";

        return $prefix !== '' ? "{$prefix}/{$path}" : $path;
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
