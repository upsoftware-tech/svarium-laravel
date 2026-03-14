<?php

namespace Upsoftware\Svarium\Modules\Builtin\Translation\Panel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Upsoftware\Svarium\Models\TranslationKey;
use Upsoftware\Svarium\Models\TranslationKeyset;
use Upsoftware\Svarium\Models\TranslationRevision;
use Upsoftware\Svarium\Models\TranslationValue;
use Upsoftware\Svarium\Modules\Builtin\Support\AuthorizesResourcePermissions;
use Upsoftware\Svarium\Modules\Builtin\Translation\Tables\TranslationKeyTable;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Resource;
use Upsoftware\Svarium\Panel\Table\Table;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\Form\Input;
use Upsoftware\Svarium\UI\Components\Form\Select;
use Upsoftware\Svarium\UI\Components\Form\Textarea;
use Upsoftware\Svarium\UI\Components\Toggle;

class TranslationKeyResource extends Resource
{
    use AuthorizesResourcePermissions;

    protected static ?string $slug = 'system/translations/keys';

    /**
     * @var array<string, string|null>
     */
    protected array $pendingValues = [];

    public static function model(): string
    {
        $configured = config('upsoftware.models.translation_key');

        if (is_string($configured) && $configured !== '' && class_exists($configured)) {
            return $configured;
        }

        return TranslationKey::class;
    }

    public function fields(): array
    {
        return [
            'translation_keyset_id' => __('Keyset'),
            'key' => __('Key'),
            'type' => __('Type'),
            'category' => __('Category'),
            'context' => __('Context'),
            'description' => __('Description'),
            'max_length' => __('Max length'),
            'status' => __('Status'),
            'updated_at' => __('Updated at'),
        ];
    }

    public function form(?Model $record = null): array
    {
        $fields = [
            Select::make('translation_keyset_id')
                ->label(__('Keyset'))
                ->required()
                ->options($this->keysetOptions())
                ->value($record ? (string) ($record->getAttribute('translation_keyset_id') ?? '') : ''),

            Input::make('key')
                ->label(__('Key'))
                ->required()
                ->value($record ? (string) ($record->getAttribute('key') ?? '') : ''),

            Select::make('type')
                ->label(__('Type'))
                ->required()
                ->options([
                    ['value' => 'text', 'label' => __('Text')],
                    ['value' => 'html', 'label' => 'HTML'],
                    ['value' => 'markdown', 'label' => 'Markdown'],
                    ['value' => 'json', 'label' => 'JSON'],
                ])
                ->value($record ? (string) ($record->getAttribute('type') ?? 'text') : 'text'),

            Input::make('category')
                ->label(__('Category'))
                ->nullable()
                ->value($record ? (string) ($record->getAttribute('category') ?? '') : ''),

            Input::make('max_length')
                ->label(__('Max length'))
                ->type('number')
                ->min(1)
                ->nullable()
                ->value($record ? (string) ($record->getAttribute('max_length') ?? '') : ''),

            Textarea::make('context')
                ->label(__('Context'))
                ->nullable()
                ->value($record ? (string) ($record->getAttribute('context') ?? '') : ''),

            Textarea::make('description')
                ->label(__('Description'))
                ->nullable()
                ->value($record ? (string) ($record->getAttribute('description') ?? '') : ''),

            Toggle::make('status')
                ->label(__('Status'))
                ->value($record ? (bool) ($record->getAttribute('status') ?? true) : true),
        ];

        $valuesByLocale = $record ? $this->resolveValuesByLocale($record) : [];

        foreach ($this->availableLocales() as $locale) {
            $fields[] = Textarea::make('value_'.$locale)
                ->label(__('Value').' ('.strtoupper($locale).')')
                ->value((string) ($valuesByLocale[$locale] ?? ''))
                ->nullable();
        }

        return $fields;
    }

    public function table(): TableBuilder
    {
        return Table::make(TranslationKeyTable::class);
    }

    public function beforeSave(Model $model, array &$data): void
    {
        $this->pendingValues = [];

        foreach (array_keys($data) as $field) {
            if (! str_starts_with($field, 'value_')) {
                continue;
            }

            $locale = strtolower(trim(substr($field, 6)));
            if ($locale === '') {
                unset($data[$field]);
                continue;
            }

            $this->pendingValues[$locale] = $this->normalizeValueInput($data[$field] ?? null);
            unset($data[$field]);
        }

        $data['translation_keyset_id'] = (int) ($data['translation_keyset_id'] ?? 0);

        $rawKey = trim((string) ($data['key'] ?? ''));
        $data['key'] = (string) Str::of($rawKey)
            ->replace(' ', '_')
            ->replace('-', '_')
            ->replaceMatches('/[^a-zA-Z0-9_.:]/', '')
            ->toString();

        if ($data['key'] === '') {
            $data['key'] = (string) Str::uuid();
        }

        $type = strtolower(trim((string) ($data['type'] ?? 'text')));
        $data['type'] = in_array($type, ['text', 'html', 'markdown', 'json'], true) ? $type : 'text';

        $maxLength = trim((string) ($data['max_length'] ?? ''));
        $data['max_length'] = is_numeric($maxLength) && (int) $maxLength > 0
            ? (int) $maxLength
            : null;

        $data['category'] = $this->nullableString($data['category'] ?? null);
        $data['context'] = $this->nullableString($data['context'] ?? null);
        $data['description'] = $this->nullableString($data['description'] ?? null);
        $data['status'] = $this->toBool($data['status'] ?? true);
    }

    public function afterSave(Model $model): void
    {
        $valueModelClass = $this->valueModelClass();
        $revisionModelClass = $this->revisionModelClass();
        $actor = $this->resolveActorIdentifier();
        $keyId = $model->getKey();

        if ($keyId === null || $keyId === '') {
            return;
        }

        /** @var array<string, TranslationValue> $existing */
        $existing = $valueModelClass::query()
            ->where('translation_key_id', $keyId)
            ->get()
            ->keyBy(static fn (Model $item): string => (string) $item->getAttribute('locale'))
            ->all();

        foreach ($this->availableLocales() as $locale) {
            $newValue = $this->pendingValues[$locale] ?? null;
            $newStatus = $newValue === null || trim($newValue) === '' ? 'missing' : 'human';

            $current = $existing[$locale] ?? null;

            if (! $current instanceof Model) {
                $created = $valueModelClass::query()->create([
                    'translation_key_id' => $keyId,
                    'locale' => $locale,
                    'value' => $newValue,
                    'status' => $newStatus,
                    'is_machine' => false,
                    'updated_by' => $actor,
                    'version' => 1,
                ]);

                $revisionModelClass::query()->create([
                    'translation_key_id' => $keyId,
                    'translation_value_id' => $created->getKey(),
                    'locale' => $locale,
                    'change_type' => 'create',
                    'old_value' => null,
                    'new_value' => $newValue,
                    'old_status' => null,
                    'new_status' => $newStatus,
                    'changed_by' => $actor,
                ]);

                continue;
            }

            $oldValue = $this->normalizeValueInput($current->getAttribute('value'));
            $oldStatus = trim((string) ($current->getAttribute('status') ?? ''));

            if ($oldValue === $newValue && $oldStatus === $newStatus) {
                continue;
            }

            $newVersion = (int) ($current->getAttribute('version') ?? 1);
            $newVersion = max(1, $newVersion + 1);

            $current->setAttribute('value', $newValue);
            $current->setAttribute('status', $newStatus);
            $current->setAttribute('is_machine', false);
            $current->setAttribute('updated_by', $actor);
            $current->setAttribute('version', $newVersion);
            $current->save();

            $revisionModelClass::query()->create([
                'translation_key_id' => $keyId,
                'translation_value_id' => $current->getKey(),
                'locale' => $locale,
                'change_type' => 'update',
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'old_status' => $oldStatus !== '' ? $oldStatus : null,
                'new_status' => $newStatus,
                'changed_by' => $actor,
            ]);
        }
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
    protected function keysetOptions(): array
    {
        $modelClass = $this->keysetModelClass();

        return $modelClass::query()
            ->where('status', true)
            ->orderBy('name')
            ->orderBy('code')
            ->get()
            ->map(static function (Model $row): array {
                $code = trim((string) $row->getAttribute('code'));
                $name = trim((string) $row->getAttribute('name'));

                $label = $name;
                if ($code !== '') {
                    $label .= " ({$code})";
                }

                return [
                    'value' => (string) $row->getKey(),
                    'label' => $label !== '' ? $label : (string) $row->getKey(),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function resolveValuesByLocale(Model $record): array
    {
        $valueModelClass = $this->valueModelClass();

        return $valueModelClass::query()
            ->where('translation_key_id', $record->getKey())
            ->get()
            ->mapWithKeys(static fn (Model $item): array => [
                strtolower(trim((string) $item->getAttribute('locale'))) => (string) ($item->getAttribute('value') ?? ''),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function availableLocales(): array
    {
        $available = [];

        foreach ((array) locales() as $locale) {
            $code = strtolower(trim((string) ($locale['value'] ?? $locale['code'] ?? $locale['locale'] ?? '')));
            if ($code === '') {
                continue;
            }

            $available[] = $code;
        }

        if ($available === []) {
            $available[] = strtolower((string) app()->getLocale());
        }

        $available = array_values(array_unique(array_filter($available, static fn (string $code): bool => $code !== '')));
        sort($available);

        return $available;
    }

    protected function normalizeValueInput(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = (string) $value;

        return trim($string) === '' ? null : $string;
    }

    protected function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
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

    protected function resolveActorIdentifier(): ?string
    {
        $user = auth()->user();
        if (! is_object($user) || ! method_exists($user, 'getAuthIdentifier')) {
            return null;
        }

        $id = $user->getAuthIdentifier();

        return $id !== null ? (string) $id : null;
    }

    protected function keysetModelClass(): string
    {
        $configured = config('upsoftware.models.translation_keyset');

        if (is_string($configured) && $configured !== '' && class_exists($configured)) {
            return $configured;
        }

        return TranslationKeyset::class;
    }

    protected function valueModelClass(): string
    {
        $configured = config('upsoftware.models.translation_value');

        if (is_string($configured) && $configured !== '' && class_exists($configured)) {
            return $configured;
        }

        return TranslationValue::class;
    }

    protected function revisionModelClass(): string
    {
        $configured = config('upsoftware.models.translation_revision');

        if (is_string($configured) && $configured !== '' && class_exists($configured)) {
            return $configured;
        }

        return TranslationRevision::class;
    }
}

