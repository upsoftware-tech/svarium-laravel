<?php

namespace Upsoftware\Svarium\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Panel\Table\TableBuilder;

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

    abstract public function form(): array;

    abstract public function table(): TableBuilder;

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

    public function canCreate(PanelContext $context): bool
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

    public function getFormFieldNames(): array
    {
        return collect($this->form())
            ->map(fn ($field) => $field->getName())
            ->filter()
            ->toArray();
    }
}
