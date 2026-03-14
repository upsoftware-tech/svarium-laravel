<?php

namespace Upsoftware\Svarium\Modules\Builtin\Translation\Panel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Upsoftware\Svarium\Models\TranslationOrder;
use Upsoftware\Svarium\Modules\Builtin\Support\AuthorizesResourcePermissions;
use Upsoftware\Svarium\Modules\Builtin\Translation\Tables\TranslationOrderTable;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Resource;
use Upsoftware\Svarium\Panel\Table\Table;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\Checklist;
use Upsoftware\Svarium\UI\Components\Form\Input;
use Upsoftware\Svarium\UI\Components\Form\Select;
use Upsoftware\Svarium\UI\Components\Form\Textarea;

class TranslationOrderResource extends Resource
{
    use AuthorizesResourcePermissions;

    protected static ?string $slug = 'system/translations/orders';

    public static function model(): string
    {
        $configured = config('upsoftware.models.translation_order');

        if (is_string($configured) && $configured !== '' && class_exists($configured)) {
            return $configured;
        }

        return TranslationOrder::class;
    }

    public function fields(): array
    {
        return [
            'code' => __('Code'),
            'title' => __('Title'),
            'description' => __('Description'),
            'status' => __('Status'),
            'priority' => __('Priority'),
            'source_locale' => __('Source locale'),
            'target_locales' => __('Target locales'),
            'due_at' => __('Due at'),
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

            Input::make('title')
                ->label(__('Title'))
                ->required()
                ->value($record ? (string) ($record->getAttribute('title') ?? '') : ''),

            Textarea::make('description')
                ->label(__('Description'))
                ->nullable()
                ->value($record ? (string) ($record->getAttribute('description') ?? '') : ''),

            Select::make('status')
                ->label(__('Status'))
                ->required()
                ->options([
                    ['value' => 'open', 'label' => __('Open')],
                    ['value' => 'in_progress', 'label' => __('In progress')],
                    ['value' => 'review', 'label' => __('In review')],
                    ['value' => 'done', 'label' => __('Done')],
                    ['value' => 'cancelled', 'label' => __('Cancelled')],
                ])
                ->value($record ? (string) ($record->getAttribute('status') ?? 'open') : 'open'),

            Select::make('priority')
                ->label(__('Priority'))
                ->required()
                ->options([
                    ['value' => 'low', 'label' => __('Low')],
                    ['value' => 'normal', 'label' => __('Normal')],
                    ['value' => 'high', 'label' => __('High')],
                    ['value' => 'urgent', 'label' => __('Urgent')],
                ])
                ->value($record ? (string) ($record->getAttribute('priority') ?? 'normal') : 'normal'),

            Select::make('source_locale')
                ->label(__('Source locale'))
                ->options($this->localeOptions())
                ->value($record ? (string) ($record->getAttribute('source_locale') ?? app()->getLocale()) : app()->getLocale()),

            Checklist::make('target_locales')
                ->label(__('Target locales'))
                ->options($this->localeOptions())
                ->value($record ? (array) ($record->getAttribute('target_locales') ?? []) : []),

            Input::make('due_at')
                ->label(__('Due at'))
                ->type('datetime-local')
                ->nullable()
                ->value($record ? $this->formatDateTimeLocal($record->getAttribute('due_at')) : ''),

            Input::make('requested_by')
                ->label(__('Requested by'))
                ->nullable()
                ->value($record ? (string) ($record->getAttribute('requested_by') ?? '') : ''),

            Input::make('assigned_to')
                ->label(__('Assigned to'))
                ->nullable()
                ->value($record ? (string) ($record->getAttribute('assigned_to') ?? '') : ''),
        ];
    }

    public function table(): TableBuilder
    {
        return Table::make(TranslationOrderTable::class);
    }

    public function beforeSave(Model $model, array &$data): void
    {
        $code = trim((string) ($data['code'] ?? ''));
        $data['code'] = (string) Str::of($code)
            ->lower()
            ->replace(' ', '_')
            ->replace('-', '_')
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->toString();

        if ($data['code'] === '') {
            $data['code'] = (string) Str::uuid();
        }

        $data['title'] = trim((string) ($data['title'] ?? ''));
        if ($data['title'] === '') {
            $data['title'] = $data['code'];
        }

        $data['description'] = $this->nullableString($data['description'] ?? null);
        $data['status'] = strtolower(trim((string) ($data['status'] ?? 'open'))) ?: 'open';
        $data['priority'] = strtolower(trim((string) ($data['priority'] ?? 'normal'))) ?: 'normal';
        $data['source_locale'] = strtolower(trim((string) ($data['source_locale'] ?? app()->getLocale()))) ?: app()->getLocale();
        $data['target_locales'] = $this->normalizeLocales($data['target_locales'] ?? []);
        $data['requested_by'] = $this->nullableString($data['requested_by'] ?? null);
        $data['assigned_to'] = $this->nullableString($data['assigned_to'] ?? null);

        $dueAt = trim((string) ($data['due_at'] ?? ''));
        $data['due_at'] = $dueAt !== '' ? str_replace('T', ' ', $dueAt).':00' : null;
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

    /**
     * @return array<int, array{value:string,label:string}>
     */
    protected function localeOptions(): array
    {
        $options = [];

        foreach ((array) locales() as $locale) {
            $code = strtolower(trim((string) ($locale['value'] ?? $locale['code'] ?? $locale['locale'] ?? '')));
            if ($code === '') {
                continue;
            }

            $label = trim((string) ($locale['label'] ?? $locale['native'] ?? strtoupper($code)));
            if ($label === '') {
                $label = strtoupper($code);
            }

            $options[] = [
                'value' => $code,
                'label' => $label,
            ];
        }

        if ($options === []) {
            $code = strtolower((string) app()->getLocale());
            $options[] = [
                'value' => $code,
                'label' => strtoupper($code),
            ];
        }

        return $options;
    }

    protected function normalizeLocales(mixed $payload): array
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            } elseif (trim($payload) !== '') {
                $payload = [$payload];
            } else {
                $payload = [];
            }
        }

        if (! is_array($payload)) {
            return [];
        }

        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $item): string => strtolower(trim((string) $item)),
            $payload
        ), static fn (string $value): bool => $value !== '')));

        sort($normalized);

        return $normalized;
    }

    protected function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    protected function formatDateTimeLocal(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d\TH:i');
        }

        $string = trim((string) $value);
        if ($string === '') {
            return '';
        }

        $timestamp = strtotime($string);
        if ($timestamp === false) {
            return '';
        }

        return date('Y-m-d\TH:i', $timestamp);
    }
}

