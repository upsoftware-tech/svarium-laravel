<?php

namespace Upsoftware\Svarium\Panel\Resource\Operations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\JsonResult;
use Upsoftware\Svarium\Http\OperationResult;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Panel\FieldAttributesRegistry;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Resource\Operations\Concerns\InteractsWithResourceFormTabs;
use Upsoftware\Svarium\Panel\Resource\ResourceFormTab;

class ResourceEditOperation extends Operation
{
    use InteractsWithResourceFormTabs;

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

        $method = strtoupper($context->request()->method());

        if ($method === 'GET') {
            return JsonResult::make([
                'status' => 'ok',
                'data' => $record->toArray(),
            ]);
        }

        if (! in_array($method, ['PUT', 'PATCH', 'POST'], true)) {
            return null;
        }

        $schema = $this->getSchema($context, $record);
        $schema = $this->filterByOperation($schema, $context);
        [$messages, $attributes, $rules] = $this->resolveFormValidationPayload($context, $schema, $record);

        $context->validated = validator($context->request()->all(), $rules, $messages, $attributes)->validate();

        $fieldNames = $this->collectFieldNames($schema);
        $data = collect($context->all())->only($fieldNames)->toArray();

        $resource = $this->resource();

        if (method_exists($resource, 'beforeSave')) {
            $resource->beforeSave($record, $data);
        }

        $data = $this->normalizeLanguagePayloadForModel($data, $schema, $record);
        $record->fill($data)->save();

        if (method_exists($resource, 'afterSave')) {
            $resource->afterSave($record);
        }

        return JsonResult::make([
            'status' => 'updated',
            'data' => $record->fresh()?->toArray() ?? $record->toArray(),
        ]);
    }

    protected function formActions(): array
    {
        /** @var PanelContext $context */
        $context = app(PanelContext::class);
        $record = null;
        $id = $context->params['id'] ?? null;

        if ($id !== null) {
            $modelClass = $this->resource()::model();
            $record = $modelClass::query()->find($id);
        }

        return (array) $this->resource()->formActions($context, $record instanceof Model ? $record : null);
    }

    protected function handleForm(PanelContext $context, ...$args): OperationResult
    {
        if (! $context->isPost()) {
            return $this->render($context, ...$args);
        }

        $schema = $this->getSchema($context, ...$args);
        $schema = $this->filterByOperation($schema, $context);

        [$messages, $attributes, $rules] = $this->resolveFormValidationPayload($context, $schema, ...$args);

        try {
            $context->validated = validator($context->request()->all(), $rules, $messages, $attributes)->validate();
        } catch (ValidationException $e) {
            $errorFields = array_keys($e->errors());
            $record = $args[0] ?? null;
            if ($record instanceof Model) {
                $tabs = $this->resolveEditFormTabs($context, $record);
                $errorTabKey = $this->resolveTabKeyForValidationErrors(
                    $context,
                    $tabs,
                    $errorFields,
                    $record
                );
                if ((! is_string($errorTabKey) || trim($errorTabKey) === '') && $tabs !== []) {
                    $errorTabKey = $tabs[0]->key();
                }
                $context->params['__form_tab_error_fields'] = $errorFields;

                if (is_string($errorTabKey) && trim($errorTabKey) !== '') {
                    $context->request()->merge(['tab' => $errorTabKey]);
                    $context->params['tab'] = $errorTabKey;
                    $context->params['__form_tab_error_tab'] = $errorTabKey;
                    $context->params['__form_tab_error_nonce'] = (string) Str::uuid();
                }

                $this->debugFormTabs($context, 'edit_validation_error', [
                    'resolved_error_tab_key' => $errorTabKey,
                    'error_fields' => $errorFields,
                    'request_tab_input' => $context->input('tab'),
                    'params_tab' => $context->params['tab'] ?? null,
                ]);
            }

            $this->clearResolvedSchema();
            $result = $this->render($context, ...$args);
            $result->prop('errors', $e->errors());

            return $result;
        }

        $action = $context->input->get('_action');

        if ($action && array_key_exists((string) $action, $this->submitOptions())) {
            session()->put(static::class . '_submit_action', $action);
        }

        $result = $this->save($context, ...$args);

        return $result ?? $this->render($context, ...$args);
    }

    protected function schema(PanelContext $context, Model $record): array
    {
        $context->setOperationType('edit');
        $resource = $this->resource();
        $this->applyTitleIfEmpty($resource->editTitle($context, $record));
        $fieldRegistry = app(FieldAttributesRegistry::class);
        $fieldRegistry->setDefinitions($resource->fields());

        try {
            $tabs = $this->resolveEditFormTabs($context, $record);
            if ($tabs === [] && isset($context->params['tab'])) {
                abort(404);
            }

            if ($tabs !== []) {
                $activeTab = $this->resolveActiveFormTab($tabs, $context);
                $activeSchema = $activeTab instanceof ResourceFormTab && $activeTab->shouldNavigateWithRoute()
                    ? $this->resolveRoutedTabSchema($activeTab, $context, $record)
                    : [];

                return [
                    $this->buildResourceTabComponent($context, $tabs, $activeTab, $activeSchema, $record),
                ];
            }

            if (method_exists($resource, 'editForm')) {
                return $resource->editForm($record);
            }

            return $resource->form($record);
        } finally {
            $fieldRegistry->clear();
        }
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
        $tabs = $this->resolveEditFormTabs($context, $record);
        $activeTab = $tabs !== [] ? $this->resolveActiveFormTab($tabs, $context) : null;

        if ($activeTab instanceof ResourceFormTab && $activeTab->shouldNavigateWithRoute()) {
            $tabOperation = $activeTab->resolveOperation();

            if ($tabOperation instanceof Operation) {
                $result = $tabOperation->delegatedSave($context, $record);

                if ($result instanceof RedirectResult) {
                    return $result;
                }
            }
        }

        $schema = $this->getSchema($context, $record);
        $schema = $this->filterByOperation($schema, $context);

        $fieldNames = $this->collectFieldNames($schema);

        $data = collect($context->all())
            ->only($fieldNames)
            ->toArray();
        $resource = $this->resource();
        $action = (string) $context->input->get('_action', 'save_and_back');
        $customActionResult = $resource->handleFormAction($context, $action, $data, $record);
        if ($customActionResult instanceof RedirectResult) {
            return $customActionResult;
        }

        if (method_exists($resource, 'beforeSave')) {
            $resource->beforeSave($record, $data);
        }

        $data = $this->normalizeLanguagePayloadForModel($data, $schema, $record);
        $record->fill($data)->save();

        if (method_exists($resource, 'afterSave')) {
            $resource->afterSave($record);
        }

        $slug = $resource::slug();
        $panelPrefix = trim($context->panel()->prefixName(), '/');

        $base = $panelPrefix
            ? "{$panelPrefix}/{$slug}"
            : $slug;
        $encodedId = $record->getKey();

        return match ($action) {

            'save_and_edit' => RedirectResult::to($this->appendTabSegment("{$base}/{$encodedId}/edit", $activeTab))
                ->success('Zapisano'),

            'save_and_new' => RedirectResult::to("{$base}/create")
                ->success('Zapisano'),

            default => RedirectResult::to($base)
                ->success('Zapisano'),
        };
    }

    protected function resolveFormValidationPayload(PanelContext $context, array $schema, ...$args): array
    {
        $messages = $this->collectMessages($schema);
        $attributes = $this->collectAttributes($schema);
        $rules = array_merge($this->collectRules($schema), $this->rules());

        $record = $args[0] ?? null;

        if ($record instanceof Model) {
            $tabs = $this->resolveEditFormTabs($context, $record);
            $activeTab = $tabs !== [] ? $this->resolveActiveFormTab($tabs, $context) : null;

            if ($activeTab instanceof ResourceFormTab && $activeTab->shouldNavigateWithRoute()) {
                $tabOperation = $activeTab->resolveOperation();

                if ($tabOperation instanceof Operation) {
                    $rules = array_merge($rules, $tabOperation->delegatedValidationRules($context, $record));
                    $attributes = array_merge($attributes, $tabOperation->delegatedValidationAttributes($context, $record));
                    $messages = array_merge($messages, $tabOperation->delegatedValidationMessages($context, $record));
                }
            }
        }

        return [$messages, $attributes, $rules];
    }

    protected function appendTabSegment(string $base, ?ResourceFormTab $tab): string
    {
        if (! $tab instanceof ResourceFormTab || ! $tab->shouldNavigateWithRoute()) {
            return $base;
        }

        return rtrim($base, '/') . '/' . trim($tab->key(), '/');
    }
}
