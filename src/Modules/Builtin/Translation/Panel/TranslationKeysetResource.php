<?php

namespace Upsoftware\Svarium\Modules\Builtin\Translation\Panel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Upsoftware\Svarium\Models\TranslationKeyset;
use Upsoftware\Svarium\Modules\Builtin\Support\AuthorizesResourcePermissions;
use Upsoftware\Svarium\Modules\Builtin\Translation\Tables\TranslationKeysetTable;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Resource;
use Upsoftware\Svarium\Panel\Table\Table;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\Form\Input;
use Upsoftware\Svarium\UI\Components\Form\Select;
use Upsoftware\Svarium\UI\Components\Form\Textarea;
use Upsoftware\Svarium\UI\Components\Toggle;

class TranslationKeysetResource extends Resource
{
    use AuthorizesResourcePermissions;

    protected static ?string $slug = 'system/translations/keysets';

    public static function model(): string
    {
        $configured = config('upsoftware.models.translation_keyset');

        if (is_string($configured) && $configured !== '' && class_exists($configured)) {
            return $configured;
        }

        return TranslationKeyset::class;
    }

    public function fields(): array
    {
        return [
            'code' => __('Code'),
            'scope' => __('Scope'),
            'scope_key' => __('Scope key'),
            'name' => __('Name'),
            'description' => __('Description'),
            'source_locale' => __('Source locale'),
            'status' => __('Status'),
            'updated_at' => __('Updated at'),
        ];
    }

    public function form(?Model $record = null): array
    {
        return [
            Input::make('code')
                ->label(__('Code'))
                ->required()
                ->value($record ? (string) ($record->getAttribute('code') ?? '') : ''),

            Select::make('scope')
                ->label(__('Scope'))
                ->required()
                ->options([
                    ['value' => 'global', 'label' => __('Global')],
                    ['value' => 'module', 'label' => __('Module')],
                    ['value' => 'package', 'label' => __('Package')],
                    ['value' => 'custom', 'label' => __('Custom')],
                ])
                ->value($record ? (string) ($record->getAttribute('scope') ?? 'global') : 'global'),

            Input::make('scope_key')
                ->label(__('Scope key'))
                ->hint(__('Required for module/package scope.'))
                ->value($record ? (string) ($record->getAttribute('scope_key') ?? '') : '')
                ->nullable(),

            Input::make('name')
                ->label(__('Name'))
                ->required()
                ->value($record ? (string) ($record->getAttribute('name') ?? '') : ''),

            Textarea::make('description')
                ->label(__('Description'))
                ->value($record ? (string) ($record->getAttribute('description') ?? '') : '')
                ->nullable(),

            Input::make('source_locale')
                ->label(__('Source locale'))
                ->required()
                ->value($record ? (string) ($record->getAttribute('source_locale') ?? app()->getLocale()) : app()->getLocale()),

            Toggle::make('status')
                ->label(__('Status'))
                ->value($record ? (bool) ($record->getAttribute('status') ?? true) : true),
        ];
    }

    public function table(): TableBuilder
    {
        return Table::make(TranslationKeysetTable::class);
    }

    public function beforeSave(Model $model, array &$data): void
    {
        $rawCode = trim((string) ($data['code'] ?? ''));
        $data['code'] = (string) Str::of($rawCode)
            ->lower()
            ->replace('-', '_')
            ->replace(' ', '_')
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->toString();

        if ($data['code'] === '') {
            $data['code'] = (string) Str::uuid();
        }

        $data['scope'] = strtolower(trim((string) ($data['scope'] ?? 'global')));
        if ($data['scope'] === '') {
            $data['scope'] = 'global';
        }

        $scopeKey = trim((string) ($data['scope_key'] ?? ''));
        $data['scope_key'] = $scopeKey !== '' ? $scopeKey : null;

        $sourceLocale = strtolower(trim((string) ($data['source_locale'] ?? app()->getLocale())));
        $data['source_locale'] = $sourceLocale !== '' ? $sourceLocale : app()->getLocale();
        $data['status'] = $this->toBool($data['status'] ?? true);
    }

    public function canList(PanelContext $context): bool
    {
        return $this->canResourceAction($context, 'list');
    }

    public function canCreate(PanelContext $context): bool
    {
        return $this->canResourceAction($context, 'create');
    }

    public function canEdit(PanelContext $context): bool
    {
        return $this->canResourceAction($context, 'edit');
    }

    public function canDelete(PanelContext $context): bool
    {
        return $this->canResourceAction($context, 'delete');
    }

    public function canDuplicate(PanelContext $context): bool
    {
        return $this->canResourceAction($context, 'duplicate');
    }

    protected function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
            return false;
        }

        return $normalized !== '';
    }
}

