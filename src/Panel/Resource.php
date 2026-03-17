<?php

namespace Upsoftware\Svarium\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Panel\Resource\ResourceFormTab;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\Services\ResourceImportService;
use Upsoftware\Svarium\UI\Component;

abstract class Resource
{
    protected static string $model;
    protected static ?string $slug = null;

    /*
    |--------------------------------------------------------------------------
    | Metadata
    |--------------------------------------------------------------------------
    */

    public static function model(): string
    {
        return static::$model;
    }

    public static function parameter(): string
    {
        return str(class_basename(static::$model))
            ->camel()
            ->toString();
    }

    public static function slug(): string
    {
        if (static::$slug) {
            return static::$slug;
        }

        return str(class_basename(static::$model))
            ->plural()
            ->lower()
            ->toString();
    }

    /*
    |--------------------------------------------------------------------------
    | Definitions (user must implement)
    |--------------------------------------------------------------------------
    */

    public function form(?Model $record = null): array
    {
        return [];
    }

    abstract public function table(): TableBuilder;

    public function fields(): array
    {
        return [];
    }

    /**
     * @return array<int, ResourceFormTab>
     */
    public function formTabs(PanelContext $context, ?Model $record = null): array
    {
        return [];
    }

    /**
     * @return array<int, ResourceFormTab>
     */
    public function createTabs(PanelContext $context): array
    {
        return $this->formTabs($context);
    }

    /**
     * @return array<int, ResourceFormTab>
     */
    public function editTabs(PanelContext $context, Model $record): array
    {
        return $this->formTabs($context, $record);
    }

    public function formTabPosition(PanelContext $context, ?Model $record = null): string
    {
        return 'top';
    }

    public function formConfig(PanelContext $context, ?Model $record = null): array
    {
        return [];
    }

    public function formTabConfig(PanelContext $context, ?Model $record = null): array
    {
        return $this->resolveFormConfig($context, $record)['tab'];
    }

    public function formTabDefaults(PanelContext $context, ?Model $record = null): array
    {
        return [];
    }

    public function formTabHeader(PanelContext $context, ?Model $record = null): Component|array|string|\Closure|null
    {
        return null;
    }

    public function formTabHeaderTitle(PanelContext $context, ?Model $record = null): Component|array|string|\Closure|null
    {
        return null;
    }

    public function formTabHeaderAside(PanelContext $context, ?Model $record = null): Component|array|string|\Closure|null
    {
        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Hooks (optional)
    |--------------------------------------------------------------------------
    */

    public function beforeFill(Model $model): void {}
    public function afterFill(Model $model): void {}

    public function beforeSave(Model $model, array &$data): void {}
    public function afterSave(Model $model): void {}

    public function beforeDelete(Model $model): void {}
    public function afterDelete(Model $model): void {}

    /**
     * Handle custom form action from create/edit forms (e.g. `_action=test_connection`).
     */
    public function handleFormAction(
        PanelContext $context,
        string $action,
        array $data,
        ?Model $record = null
    ): ?RedirectResult {
        return null;
    }

    /**
     * Extra form footer actions rendered next to the default save action.
     *
     * @return array<int, Component>
     */
    public function formActions(PanelContext $context, ?Model $record = null): array
    {
        return [];
    }

    public function canCreate(PanelContext $context): bool
    {
        return true;
    }

    public function canList(PanelContext $context): bool
    {
        return true;
    }

    public function canEdit(PanelContext $context): bool
    {
        return true;
    }

    public function canDelete(PanelContext $context): bool
    {
        return true;
    }

    public function canDuplicate(PanelContext $context): bool
    {
        return true;
    }

    public function canPreview(PanelContext $context): bool
    {
        return true;
    }

    public function canImport(PanelContext $context): bool
    {
        return true;
    }

    public function canExport(PanelContext $context): bool
    {
        return $this->canList($context);
    }

    public function import(PanelContext $context, array $files, TableBuilder $builder): mixed
    {
        /** @var ResourceImportService $importer */
        $importer = app(ResourceImportService::class);

        return $importer->import($this, $files);
    }

    public function access(): array
    {
        return [];
    }

    public function listTitle(PanelContext $context): string
    {
        $label = (string) str($this->resourceTitleLabel())->headline();

        return "{$label} list";
    }

    public function createTitle(PanelContext $context): string
    {
        return "Create {$this->resourceTitleLabel()}";
    }

    public function editTitle(PanelContext $context, Model $record): string
    {
        return "Edit {$this->resourceTitleLabel()}";
    }

    public function duplicateTitle(PanelContext $context, Model $record): string
    {
        return "Duplicate {$this->resourceTitleLabel()}";
    }

    public function previewTitle(PanelContext $context, Model $record): string
    {
        return "Preview {$this->resourceTitleLabel()}";
    }

    public function importTitle(PanelContext $context): string
    {
        return "Import {$this->resourceTitleLabel()}";
    }

    public function exportTitle(PanelContext $context): string
    {
        return "Export {$this->resourceTitleLabel()}";
    }

    public function previewForm(Model $record): array
    {
        if (method_exists($this, 'editForm')) {
            return $this->editForm($record);
        }

        return $this->form($record);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function newModel(): Model
    {
        $class = static::model();

        return new $class;
    }

    public static function query(): Builder
    {
        $class = static::model();

        return $class::query();
    }

    protected function resourceTitleLabel(): string
    {
        return (string) str(class_basename(static::model()))
            ->snake(' ')
            ->lower()
            ->trim()
            ->toString();
    }

    protected function tableQuery(): Builder
    {
        return static::query();
    }

    protected function tableBuilder(): TableBuilder
    {
        return TableBuilder::make($this->tableQuery());
    }

    public function resolveFormConfig(?PanelContext $context = null, ?Model $record = null): array
    {
        $defaults = [
            'tab' => [
                'position' => 'left',
                'variant' => 'simple',
                'title' => true,
                'card' => true,
                'defaults' => [],
                'validation_error_icon' => [
                    'enabled' => false,
                    'icon' => 'lucide:circle-alert',
                ],
            ],
            'language' => [
                'display' => 'inline',
                'multiple' => false,
                'showIcon' => false,
                'showLabel' => true,
            ],
        ];

        $configured = config('upsoftware.resource.form', []);

        if (is_array($configured)) {
            $defaults = array_replace_recursive($defaults, $configured);
        }

        $custom = $context instanceof PanelContext
            ? $this->formConfig($context, $record)
            : [];

        if (! is_array($custom)) {
            $custom = [];
        }

        return array_replace_recursive($defaults, $this->normalizeFormConfig($custom, $context, $record));
    }

    protected function normalizeFormConfig(array $config, ?PanelContext $context = null, ?Model $record = null): array
    {
        if (
            array_key_exists('position', $config)
            || array_key_exists('variant', $config)
            || array_key_exists('title', $config)
            || array_key_exists('card', $config)
        ) {
            $config['tab'] = array_filter([
                'position' => $config['position'] ?? ($context instanceof PanelContext
                    ? $this->formTabPosition($context, $record)
                    : 'top'),
                'variant' => $config['variant'] ?? 'default',
                'title' => $config['title'] ?? true,
                'card' => $config['card'] ?? true,
            ], static fn ($value) => $value !== null);

            unset($config['position'], $config['variant'], $config['title'], $config['card']);
        }

        if (! isset($config['tab']) || ! is_array($config['tab'])) {
            if ($context instanceof PanelContext && $this->isFormTabPositionOverridden()) {
                $config['tab'] = [
                    'position' => $this->formTabPosition($context, $record),
                ];
            }

            return $config;
        }

        if (
            $context instanceof PanelContext
            && $this->isFormTabPositionOverridden()
            && ! array_key_exists('position', $config['tab'])
        ) {
            $config['tab']['position'] = $this->formTabPosition($context, $record);
        }

        return $config;
    }

    protected function isFormTabPositionOverridden(): bool
    {
        try {
            $reflection = new \ReflectionMethod($this, 'formTabPosition');
        } catch (\ReflectionException) {
            return false;
        }

        return $reflection->getDeclaringClass()->getName() !== self::class;
    }

    public function getFormFieldNames(): array
    {
        return collect($this->form())
            ->map(fn ($field) => $field->getName())
            ->filter()
            ->toArray();
    }
}
