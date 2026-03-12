<?php

namespace Upsoftware\Svarium\Panel\Resource\Operations;

use Illuminate\Validation\ValidationException;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\OperationResult;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Panel\FieldAttributesRegistry;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Resource\Operations\Concerns\InteractsWithResourceFormTabs;
use Upsoftware\Svarium\Panel\Resource\ResourceFormTab;
use Upsoftware\Svarium\Security\RecordIdentifier;

class ResourceCreateOperation extends Operation
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

    protected function formActions(): array
    {
        /** @var PanelContext $context */
        $context = app(PanelContext::class);

        return (array) $this->resource()->formActions($context, null);
    }

    protected function handleForm(PanelContext $context, ...$args): OperationResult
    {
        if (! $context->isPost()) {
            return $this->render($context, ...$args);
        }

        $schema = $this->getSchema($context, ...$args);
        $schema = $this->filterByOperation($schema, $context);

        [$messages, $attributes, $rules] = $this->resolveFormValidationPayload($context, $schema);

        try {
            $context->validated = validator($context->request()->all(), $rules, $messages, $attributes)->validate();
        } catch (ValidationException $e) {
            $result = $this->render($context, ...$args);
            $result->prop('errors', $e->errors());

            return $result;
        }

        $action = $context->input->get('_action');

        if ($action && array_key_exists((string) $action, $this->submitOptions())) {
            session()->put(static::class . '_submit_action', $action);
        }

        $result = $this->save($context);

        return $result ?? $this->render($context, ...$args);
    }

    protected function schema(PanelContext $context): array
    {
        $context->setOperationType('create');
        $resource = $this->resource();
        $this->applyTitleIfEmpty($resource->createTitle($context));
        $fieldRegistry = app(FieldAttributesRegistry::class);
        $fieldRegistry->setDefinitions($resource->fields());

        try {
            $tabs = $this->resolveCreateFormTabs($context);
            if ($tabs === [] && isset($context->params['tab'])) {
                abort(404);
            }

            if ($tabs !== []) {
                $activeTab = $this->resolveActiveFormTab($tabs, $context);
                $activeSchema = $activeTab instanceof ResourceFormTab && $activeTab->isRouted()
                    ? $this->resolveRoutedTabSchema($activeTab, $context)
                    : [];

                return [
                    $this->buildResourceTabComponent($context, $tabs, $activeTab, $activeSchema),
                ];
            }

            if (method_exists($resource, 'createForm')) {
                return $resource->createForm();
            }

            return $resource->form(null);
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

    protected function save(PanelContext $context): RedirectResult
    {
        $resource = $this->resource();
        $modelClass = $resource::model();

        $tabs = $this->resolveCreateFormTabs($context);
        $activeTab = $tabs !== [] ? $this->resolveActiveFormTab($tabs, $context) : null;

        if ($activeTab instanceof ResourceFormTab && $activeTab->isRouted()) {
            $tabOperation = $activeTab->resolveOperation();

            if ($tabOperation instanceof Operation) {
                $result = $tabOperation->delegatedSave($context);

                if ($result instanceof RedirectResult) {
                    return $result;
                }
            }
        }

        $schema = $this->getSchema($context);
        $schema = $this->filterByOperation($schema, $context);

        $fieldNames = $this->collectFieldNames($schema);

        $data = collect($context->all())
            ->only($fieldNames)
            ->toArray();

        $action = (string) $context->input->get('_action', 'save_and_back');
        $customActionResult = $resource->handleFormAction($context, $action, $data, null);
        if ($customActionResult instanceof RedirectResult) {
            return $customActionResult;
        }

        $record = new $modelClass;

        if (method_exists($resource, 'beforeSave')) {
            $resource->beforeSave($record, $data);
        }

        $record->fill($data)->save();

        if (method_exists($resource, 'afterSave')) {
            $resource->afterSave($record);
        }

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

            'save_and_edit' => RedirectResult::to($this->appendTabSegment("{$base}/{$encodedId}/edit", $activeTab))
                ->success('Zapisano'),

            'save_and_new' => RedirectResult::to($this->appendTabSegment("{$base}/create", $activeTab))
                ->success('Zapisano'),

            default => RedirectResult::to($base)
                ->success('Zapisano'),
        };
    }

    protected function resolveFormValidationPayload(PanelContext $context, array $schema): array
    {
        $messages = $this->collectMessages($schema);
        $attributes = $this->collectAttributes($schema);
        $rules = array_merge($this->collectRules($schema), $this->rules());

        $tabs = $this->resolveCreateFormTabs($context);
        $activeTab = $tabs !== [] ? $this->resolveActiveFormTab($tabs, $context) : null;

        if ($activeTab instanceof ResourceFormTab && $activeTab->isRouted()) {
            $tabOperation = $activeTab->resolveOperation();

            if ($tabOperation instanceof Operation) {
                $rules = array_merge($rules, $tabOperation->delegatedValidationRules($context));
                $attributes = array_merge($attributes, $tabOperation->delegatedValidationAttributes($context));
                $messages = array_merge($messages, $tabOperation->delegatedValidationMessages($context));
            }
        }

        return [$messages, $attributes, $rules];
    }

    protected function appendTabSegment(string $base, ?ResourceFormTab $tab): string
    {
        if (! $tab instanceof ResourceFormTab || ! $tab->isRouted()) {
            return $base;
        }

        return rtrim($base, '/') . '/' . trim($tab->key(), '/');
    }
}
