<?php

namespace Upsoftware\Svarium\UI\Components\Table;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Upsoftware\Svarium\Enums\TableActionDisplay;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Components\Button;
use Upsoftware\Svarium\UI\Components\ButtonLink;
use Upsoftware\Svarium\UI\Components\Dropdown;
use Upsoftware\Svarium\UI\Components\DropdownItem;
use Upsoftware\Svarium\UI\Components\Icon;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class Table extends Component
{
    use HasChildren;

    protected const EXPORT_FORMATS = ['sql', 'csv', 'txt'];

    protected ?string $model = null;

    protected array $columns = [];

    protected array $actions = [];

    protected ?TableActionDisplay $actionDisplay = null;

    protected ?bool $condesed = null;

    protected bool|array $exported = true;

    protected ?string $id = null;

    public static function make(?string $name = null): static
    {
        return new static;
    }

    /*
    |--------------------------------------------------------------------------
    | Model binding
    |--------------------------------------------------------------------------
    */

    public function model(string $modelClass): static
    {
        if (! is_subclass_of($modelClass, Model::class)) {
            throw new \InvalidArgumentException(
                "{$modelClass} must extend Eloquent Model."
            );
        }

        $this->model = $modelClass;

        $this->rows = $modelClass::query()
            ->get()
            ->map(function ($item) use ($modelClass) {
                $array = $item->toArray();
                $array['_model'] = $modelClass;

                return $array;
            })
            ->toArray();

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Action view type (inline, dropdown)
    |--------------------------------------------------------------------------
     */

    public function actionDisplay(TableActionDisplay|string $mode): static
    {
        if (is_string($mode)) {
            $mode = TableActionDisplay::tryFrom($mode);

            if (! $mode) {
                throw new \InvalidArgumentException(
                    'Invalid table action display mode.'
                );
            }
        }

        $this->actionDisplay = $mode;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Columns
    |--------------------------------------------------------------------------
    */

    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */

    public function actions(array $actions): static
    {
        $this->actions = $actions;

        return $this;
    }

    public function sticky(array|string ...$sections): static
    {
        $normalized = [];

        foreach ($sections as $section) {
            if (is_array($section)) {
                foreach ($section as $nested) {
                    if (! is_string($nested)) {
                        continue;
                    }

                    $value = strtolower(trim($nested));
                    if (in_array($value, ['header', 'search', 'footer'], true) && ! in_array($value, $normalized, true)) {
                        $normalized[] = $value;
                    }
                }

                continue;
            }

            $value = strtolower(trim($section));
            if (in_array($value, ['header', 'search', 'footer'], true) && ! in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return $this->prop('sticky', $normalized);
    }

    public function selected(bool $state = true): static
    {
        return $this->prop('columnSelection', $state);
    }

    public function id(?string $id): static
    {
        $normalized = $this->normalizeTableIdentifier((string) $id);

        if ($normalized === '') {
            $this->id = null;
            unset($this->props['id']);

            return $this;
        }

        $this->id = $normalized;
        $this->prop('id', $normalized);

        return $this;
    }

    public function condesed(bool $state = true): static
    {
        $this->condesed = $state;
        $this->prop('condesed', $state);

        return $this;
    }

    public function condensed(bool $state = true): static
    {
        return $this->condesed($state);
    }

    public function exported(bool|array|string ...$config): static
    {
        if (count($config) === 0) {
            $this->exported = true;
            $this->prop('exported', true);

            return $this;
        }

        if (count($config) === 1 && is_bool($config[0])) {
            $this->exported = $config[0];
            $this->prop('exported', $config[0]);

            return $this;
        }

        $formats = $this->normalizeExportFormats($config);
        if ($formats === []) {
            throw new \InvalidArgumentException('Export formats list cannot be empty.');
        }

        $this->exported = $formats;
        $this->prop('exported', $formats);

        return $this;
    }

    protected function wrapActions(array $actions): array
    {
        if (empty($actions)) {
            return [];
        }

        if ($this->actionDisplay === TableActionDisplay::DROPDOWN) {
            $dropdown = Dropdown::make()
                ->trigger(
                    Button::make()
                        ->icon(Icon::make('lucide:ellipsis-vertical'))
                        ->variant('ghost')
                        ->size('icon-sm')
                )
                ->children(
                    array_map(function ($a) {
                        $item = DropdownItem::make();
                        foreach ($a['props'] as $key => $value) {
                            $item->prop($key, $value);
                        }
                        return $item;
                    }, $actions)
                );
            return [$dropdown];
        }

        return array_map(function ($a) {
            $button = ButtonLink::make();
            foreach ($a['props'] as $key => $value) {
                $button->prop($key, $value);
            }
            return $button;
        }, $actions);
    }

    /*
    |--------------------------------------------------------------------------
    | Serialization
    |--------------------------------------------------------------------------
    */

    public function toArray(): array
    {
        if (! array_key_exists('id', $this->props)) {
            $this->prop('id', $this->resolveTableIdentifier());
        }

        if (! array_key_exists('condesed', $this->props)) {
            $configured = config('upsoftware.table.condesed');
            if ($configured === null) {
                $configured = config('upsoftware.table.condensed', false);
            }

            $value = is_bool($configured)
                ? $configured
                : filter_var($configured, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            $this->prop('condesed', $value ?? false);
        }

        if (! array_key_exists('exported', $this->props)) {
            $this->prop('exported', $this->exported);
        }

        return parent::toArray();
    }

    protected function normalizeExportFormats(array $config): array
    {
        $tokens = [];

        foreach ($config as $item) {
            if (is_string($item)) {
                $parts = array_map('trim', explode(',', $item));
                foreach ($parts as $part) {
                    if ($part !== '') {
                        $tokens[] = strtolower($part);
                    }
                }

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            foreach ($item as $nested) {
                if (! is_string($nested)) {
                    continue;
                }

                $parts = array_map('trim', explode(',', $nested));
                foreach ($parts as $part) {
                    if ($part !== '') {
                        $tokens[] = strtolower($part);
                    }
                }
            }
        }

        $tokens = array_values(array_unique($tokens));

        foreach ($tokens as $token) {
            if (! in_array($token, self::EXPORT_FORMATS, true)) {
                throw new \InvalidArgumentException(
                    "Invalid export format [{$token}]. Allowed values: ".implode(', ', self::EXPORT_FORMATS).'.'
                );
            }
        }

        return $tokens;
    }

    protected function resolveTableIdentifier(): string
    {
        if (is_string($this->id) && trim($this->id) !== '') {
            return $this->ensureUniqueTableIdentifier($this->id);
        }

        $segments = $this->resolveIdentifierPathSegments();

        if ($segments === []) {
            return $this->ensureUniqueTableIdentifier('page-index-table');
        }

        $resource = $this->normalizeTableIdentifier($segments[0] ?? '');
        if ($resource === '') {
            $resource = 'page';
        }

        $action = $this->normalizeTableIdentifier($this->resolveIdentifierAction($segments));
        if ($action === '') {
            $action = 'index';
        }

        $base = "{$resource}-{$action}-table";

        return $this->ensureUniqueTableIdentifier($base);
    }

    protected function normalizeTableIdentifier(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        return Str::of($trimmed)
            ->replaceMatches('/[^A-Za-z0-9\-_:.]+/', '-')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->lower()
            ->value();
    }

    protected function ensureUniqueTableIdentifier(string $identifier): string
    {
        $base = $this->normalizeTableIdentifier($identifier);

        if ($base === '') {
            $base = 'table';
        }

        $request = request();

        if (! $request) {
            return $base;
        }

        $used = $request->attributes->get('_svarium_table_identifiers', []);

        if (! is_array($used)) {
            $used = [];
        }

        $count = (int) ($used[$base] ?? 0) + 1;
        $used[$base] = $count;
        $request->attributes->set('_svarium_table_identifiers', $used);

        if ($count <= 1) {
            return $base;
        }

        return "{$base}-{$count}";
    }

    protected function resolveIdentifierPathSegments(): array
    {
        $path = request()?->path();

        if (! is_string($path)) {
            return [];
        }

        $trimmedPath = trim($path, '/');
        if ($trimmedPath === '') {
            return [];
        }

        $segments = array_values(array_filter(
            array_map(static fn (string $segment): string => trim($segment), explode('/', $trimmedPath)),
            static fn (string $segment): bool => $segment !== ''
        ));

        if ($segments === []) {
            return [];
        }

        $prefix = trim((string) config('upsoftware.panel.prefix', ''), '/');
        if ($prefix === '') {
            return $segments;
        }

        $prefixSegments = array_values(array_filter(
            array_map(static fn (string $segment): string => trim($segment), explode('/', $prefix)),
            static fn (string $segment): bool => $segment !== ''
        ));

        if ($prefixSegments === []) {
            return $segments;
        }

        $matchesPrefix = true;
        foreach ($prefixSegments as $index => $prefixSegment) {
            if (($segments[$index] ?? null) !== $prefixSegment) {
                $matchesPrefix = false;
                break;
            }
        }

        if (! $matchesPrefix) {
            return $segments;
        }

        $withoutPrefix = array_slice($segments, count($prefixSegments));

        return $withoutPrefix === [] ? $segments : array_values($withoutPrefix);
    }

    protected function resolveIdentifierAction(array $segments): string
    {
        if (count($segments) <= 1) {
            return 'index';
        }

        $second = trim((string) ($segments[1] ?? ''));
        $third = trim((string) ($segments[2] ?? ''));

        if ($second !== '' && ! is_numeric($second)) {
            return $second;
        }

        if ($third !== '' && ! is_numeric($third)) {
            return $third;
        }

        return 'index';
    }
}
