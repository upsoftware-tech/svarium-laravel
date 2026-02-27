<?php

namespace Upsoftware\Svarium\Panel\Table;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Upsoftware\Svarium\Enums\TableActionDisplay;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Components\Button;
use Upsoftware\Svarium\UI\Components\Checkbox;
use Upsoftware\Svarium\UI\Components\ColumnVisibility;
use Upsoftware\Svarium\UI\Components\Drawer;
use Upsoftware\Svarium\UI\Components\DrawerClose;
use Upsoftware\Svarium\UI\Components\DrawerContent;
use Upsoftware\Svarium\UI\Components\DrawerFooter;
use Upsoftware\Svarium\UI\Components\DrawerHeader;
use Upsoftware\Svarium\UI\Components\DrawerTitle;
use Upsoftware\Svarium\UI\Components\DrawerTrigger;
use Upsoftware\Svarium\UI\Components\Dialog;
use Upsoftware\Svarium\UI\Components\Dropdown;
use Upsoftware\Svarium\UI\Components\Icon;
use Upsoftware\Svarium\UI\Components\Radio;
use Upsoftware\Svarium\UI\Components\Search\DropdownSearch;
use Upsoftware\Svarium\UI\Components\Search\InputSearch;
use Upsoftware\Svarium\UI\Components\Tab;
use Upsoftware\Svarium\UI\Components\TabItem;
use Upsoftware\Svarium\UI\Components\Table\Action;
use Upsoftware\Svarium\UI\Components\Table\Column;
use Upsoftware\Svarium\UI\Components\Table\Filter;
use Upsoftware\Svarium\UI\Components\Table\TableFilters;
use Upsoftware\Svarium\UI\Components\Table\Table;
use Upsoftware\Svarium\UI\Components\Table\TableBody;
use Upsoftware\Svarium\UI\Components\Table\TableCell;
use Upsoftware\Svarium\UI\Components\Table\TableFooter;
use Upsoftware\Svarium\UI\Components\Table\TableHead;
use Upsoftware\Svarium\UI\Components\Table\TableHeader;
use Upsoftware\Svarium\UI\Components\Table\TableRow;
use Upsoftware\Svarium\UI\Appearance;
use Upsoftware\Svarium\UI\Components\Text;

class TableBuilder
{
    protected $query;

    protected bool $bulkEnabled = false;
    protected ?string $bulkMode = null;
    protected bool $numberingEnabled = false;
    protected ?string $numberingMode = null;
    protected string $numberingLabel = '#';
    protected string $filtersAppearance = 'drawer';

    protected array $bulkActions = [];

    protected bool $useDefaultBulkActions = true;

    protected array $disabledDefaultBulkActions = [];

    protected ?array $onlyDefaultBulkActions = null;

    protected ?string $baseUri = null;

    protected array $columns = [];

    protected array $searchable = [];

    protected array $actions = [];

    protected ?TableActionDisplay $actionDisplay = null;

    protected bool $useDefaultActions = true;

    protected array $disabledDefaultActions = [];

    protected ?array $onlyDefaultActions = null;

    protected ?string $title = null;

    protected ?string $description = null;

    protected array $headerActions = [];

    protected array $filters = [];

    protected array $perPageOptions = [15];
    protected ?int $rowsPerPage = null;
    protected ?string $rowsPerPageLabel = null;
    protected ?string $rowsPerPageAllLabel = null;
    protected ?string $paginationLabel = null;
    protected bool $showButtonLabel = true;
    protected bool $showFirstLabel = true;
    protected bool $showLastLabel = true;
    protected int $ellipsisAfter = 7;
    protected ?string $firstButtonLabel = null;
    protected ?string $previousButtonLabel = null;
    protected ?string $nextButtonLabel = null;
    protected ?string $lastButtonLabel = null;
    protected ?int $resolvedRowsPerPage = null;

    protected ?string $appearance = null;

    protected array $headerComponents = [];

    protected array $headerAppearanceProps = [];

    protected array $searchAppearanceProps = [];

    protected array $bodyAppearanceProps = [];

    protected bool $searchAppearanceDefined = false;

    protected array $tabs = [];

    protected $searchbar;

    protected bool $tabsFromViews = false;

    protected array $columnObjects = [];

    protected array $footerTotalAggregatesCache = [];

    protected ?array $footerTotalRowsCache = null;

    protected array $stickySections = [];

    protected string $filterInputSize = 'default';

    public function searchbar($searchbar): static
    {
        $this->searchbar = $searchbar;

        return $this;
    }

    public static function make($query): static
    {
        $instance = new static;
        $instance->query = $query;

        return $instance;
    }

    public function bulk(bool|string $mode = true): static
    {
        if (is_bool($mode)) {
            $this->bulkEnabled = $mode;
            $this->bulkMode = $mode ? 'multiple' : null;

            return $this;
        }

        $normalizedMode = strtolower(trim($mode));

        if (! in_array($normalizedMode, ['single', 'multiple'], true)) {
            throw new \InvalidArgumentException(
                "Invalid bulk mode [{$mode}]. Allowed values: true, false, 'single', 'multiple'."
            );
        }

        $this->bulkEnabled = true;
        $this->bulkMode = $normalizedMode;

        return $this;
    }

    public function numbering(bool|string $mode = true, ?string $label = null): static
    {
        if (is_bool($mode)) {
            $this->numberingEnabled = $mode;
            $this->numberingMode = $mode ? 'continuous' : null;

            if ($label !== null) {
                $this->numberingLabel = $label;
            }

            return $this;
        }

        $normalizedMode = strtolower(trim($mode));
        $normalizedMode = match ($normalizedMode) {
            'reset', 'page', 'per-page' => 'per_page',
            default => $normalizedMode,
        };

        if (! in_array($normalizedMode, ['continuous', 'per_page'], true)) {
            throw new \InvalidArgumentException(
                "Invalid numbering mode [{$mode}]. Allowed values: true, false, 'continuous', 'per_page', 'reset'."
            );
        }

        $this->numberingEnabled = true;
        $this->numberingMode = $normalizedMode;

        if ($label !== null) {
            $this->numberingLabel = $label;
        }

        return $this;
    }

    public function tabs(array $tabs): static
    {
        $this->tabs = $tabs;

        return $this;
    }

    public function tabsFromViews(bool $enabled = true): static
    {
        $this->tabsFromViews = $enabled;

        return $this;
    }

    public function bulkActions(array $actions): static
    {
        $this->bulkActions = $actions;

        return $this;
    }

    public function disableDefaultBulkActions(array $types): static
    {
        $this->disabledDefaultBulkActions = array_values(array_filter(array_map(
            static fn ($type) => is_string($type) ? strtolower(trim($type)) : '',
            $types
        )));

        return $this;
    }

    public function onlyDefaultBulkActions(array $types): static
    {
        $this->onlyDefaultBulkActions = array_values(array_filter(array_map(
            static fn ($type) => is_string($type) ? strtolower(trim($type)) : '',
            $types
        )));

        return $this;
    }

    public function withoutDefaultBulkActions(): static
    {
        $this->useDefaultBulkActions = false;

        return $this;
    }

    public function columns(array $columns): static
    {
        $this->columnObjects = $columns;

        return $this;
    }

    public function filterColumns(callable $callback): static
    {
        $filtered = [];
        $allowedKeys = [];

        foreach ($this->columnObjects as $column) {
            $key = null;

            if ($column instanceof Column) {
                $key = $column->getKey();
            } elseif (is_array($column)) {
                if (isset($column['key']) && is_string($column['key'])) {
                    $key = $column['key'];
                } elseif (isset($column['field']) && is_string($column['field'])) {
                    $key = $column['field'];
                }
            }

            $isVisible = $callback($key ?? '', $column);

            if ($isVisible === false) {
                continue;
            }

            if (is_string($key) && trim($key) !== '') {
                $allowedKeys[] = $key;
            }

            $filtered[] = $column;
        }

        $this->columnObjects = $filtered;

        if (! empty($this->searchable)) {
            $allowedLookup = array_flip($allowedKeys);

            $this->searchable = array_values(array_filter(
                $this->searchable,
                static fn ($column) => isset($allowedLookup[$column])
            ));
        }

        return $this;
    }

    public function searchable(array $columns): static
    {
        $this->searchable = $columns;

        return $this;
    }

    public function actions(array $actions): static
    {
        $this->actions = $actions;

        return $this;
    }

    public function baseUri(string $uri): static
    {
        $this->baseUri = '/'.trim($uri, '/');

        return $this;
    }

    public function header(array $components): static
    {
        $this->headerComponents = $components;

        return $this;
    }

    public function addHeader(Component $component): static
    {
        $this->headerComponents[] = $component;

        return $this;
    }

    protected function mergeAppearanceProps(array &$target, array|Appearance $props): void
    {
        if ($props instanceof Appearance) {
            $props = $props->toArray();
        }

        if (array_key_exists('appearance', $props)) {
            $appearance = $props['appearance'];
            unset($props['appearance']);
        } else {
            $appearance = $props;
            $props = [];
        }

        $currentAppearance = $target['appearance'] ?? [];
        if (! is_array($currentAppearance)) {
            $currentAppearance = [];
        }
        if (! is_array($appearance)) {
            $appearance = [];
        }

        $target = [
            ...$target,
            ...$props,
            'appearance' => [
                ...$currentAppearance,
                ...$appearance,
            ],
        ];
    }

    public function headerAppearance(array|Appearance $props): static
    {
        $this->mergeAppearanceProps($this->headerAppearanceProps, $props);

        return $this;
    }

    public function bodyAppearance(array|Appearance $props): static
    {
        $this->mergeAppearanceProps($this->bodyAppearanceProps, $props);

        return $this;
    }

    public function searchAppearance(array|Appearance $props): static
    {
        $this->searchAppearanceDefined = true;
        $this->mergeAppearanceProps($this->searchAppearanceProps, $props);

        return $this;
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function headerActions(array $actions): static
    {
        $this->headerActions = $actions;

        return $this;
    }

    public function filters(array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    public function filtersAppearance(string $appearance): static
    {
        $normalized = strtolower(trim($appearance));

        if (! in_array($normalized, ['drawer', 'inline', 'both'], true)) {
            throw new \InvalidArgumentException(
                "Invalid filters appearance [{$appearance}]. Allowed values: 'drawer', 'inline', 'both'."
            );
        }

        $this->filtersAppearance = $normalized;

        return $this;
    }

    public function filterAppearance(string $appearance): static
    {
        return $this->filtersAppearance($appearance);
    }

    public function filtersSize(string $size): static
    {
        $normalized = strtolower(trim($size));

        if ($normalized === 'base') {
            $normalized = 'default';
        }

        if (! in_array($normalized, ['xs', 'sm', 'default'], true)) {
            throw new \InvalidArgumentException(
                "Invalid filters size [{$size}]. Allowed values: 'xs', 'sm', 'default'."
            );
        }

        $this->filterInputSize = $normalized;

        return $this;
    }

    public function filterSize(string $size): static
    {
        return $this->filtersSize($size);
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

        $this->stickySections = $normalized;

        return $this;
    }

    public function pagination(array $config): static
    {
        if (array_key_exists('rowsPerPageOptions', $config) && is_array($config['rowsPerPageOptions'])) {
            $this->rowsPerPageOptions($config['rowsPerPageOptions']);
        } elseif (array_key_exists('perPageOptions', $config) && is_array($config['perPageOptions'])) {
            $this->rowsPerPageOptions($config['perPageOptions']);
        }

        if (array_key_exists('rowsPerPage', $config)) {
            $this->rowsPerPage($config['rowsPerPage']);
        }

        if (array_key_exists('rowsPerPageLabel', $config)) {
            $this->rowsPerPageLabel(is_string($config['rowsPerPageLabel']) ? $config['rowsPerPageLabel'] : null);
        } elseif (array_key_exists('perPageLabel', $config)) {
            $this->rowsPerPageLabel(is_string($config['perPageLabel']) ? $config['perPageLabel'] : null);
        }

        if (array_key_exists('rowsPerPageAllLabel', $config)) {
            $this->rowsPerPageAllLabel(is_string($config['rowsPerPageAllLabel']) ? $config['rowsPerPageAllLabel'] : null);
        } elseif (array_key_exists('perPageAllLabel', $config)) {
            $this->rowsPerPageAllLabel(is_string($config['perPageAllLabel']) ? $config['perPageAllLabel'] : null);
        }

        if (array_key_exists('paginationLabel', $config)) {
            $this->paginationLabel(is_string($config['paginationLabel']) ? $config['paginationLabel'] : null);
        }

        if (array_key_exists('showButtonLabel', $config)) {
            $this->showButtonLabel($this->toBoolean($config['showButtonLabel'], true));
        }

        if (array_key_exists('showFirstLabel', $config)) {
            $this->showFirstLabel($this->toBoolean($config['showFirstLabel'], true));
        }

        if (array_key_exists('showLastLabel', $config)) {
            $this->showLastLabel($this->toBoolean($config['showLastLabel'], true));
        }

        if (array_key_exists('ellipsisAfter', $config)) {
            $this->ellipsisAfter($config['ellipsisAfter']);
        }

        if (array_key_exists('firstButtonLabel', $config)) {
            $this->firstButtonLabel(is_string($config['firstButtonLabel']) ? $config['firstButtonLabel'] : null);
        }

        if (array_key_exists('previousButtonLabel', $config)) {
            $this->previousButtonLabel(is_string($config['previousButtonLabel']) ? $config['previousButtonLabel'] : null);
        }

        if (array_key_exists('nextButtonLabel', $config)) {
            $this->nextButtonLabel(is_string($config['nextButtonLabel']) ? $config['nextButtonLabel'] : null);
        }

        if (array_key_exists('lastButtonLabel', $config)) {
            $this->lastButtonLabel(is_string($config['lastButtonLabel']) ? $config['lastButtonLabel'] : null);
        }

        return $this;
    }

    public function perPage(array $options, ?string $rowsPerPageLabel = null): static
    {
        $this->rowsPerPageOptions($options);

        if ($rowsPerPageLabel !== null) {
            $this->rowsPerPageLabel = $rowsPerPageLabel;
        }

        return $this;
    }

    public function rowsPerPageOptions(array $options): static
    {
        $normalized = [];
        $hasAllOption = false;

        foreach ($options as $option) {
            if (is_numeric($option) === false) {
                continue;
            }

            $value = (int) $option;

            if ($value < 0) {
                continue;
            }

            if ($value === 0) {
                $hasAllOption = true;
                continue;
            }

            if (! in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        if ($hasAllOption) {
            $normalized[] = 0;
        }

        $this->perPageOptions = $normalized === [] ? [15] : $normalized;

        return $this;
    }

    public function rowsPerPage(int|string $rowsPerPage): static
    {
        if (! is_numeric($rowsPerPage)) {
            throw new \InvalidArgumentException('Rows per page must be numeric.');
        }

        $value = (int) $rowsPerPage;

        if ($value < 0) {
            throw new \InvalidArgumentException('Rows per page must be greater than or equal to 0.');
        }

        $this->rowsPerPage = $value;

        return $this;
    }

    public function rowsPerPageLabel(?string $label): static
    {
        $this->rowsPerPageLabel = $label;

        return $this;
    }

    public function perPageLabel(?string $label): static
    {
        return $this->rowsPerPageLabel($label);
    }

    public function rowsPerPageAllLabel(?string $label): static
    {
        $this->rowsPerPageAllLabel = $label;

        return $this;
    }

    public function paginationLabel(?string $label): static
    {
        $this->paginationLabel = $label;

        return $this;
    }

    public function showButtonLabel(bool $show = true): static
    {
        $this->showButtonLabel = $show;

        return $this;
    }

    public function showFirstLabel(bool $show = true): static
    {
        $this->showFirstLabel = $show;

        return $this;
    }

    public function showLastLabel(bool $show = true): static
    {
        $this->showLastLabel = $show;

        return $this;
    }

    public function ellipsisAfter(int|string $pages): static
    {
        if (! is_numeric($pages)) {
            throw new \InvalidArgumentException('Ellipsis after must be numeric.');
        }

        $value = (int) $pages;

        if ($value < 1) {
            throw new \InvalidArgumentException('Ellipsis after must be greater than or equal to 1.');
        }

        $this->ellipsisAfter = $value;

        return $this;
    }

    public function firstButtonLabel(?string $label): static
    {
        $this->firstButtonLabel = $label;

        return $this;
    }

    public function previousButtonLabel(?string $label): static
    {
        $this->previousButtonLabel = $label;

        return $this;
    }

    public function nextButtonLabel(?string $label): static
    {
        $this->nextButtonLabel = $label;

        return $this;
    }

    public function lastButtonLabel(?string $label): static
    {
        $this->lastButtonLabel = $label;

        return $this;
    }

    public function appearance(string $appearance): static
    {
        $this->appearance = $appearance;

        return $this;
    }

    public function getPerPageOptions(): array
    {
        $options = $this->perPageOptions;

        if ($this->rowsPerPage !== null && ! in_array($this->rowsPerPage, $options, true)) {
            if ($this->rowsPerPage === 0) {
                $options[] = 0;
            } elseif (in_array(0, $options, true)) {
                $allIndex = array_search(0, $options, true);

                if (is_int($allIndex)) {
                    array_splice($options, $allIndex, 0, [$this->rowsPerPage]);
                } else {
                    $options[] = $this->rowsPerPage;
                }
            } else {
                $options[] = $this->rowsPerPage;
            }
        }

        return $options;
    }

    public function getRowsPerPageOptions(): array
    {
        return $this->getPerPageOptions();
    }

    public function getDefaultRowsPerPage(): int
    {
        if ($this->rowsPerPage !== null) {
            return $this->rowsPerPage;
        }

        return $this->getPerPageOptions()[0] ?? 15;
    }

    public function resolveRowsPerPage(mixed $value): int
    {
        $default = $this->getDefaultRowsPerPage();

        if ($value === null || $value === '') {
            return $default;
        }

        if (! is_numeric($value)) {
            return $default;
        }

        $resolved = (int) $value;

        if ($resolved < 0) {
            return $default;
        }

        $allowed = $this->getPerPageOptions();

        if ($allowed !== [] && ! in_array($resolved, $allowed, true)) {
            return $default;
        }

        return $resolved;
    }

    public function setResolvedRowsPerPage(int $rowsPerPage): static
    {
        $this->resolvedRowsPerPage = max(0, $rowsPerPage);

        return $this;
    }

    public function getRowsPerPageLabel(): string
    {
        return $this->rowsPerPageLabel ?? __('Rows per page');
    }

    public function getRowsPerPageAllLabel(): string
    {
        return $this->rowsPerPageAllLabel ?? __('All');
    }

    public function getPaginationLabel(): string
    {
        return $this->paginationLabel ?? __('Page :currentPage of :totalPage');
    }

    public function getShowButtonLabel(): bool
    {
        return $this->showButtonLabel;
    }

    public function getShowFirstLabel(): bool
    {
        return $this->showFirstLabel;
    }

    public function getShowLastLabel(): bool
    {
        return $this->showLastLabel;
    }

    public function getEllipsisAfter(): int
    {
        return max(1, $this->ellipsisAfter);
    }

    public function getFirstButtonLabel(): string
    {
        return $this->firstButtonLabel ?? __('First');
    }

    public function getPreviousButtonLabel(): string
    {
        return $this->previousButtonLabel ?? __('Previous');
    }

    public function getNextButtonLabel(): string
    {
        return $this->nextButtonLabel ?? __('Next');
    }

    public function getLastButtonLabel(): string
    {
        return $this->lastButtonLabel ?? __('Last');
    }

    protected function toBoolean(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['false', '0', 'off', 'no'], true)) {
                return false;
            }

            if (in_array($normalized, ['true', '1', 'on', 'yes'], true)) {
                return true;
            }
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        return $default;
    }

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

    public function disableDefaultActions(array $types): static
    {
        $this->disabledDefaultActions = $types;

        return $this;
    }

    public function onlyDefaultActions(array $types): static
    {
        $this->onlyDefaultActions = $types;

        return $this;
    }

    public function withoutDefaultActions(): static
    {
        $this->useDefaultActions = false;

        return $this;
    }

    public function hasActionDisplay(): bool
    {
        return $this->actionDisplay !== null;
    }

    protected function defaultActions(): array
    {
        return [
            Action::view(),
            Action::edit(),
            Action::duplicate(),
            Action::delete()
                ->method('POST')
                ->confirm([
                    'title' => 'Czy na pewno?',
                    'description' => 'Tej operacji nie można cofnąć.',
                    'cancel' => 'Anuluj',
                    'ok' => 'Usuń',
                ]),
        ];
    }

    protected function defaultBulkActions(): array
    {
        return [
            BulkAction::delete(),
            BulkAction::duplicate(),
        ];
    }

    public function resolveBulkActions(): array
    {
        if (! $this->bulkEnabled) {
            return [];
        }

        $final = [];

        if ($this->useDefaultBulkActions) {
            $defaults = $this->defaultBulkActions();

            if ($this->onlyDefaultBulkActions !== null) {
                $defaults = array_filter($defaults, function (BulkAction $action) {
                    return in_array($action->getKey(), $this->onlyDefaultBulkActions, true);
                });
            }

            if (! empty($this->disabledDefaultBulkActions)) {
                $defaults = array_filter($defaults, function (BulkAction $action) {
                    return ! in_array($action->getKey(), $this->disabledDefaultBulkActions, true);
                });
            }

            foreach ($defaults as $action) {
                $final[$action->getKey()] = $action;
            }
        }

        foreach ($this->bulkActions as $action) {
            $normalized = $this->normalizeBulkActionDefinition($action);

            if (! $normalized instanceof BulkAction) {
                continue;
            }

            $final[$normalized->getKey()] = $normalized;
        }

        return array_values($final);
    }

    protected function normalizeBulkActionDefinition(mixed $definition): ?BulkAction
    {
        if ($definition instanceof BulkAction) {
            return $definition;
        }

        if (is_string($definition)) {
            $key = strtolower(trim($definition));

            if ($key === '') {
                return null;
            }

            return match ($key) {
                'delete' => BulkAction::delete(),
                'duplicate' => BulkAction::duplicate(),
                default => BulkAction::make($key),
            };
        }

        if (is_array($definition)) {
            return BulkAction::fromArray($definition);
        }

        return null;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function applySearch($query, string $search): void
    {
        $searchableColumns = $this->resolveSearchableColumns();

        if (! $searchableColumns) {
            return;
        }

        $query->where(function ($q) use ($search, $searchableColumns) {
            foreach ($searchableColumns as $column) {
                $q->orWhere($column, 'like', "%{$search}%");
            }
        });
    }

    public function applySort($query, string $sort): void
    {
        $direction = 'asc';

        if (str_starts_with($sort, '-')) {
            $direction = 'desc';
            $sort = substr($sort, 1);
        }

        $query->orderBy($sort, $direction);
    }

    protected function resolveActions(): array
    {
        $final = [];

        if ($this->useDefaultActions) {
            $defaults = $this->defaultActions();

            if ($this->onlyDefaultActions !== null) {
                $defaults = array_filter($defaults, function ($action) {
                    return in_array($action->getType(), $this->onlyDefaultActions);
                });
            }

            if (! empty($this->disabledDefaultActions)) {
                $defaults = array_filter($defaults, function ($action) {
                    return ! in_array($action->getType(), $this->disabledDefaultActions);
                });
            }

            foreach ($defaults as $action) {
                $final[$action->getType()] = $action;
            }
        }

        foreach ($this->actions as $action) {
            $final[$action->getType()] = $action;
        }

        foreach ($final as $action) {
            $action->baseUri($this->baseUri ?? '');

            if ($this->actionDisplay === TableActionDisplay::INLINE) {
                $action->component('ButtonLink');
            }

            if ($this->actionDisplay === TableActionDisplay::DROPDOWN) {
                $action->component('DropdownItem');
            }
        }

        return array_values($final);
    }

    protected function getSavedViews(): \Illuminate\Support\Collection
    {
        return collect([
            [
                'id' => 1,
                'tenant_id' => 10,
                'user_id' => 5,
                'resource' => 'patients',
                'name' => 'Moi pacjenci',
                'key' => 'my_patients',
                'filters' => [
                    [
                        'field' => 'assigned_user_id',
                        'operator' => '=',
                        'value' => 5,
                    ],
                ],
                'sort' => [
                    'field' => 'created_at',
                    'direction' => 'desc',
                ],
                'columns' => [
                    'first_name',
                    'last_name',
                    'email',
                    'status',
                ],
                'is_default' => true,
            ],

            [
                'id' => 2,
                'tenant_id' => 10,
                'user_id' => 5,
                'resource' => 'patients',
                'name' => 'Nowi w tym miesiącu',
                'key' => 'new_this_month',
                'filters' => [
                    [
                        'field' => 'created_at',
                        'operator' => '>=',
                        'value' => now()->startOfMonth()->toDateString(),
                    ],
                ],
                'sort' => [
                    'field' => 'created_at',
                    'direction' => 'desc',
                ],
                'columns' => [
                    'first_name',
                    'last_name',
                    'phone',
                    'created_at',
                ],
                'is_default' => false,
            ],

            [
                'id' => 3,
                'tenant_id' => 10,
                'user_id' => 5,
                'resource' => 'patients',
                'name' => 'Zaległe płatności',
                'key' => 'overdue_payments',
                'filters' => [
                    [
                        'field' => 'payment_status',
                        'operator' => '=',
                        'value' => 'overdue',
                    ],
                ],
                'sort' => [
                    'field' => 'last_visit_at',
                    'direction' => 'asc',
                ],
                'columns' => [
                    'first_name',
                    'last_name',
                    'last_visit_at',
                    'payment_status',
                ],
                'is_default' => false,
            ],
        ])
            ->map(fn ($view) => (object) $view);
    }

    protected function buildTabs(): ?Tab
    {
        $items = $this->tabs ?? [];

        if ($this->tabsFromViews) {
            foreach ($this->getSavedViews() as $view) {

                $tabItem = TabItem::make($view->name)
                    ->prop('value', $view->key);

                $items[] = $tabItem;
            }
        }

        if (empty($items)) {
            return null;
        }

        $default = $items[0]->getProp('value') ?? null;

        return Tab::make('table_tabs')
            ->prop('defaultValue', $default)
            ->items($items);
    }

    protected function resolveSearchbarComponents(): array
    {
        if (! $this->searchbar) {
            return [];
        }

        $components = [];

        if ($this->searchbar === true) {
            $components[] = InputSearch::make('search');
        } elseif ($this->searchbar instanceof Component) {
            $components[] = $this->searchbar;
        } elseif (is_array($this->searchbar)) {
            $components = $this->searchbar;
        }
        foreach ($components as $component) {
            if ($component instanceof DropdownSearch) {
                $component->resolveFromQuery($this->query);
            }
        }

        if ($components) {
            $components[] = $this->resolveFilterComponents($this->resolveFilters());
        }

        return $components;
    }

    protected function resolveSearchAppearanceProps(): array
    {
        return $this->searchAppearanceProps;
    }

    protected function resolveHeaderComponents(): array
    {
        $components = [];
        $serializedColumns = $this->serializeColumns();

        foreach ($this->headerComponents as $component) {
            if (! is_object($component) || ! method_exists($component, 'toArray')) {
                continue;
            }

            if (method_exists($component, 'baseUri')) {
                $component->baseUri($this->baseUri ?? '');
            }

            if ($component instanceof ColumnVisibility) {
                $component->columns($serializedColumns);
            }

            $components[] = $component;
        }

        if (! $this->resolveSearchbarComponents()) {
            $components[] = $this->resolveFilterComponents($this->resolveFilters());
        }

        return $components;
    }

    protected function resolveFilterComponents(array $filters)
    {
        if (! $this->hasDrawerFilters($filters)) {
            return [];
        }

        return Drawer::make()
            ->children([
                DrawerTrigger::make()
                    ->prop('asChild', true)
                    ->children([
                        Button::make('Filtruj')
                            ->variant('outline')
                            ->size('sm'),
                    ]),
                DrawerContent::make()
                    ->children([
                        DrawerHeader::make()
                            ->children([
                                DrawerTitle::make()
                                    ->children([
                                        Text::make('Filtry'),
                                    ]),
                            ]),
                        TableFilters::make()->filters($filters),
                        DrawerFooter::make()
                            ->children([
                                DrawerClose::make()
                                    ->prop('asChild', true)
                                    ->children([
                                        Button::make('Zamknij')
                                            ->variant('outline'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    protected function hasDrawerFilters(array $filters): bool
    {
        foreach ($filters as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $appearance = strtolower((string) ($definition['appearance'] ?? $this->filtersAppearance));

            if (in_array($appearance, ['drawer', 'both'], true)) {
                return true;
            }
        }

        return false;
    }

    protected function resolveSearchableColumns(): array
    {
        if (! empty($this->searchable)) {
            return $this->searchable;
        }

        $columns = [];

        foreach ($this->columnObjects as $column) {
            if (! $column instanceof Column || ! $column->isSearchable()) {
                continue;
            }

            $columns[] = $column->getKey();
        }

        return array_values(array_filter(
            array_unique($columns),
            static fn ($column) => is_string($column) && trim($column) !== ''
        ));
    }

    protected function resolveFilters(): array
    {
        $definitionsByField = [];

        foreach ($this->columnObjects as $column) {
            if (! $column instanceof Column || ! $column->hasFilter()) {
                continue;
            }

            $definition = $column->toFilterDefinition($this->filtersAppearance);

            if ($definition === null) {
                continue;
            }

            $normalized = $this->normalizeFilterDefinition($definition);

            if ($normalized === null) {
                continue;
            }

            $definitionsByField[$normalized['field']] = $normalized;
        }

        foreach ($this->filters as $filter) {
            if ($filter instanceof Filter) {
                $definition = $filter->toArray($this->filtersAppearance);
            } elseif (is_array($filter)) {
                $definition = $filter;
            } else {
                continue;
            }

            $normalized = $this->normalizeFilterDefinition($definition, true);

            if ($normalized === null) {
                continue;
            }

            $definitionsByField[$normalized['field']] = $normalized;
        }

        return array_values($definitionsByField);
    }

    protected function normalizeFilterDefinition(array $definition, bool $forceDrawer = false): ?array
    {
        $field = trim((string) ($definition['field'] ?? ''));

        if ($field === '') {
            return null;
        }

        $type = trim((string) ($definition['type'] ?? 'string'));
        if ($type === '') {
            $type = 'string';
        }

        $appearance = strtolower(trim((string) ($definition['appearance'] ?? $this->filtersAppearance)));
        if ($forceDrawer) {
            $appearance = 'drawer';
        } elseif (! in_array($appearance, ['drawer', 'inline', 'both'], true)) {
            $appearance = $this->filtersAppearance;
        }

        $modeDefault = $forceDrawer ? 'single' : 'multiple';
        $mode = $definition['mode'] ?? null;

        if (! is_string($mode) && isset($definition['multiple']) && is_bool($definition['multiple'])) {
            $mode = $definition['multiple'] ? 'multiple' : 'single';
        }

        $mode = strtolower(trim((string) ($mode ?? $modeDefault)));
        if (! in_array($mode, ['single', 'multiple'], true)) {
            $mode = $modeDefault;
        }

        $operators = $this->normalizeFilterOperators($definition['operators'] ?? []);
        $rule = (bool) ($definition['rule'] ?? false);

        if ($mode === 'multiple') {
            $rule = true;
        }

        if ($rule && empty($operators)) {
            $operators = $this->defaultOperatorsForFilterType($type);
        }

        $label = trim((string) ($definition['label'] ?? ''));
        if ($label === '') {
            $label = ucfirst($field);
        }

        return [
            'field' => $field,
            'label' => $label,
            'type' => $type,
            'appearance' => $appearance,
            'mode' => $mode,
            'multiple' => $mode === 'multiple',
            'rule' => $rule,
            'operators' => $this->toOperatorOptions($operators),
        ];
    }

    protected function normalizeFilterOperators(mixed $operators): array
    {
        if (! is_array($operators)) {
            return [];
        }

        $normalized = array_map(function ($operator) {
            if (is_array($operator)) {
                $operator = $operator['value'] ?? '';
            }

            if (! is_string($operator)) {
                return '';
            }

            return $this->normalizeFilterOperatorName($operator);
        }, $operators);

        $normalized = array_values(array_filter($normalized, static fn (string $operator) => $operator !== ''));

        return array_values(array_unique($normalized));
    }

    protected function normalizeFilterOperatorName(string $operator): string
    {
        $normalized = strtolower(trim($operator));

        return match ($normalized) {
            'start_with' => 'starts_with',
            'end_with' => 'ends_with',
            'not_start_with' => 'not_starts_with',
            'not_end_with' => 'not_ends_with',
            default => $normalized,
        };
    }

    protected function defaultOperatorsForFilterType(string $type): array
    {
        return match (strtolower($type)) {
            'number', 'numeric', 'int', 'integer', 'float', 'decimal' => [
                '=', '!=', '>', '>=', '<', '<=', 'between', 'in', 'not_in', 'is_null', 'is_not_null',
            ],
            'date', 'datetime', 'timestamp' => [
                '=', '!=', 'after', 'before', 'between', 'is_null', 'is_not_null',
            ],
            'bool', 'boolean' => [
                '=', '!=', 'is_null', 'is_not_null',
            ],
            default => [
                'contains', 'starts_with', 'ends_with', '=', '!=', 'is_null', 'is_not_null', 'is_empty', 'is_not_empty',
            ],
        };
    }

    protected function toOperatorOptions(array $operators): array
    {
        return array_map(
            fn (string $operator) => [
                'value' => $operator,
                'label' => $this->operatorLabel($operator),
            ],
            $operators
        );
    }

    protected function operatorLabel(string $operator): string
    {
        return match ($operator) {
            'contains' => __('contains'),
            'starts_with' => __('starts with'),
            'ends_with' => __('ends with'),
            'not_starts_with' => __('does not start with'),
            'not_ends_with' => __('does not end with'),
            '=' => __('is equal to'),
            '!=' => __('is not equal to'),
            '>' => __('is greater than'),
            '>=' => __('is greater than or equal to'),
            '<' => __('is less than'),
            '<=' => __('is less than or equal to'),
            'between' => __('between'),
            'in' => __('in'),
            'not_in' => __('not in'),
            'after' => __('after'),
            'before' => __('before'),
            'is_null' => __('is null'),
            'is_not_null' => __('is not null'),
            'is_empty' => __('is empty'),
            'is_not_empty' => __('is not empty'),
            default => __(str_replace('_', ' ', $operator)),
        };
    }

    protected function serializeColumns(): array
    {
        return array_map(function ($column) {

            if ($column instanceof Column) {
                return $column->toArray();
            }

            if (is_array($column)) {
                return $column;
            }

            throw new \InvalidArgumentException('Invalid column definition.');
        }, $this->columnObjects);
    }

    protected function resolveBulkMode(): ?string
    {
        if (! $this->bulkEnabled) {
            return null;
        }

        return $this->bulkMode ?? 'multiple';
    }

    protected function resolveNumberingMode(): ?string
    {
        if (! $this->numberingEnabled) {
            return null;
        }

        return $this->numberingMode ?? 'continuous';
    }

    protected function buildSelectionHeaderCell(string $bulkMode): TableHead
    {
        $head = TableHead::make()->appearance($this->selectionColumnAppearance());

        if ($bulkMode === 'multiple') {
            return $head->children([
                Checkbox::make()
                    ->name('bulk_select_all')
                    ->prop('ariaLabel', 'Select all rows')
                    ->prop('data-bulk-select-all', true),
            ]);
        }

        return $head;
    }

    protected function buildSelectionCell(array $row, string $bulkMode): TableCell
    {
        $rowKey = (string) ($row['id'] ?? $row['uuid'] ?? uniqid('row_', true));

        $control = $bulkMode === 'single'
            ? Radio::make()
                ->name('bulk_row_selection')
                ->value($rowKey)
                ->prop('ariaLabel', 'Select row')
                ->prop('data-row-selection', true)
                ->prop('data-row-key', $rowKey)
            : Checkbox::make()
                ->name('bulk_row_selection[]')
                ->value($rowKey)
                ->prop('ariaLabel', 'Select row')
                ->prop('data-row-selection', true)
                ->prop('data-row-key', $rowKey);

        return TableCell::make()
            ->appearance($this->selectionColumnAppearance())
            ->children([$control]);
    }

    protected function selectionColumnAppearance(): array
    {
        return [
            'width' => '36px',
        ];
    }

    protected function buildNumberingHeaderCell(): TableHead
    {
        return TableHead::make()->children([
            Text::make($this->numberingLabel),
        ]);
    }

    protected function buildNumberingCell(int $number): TableCell
    {
        return TableCell::make()->children([
            Text::make((string) $number),
        ]);
    }

    protected function shouldRenderInlineFilters(): bool
    {
        foreach ($this->columnObjects as $column) {
            if (! $column instanceof Column || ! $column->hasFilter()) {
                continue;
            }

            $definition = $column->toFilterDefinition($this->filtersAppearance);

            if (! is_array($definition)) {
                continue;
            }

            $appearance = strtolower((string) ($definition['appearance'] ?? $this->filtersAppearance));

            if (in_array($appearance, ['inline', 'both'], true)) {
                return true;
            }
        }

        return false;
    }

    protected function buildInlineFilterHeadCell(Column $column): TableHead
    {
        $head = TableHead::make()->prop('class', 'py-2');
        $searchAppearance = $column->getSearchAppearance();

        if (is_array($searchAppearance) && ! empty($searchAppearance)) {
            $head->appearance($searchAppearance);
        }

        if (! $column->hasFilter()) {
            return $head;
        }

        $definition = $column->toFilterDefinition($this->filtersAppearance);

        if (! is_array($definition) || empty($definition['field'])) {
            return $head;
        }

        $appearance = strtolower((string) ($definition['appearance'] ?? $this->filtersAppearance));

        if (! in_array($appearance, ['inline', 'both'], true)) {
            return $head;
        }

        $label = (string) ($definition['label'] ?? $column->toHeaderProps()['label'] ?? 'Filtr');
        $field = (string) $definition['field'];
        $operators = is_array($definition['operators'] ?? null) ? $definition['operators'] : [];
        $mode = (string) ($definition['mode'] ?? 'multiple');
        $type = (string) ($definition['type'] ?? 'string');

        $input = InputSearch::make('filter_'.$field)
            ->placeholder($label)
            ->prop('field', $field)
            ->prop('operators', $operators)
            ->prop('mode', $mode)
            ->prop('size', $this->filterInputSize)
            ->prop('type', $type);

        return $head->children([$input]);
    }

    protected function buildHeader(bool $hasActions, ?string $bulkMode, ?string $numberingMode): TableHeader
    {
        $heads = [];
        $globalHeaderAppearance = $this->headerAppearanceProps['appearance'] ?? [];
        $globalHeaderAppearance = is_array($globalHeaderAppearance) ? $globalHeaderAppearance : [];

        if ($bulkMode !== null) {
            $heads[] = $this->buildSelectionHeaderCell($bulkMode);
        }

        if ($numberingMode !== null) {
            $heads[] = $this->buildNumberingHeaderCell();
        }

        foreach ($this->columnObjects as $column) {
            $headProps = $column->toHeaderProps();

            if (! empty($globalHeaderAppearance)) {
                $columnHeaderAppearance = $headProps['appearance'] ?? [];
                $columnHeaderAppearance = is_array($columnHeaderAppearance) ? $columnHeaderAppearance : [];

                $headProps['appearance'] = [
                    ...$globalHeaderAppearance,
                    ...$columnHeaderAppearance,
                ];
            }

            $heads[] = TableHead::make()->props($headProps);
        }

        if ($hasActions) {
            $heads[] = TableHead::make()->children([
                Dialog::make()
                    ->title('Custom columns')
                    ->cancel('Cancel')
                    ->ok('Save')
                    ->slot('trigger', Button::make()
                        ->variant('ghost')
                        ->size('icon-sm')
                        ->icon(Icon::make('lucide:plus')
                    )),
            ]);
        }

        $rows = [
            TableRow::make()->children($heads),
        ];

        if ($this->shouldRenderInlineFilters()) {
            $filterHeads = [];

            if ($bulkMode !== null) {
                $filterHeads[] = TableHead::make()
                    ->appearance($this->selectionColumnAppearance())
                    ->prop('class', 'py-2');
            }

            if ($numberingMode !== null) {
                $filterHeads[] = TableHead::make()->prop('class', 'py-2');
            }

            foreach ($this->columnObjects as $column) {
                if (! $column instanceof Column) {
                    $filterHeads[] = TableHead::make()->prop('class', 'py-2');
                    continue;
                }

                $filterHeads[] = $this->buildInlineFilterHeadCell($column);
            }

            if ($hasActions) {
                $filterHeads[] = TableHead::make()->prop('class', 'py-2');
            }

            $rows[] = TableRow::make()->children($filterHeads);
        }

        return TableHeader::make()
            ->props($this->headerAppearanceProps)
            ->children($rows);
    }

    protected function buildBody($paginator, array $resolvedActions, ?string $bulkMode, ?string $numberingMode): TableBody
    {
        $rows = [];
        $number = $numberingMode === null
            ? null
            : $this->resolveNumberingStart($paginator, $numberingMode);

        foreach ($paginator->items() as $model) {
            $rows[] = $this->buildRow($model, $resolvedActions, $bulkMode, $number);

            if ($number !== null) {
                $number++;
            }
        }

        return TableBody::make()
            ->props($this->bodyAppearanceProps)
            ->children($rows);
    }

    protected function resolveNumberingStart(LengthAwarePaginator $paginator, string $numberingMode): int
    {
        if ($numberingMode === 'per_page') {
            return 1;
        }

        return (($paginator->currentPage() - 1) * $paginator->perPage()) + 1;
    }

    protected function buildRow(Model $model, array $resolvedActions, ?string $bulkMode, ?int $number): TableRow
    {
        $data = $model->toArray();
        $data['_model'] = get_class($model);

        $cells = [];

        if ($bulkMode !== null) {
            $cells[] = $this->buildSelectionCell($data, $bulkMode);
        }

        if ($number !== null) {
            $cells[] = $this->buildNumberingCell($number);
        }

        foreach ($this->columnObjects as $column) {
            $cells[] = $this->buildCell($column, $data);
        }

        if (! empty($resolvedActions)) {
            $cells[] = $this->buildActionsCell($data, $resolvedActions);
        }

        return TableRow::make()->children($cells);
    }

    protected function buildActionsCell(array $row, array $resolvedActions): TableCell
    {
        $components = [];

        foreach ($resolvedActions as $action) {
            $component = $action->resolve($row);

            if ($component instanceof Component) {
                $components[] = $component;
            }
        }

        if (empty($components)) {
            return TableCell::make();
        }

        if ($this->actionDisplay === TableActionDisplay::DROPDOWN) {
            $dropdown = Dropdown::make()
                ->trigger(
                    Button::make()
                        ->icon(Icon::make('lucide:ellipsis-vertical'))
                        ->variant('ghost')
                        ->size('icon-sm')
                )
                ->children($components);

            return TableCell::make()
                ->width('10')
                ->children([$dropdown]);
        }

        return TableCell::make()
            ->appearance([
                'class' => 'flex gap-3',
            ])
            ->children($components);
    }

    protected function buildCell(Column $column, array $row): TableCell
    {
        $value = $column->resolveState($row);
        $displayValue = $this->normalizeCellValue($value);
        $isPlaceholder = $column->wasPlaceholderApplied();

        $cell = TableCell::make();
        $bodyAppearance = $column->getBodyAppearance();

        if (is_array($bodyAppearance) && ! empty($bodyAppearance)) {
            $cell->appearance($bodyAppearance);
        }

        if ($isPlaceholder) {
            $cell->appearance([
                'class' => 'text-muted-foreground',
            ]);
        }

        if ($column->hasAction()) {

            $action = clone $column->getAction();
            $action->baseUri($this->baseUri ?? '');
            $action->component('ButtonLink');
            $action->label($displayValue);
            $action->icon(null);

            $component = $action->resolve($row);

            return $cell->children([
                $component,
            ]);
        }

        return $cell->children([
            Text::make($displayValue),
        ]);
    }

    protected function normalizeCellValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded === false ? '' : $encoded;
        }

        return '';
    }

    protected function resolveFooterValues(LengthAwarePaginator $paginator): array
    {
        $footer = [];
        $pageRows = array_map(function ($item) {
            if ($item instanceof Model) {
                return $item->toArray();
            }

            return is_array($item) ? $item : (array) $item;
        }, $paginator->items());

        foreach ($this->columnObjects as $column) {
            if (! $column instanceof Column) {
                continue;
            }

            $definition = $column->getFooterDefinition();

            if (! is_string($definition) || trim($definition) === '') {
                continue;
            }

            $key = $column->getKey();
            $tokens = $this->extractFooterTokens($definition);
            $groupExpressions = $this->extractGroupedFooterExpressions($definition);

            if (empty($tokens) && empty($groupExpressions)) {
                $footer[$key] = $definition;
                continue;
            }

            $page = $this->resolveFooterPageMetrics($key, $pageRows);
            $metrics = [
                'sum' => $page['sum'],
                'count' => $page['count'],
                'between' => $page['between'],
                'min' => $page['min'],
                'max' => $page['max'],
                'average' => $page['average'],
            ];

            if ($this->footerNeedsTotalMetrics($tokens) || $this->footerNeedsTotalGroupedMetrics($groupExpressions)) {
                $total = $this->resolveFooterTotalMetrics($key);
                $metrics += [
                    'total_sum' => $total['sum'],
                    'total_count' => $total['count'],
                    'total_between' => $total['between'],
                    'total_min' => $total['min'],
                    'total_max' => $total['max'],
                    'total_average' => $total['average'],
                ];
            }

            $footer[$key] = $this->renderFooterValue(
                $definition,
                $tokens,
                $metrics,
                $groupExpressions,
                $key,
                $pageRows
            );
        }

        return $footer;
    }

    protected function extractFooterTokens(string $definition): array
    {
        $known = [
            'sum', 'count', 'between', 'min', 'max', 'average',
            'total_sum', 'total_count', 'total_between', 'total_min', 'total_max', 'total_average',
        ];

        $tokens = [];
        $normalized = strtolower(trim($definition));

        if (in_array($normalized, $known, true)) {
            $tokens[] = $normalized;
        }

        preg_match_all('/:([a-z_]+)/i', $definition, $matches);

        foreach ($matches[1] ?? [] as $match) {
            $token = strtolower(trim((string) $match));

            if (in_array($token, $known, true)) {
                $tokens[] = $token;
            }
        }

        return array_values(array_unique($tokens));
    }

    protected function footerNeedsTotalMetrics(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (str_starts_with($token, 'total_')) {
                return true;
            }
        }

        return false;
    }

    protected function extractGroupedFooterExpressions(string $definition): array
    {
        $expressions = [];
        $trimmed = trim($definition);

        if (preg_match('/^((?:total_)?sum_by\([a-zA-Z0-9_.]+\)|(?:total_)?sum_where\([^()]+\))$/i', $trimmed, $direct)) {
            $expressions[] = strtolower($direct[1]);
        }

        preg_match_all('/:((?:total_)?sum_by\([a-zA-Z0-9_.]+\)|(?:total_)?sum_where\([^()]+\))/i', $definition, $matches);

        foreach ($matches[1] ?? [] as $match) {
            $expressions[] = strtolower(trim((string) $match));
        }

        return array_values(array_unique($expressions));
    }

    protected function footerNeedsTotalGroupedMetrics(array $groupExpressions): bool
    {
        foreach ($groupExpressions as $expression) {
            if (str_starts_with($expression, 'total_')) {
                return true;
            }
        }

        return false;
    }

    protected function resolveFooterPageMetrics(string $key, array $rows): array
    {
        $values = $this->resolveFooterValuesForKey($rows, $key);
        $sum = $this->footerSum($values);
        $count = $this->footerCount($values);
        $min = $this->footerMin($values);
        $max = $this->footerMax($values);
        $average = $this->footerAverage($values);

        return [
            'sum' => $sum,
            'count' => $count,
            'min' => $min,
            'max' => $max,
            'between' => $this->footerBetween($min, $max),
            'average' => $average,
        ];
    }

    protected function resolveFooterTotalMetrics(string $key): array
    {
        if (isset($this->footerTotalAggregatesCache[$key])) {
            return $this->footerTotalAggregatesCache[$key];
        }

        if (str_contains($key, '.')) {
            $metrics = $this->resolveFooterPageMetrics($key, $this->resolveFooterTotalRows());
            $this->footerTotalAggregatesCache[$key] = $metrics;

            return $metrics;
        }

        $query = $this->newAggregateQuery();
        $sum = (clone $query)->sum($key);
        $count = (clone $query)->count($key);
        $min = (clone $query)->min($key);
        $max = (clone $query)->max($key);
        $average = (clone $query)->avg($key);

        $metrics = [
            'sum' => $sum,
            'count' => (int) $count,
            'min' => $min,
            'max' => $max,
            'between' => $this->footerBetween($min, $max),
            'average' => $average,
        ];

        $this->footerTotalAggregatesCache[$key] = $metrics;

        return $metrics;
    }

    protected function newAggregateQuery(): EloquentBuilder
    {
        $query = clone $this->query;
        $base = $query->getQuery();

        $base->orders = null;
        $base->unionOrders = null;
        $base->limit = null;
        $base->offset = null;
        if (property_exists($base, 'unionLimit')) {
            $base->unionLimit = null;
        }
        if (property_exists($base, 'unionOffset')) {
            $base->unionOffset = null;
        }

        return $query;
    }

    protected function resolveFooterTotalRows(): array
    {
        if ($this->footerTotalRowsCache !== null) {
            return $this->footerTotalRowsCache;
        }

        $this->footerTotalRowsCache = $this->newAggregateQuery()->get()->map(function ($item) {
            if ($item instanceof Model) {
                return $item->toArray();
            }

            return is_array($item) ? $item : (array) $item;
        })->all();

        return $this->footerTotalRowsCache;
    }

    protected function resolveFooterValuesForKey(array $rows, string $key): array
    {
        $values = [];

        foreach ($rows as $row) {
            $values[] = data_get($row, $key);
        }

        return $values;
    }

    protected function renderFooterValue(
        string $definition,
        array $tokens,
        array $metrics,
        array $groupExpressions,
        string $valueKey,
        array $pageRows
    ): string
    {
        $normalized = strtolower(trim($definition));

        if (in_array($normalized, $tokens, true) && count($tokens) === 1 && empty($groupExpressions)) {
            return $this->normalizeFooterValue($metrics[$normalized] ?? null);
        }

        if (in_array($normalized, $groupExpressions, true)) {
            return $this->resolveGroupedFooterExpressionValue($normalized, $valueKey, $pageRows);
        }

        $result = $definition;

        foreach ($groupExpressions as $expression) {
            $result = str_ireplace(
                ':'.$expression,
                $this->resolveGroupedFooterExpressionValue($expression, $valueKey, $pageRows),
                $result
            );
        }

        foreach ($tokens as $token) {
            $result = str_replace(':'.$token, $this->normalizeFooterValue($metrics[$token] ?? null), $result);
        }

        return $result;
    }

    protected function resolveGroupedFooterExpressionValue(string $expression, string $valueKey, array $pageRows): string
    {
        if (preg_match('/^(total_)?sum_by\(([a-zA-Z0-9_.]+)\)$/i', $expression, $matches)) {
            $scope = strtolower((string) ($matches[1] ?? ''));
            $groupKey = (string) ($matches[2] ?? '');

            if ($groupKey === '') {
                return '';
            }

            $rows = $scope === 'total_'
                ? $this->resolveFooterTotalRows()
                : $pageRows;

            return $this->resolveGroupedSumBy($rows, $valueKey, $groupKey);
        }

        if (preg_match('/^(total_)?sum_where\(([a-zA-Z0-9_.]+),(.+)\)$/i', $expression, $matches)) {
            $scope = strtolower((string) ($matches[1] ?? ''));
            $filterKey = trim((string) ($matches[2] ?? ''));
            $expectedRaw = trim((string) ($matches[3] ?? ''));

            if ($filterKey === '' || $expectedRaw === '') {
                return '';
            }

            if (
                (str_starts_with($expectedRaw, "'") && str_ends_with($expectedRaw, "'")) ||
                (str_starts_with($expectedRaw, '"') && str_ends_with($expectedRaw, '"'))
            ) {
                $expectedRaw = substr($expectedRaw, 1, -1);
            }

            $rows = $scope === 'total_'
                ? $this->resolveFooterTotalRows()
                : $pageRows;

            return $this->normalizeFooterValue(
                $this->resolveConditionalSum($rows, $valueKey, $filterKey, $expectedRaw)
            );
        }

        return '';
    }

    protected function resolveGroupedSumBy(array $rows, string $valueKey, string $groupKey): string
    {
        $grouped = [];

        foreach ($rows as $row) {
            $group = trim((string) data_get($row, $groupKey));
            $value = data_get($row, $valueKey);

            if (! is_numeric($value)) {
                continue;
            }

            if (! isset($grouped[$group])) {
                $grouped[$group] = 0.0;
            }

            $grouped[$group] += (float) $value;
        }

        if ($grouped === []) {
            return '';
        }

        ksort($grouped);

        $parts = [];

        foreach ($grouped as $group => $sum) {
            $formatted = number_format($sum, 2, ',', ' ');
            $parts[] = $group !== '' ? "{$formatted} {$group}" : $formatted;
        }

        return implode(' ', $parts);
    }

    protected function resolveConditionalSum(array $rows, string $valueKey, string $filterKey, string $expected): float|int
    {
        $sum = 0.0;

        foreach ($rows as $row) {
            $actual = data_get($row, $filterKey);

            if (! $this->footerMatchesCondition($actual, $expected)) {
                continue;
            }

            $value = data_get($row, $valueKey);

            if (is_string($value)) {
                $value = trim($value);
            }

            if (! is_numeric($value)) {
                continue;
            }

            $sum += (float) $value;
        }

        if (floor($sum) === $sum) {
            return (int) $sum;
        }

        return $sum;
    }

    protected function footerMatchesCondition(mixed $actual, string $expected): bool
    {
        if ($actual === null) {
            return false;
        }

        $expected = trim($expected);

        if (is_string($actual)) {
            $actual = trim($actual);
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        if (is_bool($actual)) {
            $expectedBool = match (strtolower($expected)) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off' => false,
                default => null,
            };

            return $expectedBool !== null && $actual === $expectedBool;
        }

        if (! is_scalar($actual)) {
            return false;
        }

        return strtolower((string) $actual) === strtolower($expected);
    }

    protected function footerSum(array $values): float|int
    {
        $sum = 0.0;

        foreach ($values as $value) {
            if (is_string($value)) {
                $value = trim($value);
            }

            if (is_numeric($value)) {
                $sum += (float) $value;
            }
        }

        if (floor($sum) === $sum) {
            return (int) $sum;
        }

        return $sum;
    }

    protected function footerCount(array $values): int
    {
        $count = 0;

        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            $count++;
        }

        return $count;
    }

    protected function footerAverage(array $values): float|int|null
    {
        $sum = 0.0;
        $count = 0;

        foreach ($values as $value) {
            if (is_string($value)) {
                $value = trim($value);
            }

            if (! is_numeric($value)) {
                continue;
            }

            $sum += (float) $value;
            $count++;
        }

        if ($count === 0) {
            return null;
        }

        $average = $sum / $count;

        if (floor($average) === $average) {
            return (int) $average;
        }

        return $average;
    }

    protected function footerMin(array $values): mixed
    {
        return $this->footerBoundary($values, 'min');
    }

    protected function footerMax(array $values): mixed
    {
        return $this->footerBoundary($values, 'max');
    }

    protected function footerBoundary(array $values, string $type): mixed
    {
        $entries = [];

        foreach ($values as $value) {
            $entry = $this->normalizeFooterComparable($value);

            if ($entry === null) {
                continue;
            }

            $entries[] = $entry;
        }

        if (empty($entries)) {
            return null;
        }

        $types = array_values(array_unique(array_map(static fn (array $entry) => $entry['type'], $entries)));

        usort($entries, static function (array $left, array $right) use ($types): int {
            if (count($types) > 1) {
                return strcmp((string) $left['display'], (string) $right['display']);
            }

            if ($left['sort'] === $right['sort']) {
                return 0;
            }

            return $left['sort'] <=> $right['sort'];
        });

        $picked = $type === 'max'
            ? $entries[array_key_last($entries)]
            : $entries[0];

        return $picked['raw'];
    }

    protected function normalizeFooterComparable(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return [
                'type' => 'date',
                'sort' => $value->getTimestamp(),
                'display' => $value->format('Y-m-d H:i:s'),
                'raw' => $value->format('Y-m-d H:i:s'),
            ];
        }

        if (is_bool($value)) {
            return [
                'type' => 'number',
                'sort' => $value ? 1 : 0,
                'display' => $value ? '1' : '0',
                'raw' => $value ? 1 : 0,
            ];
        }

        if (is_numeric($value)) {
            return [
                'type' => 'number',
                'sort' => (float) $value,
                'display' => (string) $value,
                'raw' => $value,
            ];
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if ((str_contains($trimmed, '-') || str_contains($trimmed, ':') || str_contains($trimmed, '/')) && strtotime($trimmed) !== false) {
            return [
                'type' => 'date',
                'sort' => strtotime($trimmed),
                'display' => $trimmed,
                'raw' => $trimmed,
            ];
        }

        return [
            'type' => 'string',
            'sort' => $trimmed,
            'display' => $trimmed,
            'raw' => $trimmed,
        ];
    }

    protected function footerBetween(mixed $min, mixed $max): ?string
    {
        $minText = $this->normalizeFooterValue($min);
        $maxText = $this->normalizeFooterValue($max);

        if ($minText === '' && $maxText === '') {
            return null;
        }

        if ($minText === '') {
            return $maxText;
        }

        if ($maxText === '') {
            return $minText;
        }

        return "{$minText} - {$maxText}";
    }

    protected function normalizeFooterValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (floor($value) === $value) {
                return (string) (int) $value;
            }

            return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded === false ? '' : $encoded;
        }

        return '';
    }

    protected function buildFooter(array $footerValues, bool $hasActions, ?string $bulkMode, ?string $numberingMode): ?TableFooter
    {
        if ($footerValues === []) {
            return null;
        }

        $cells = [];

        if ($bulkMode !== null) {
            $cells[] = TableCell::make()->appearance($this->selectionColumnAppearance());
        }

        if ($numberingMode !== null) {
            $cells[] = TableCell::make();
        }

        foreach ($this->columnObjects as $column) {
            if (! $column instanceof Column) {
                $cells[] = TableCell::make();
                continue;
            }

            $key = $column->getKey();
            $value = $footerValues[$key] ?? '';
            $cell = TableCell::make();

            $columnAppearance = $column->getFooterAppearance();

            if (is_array($columnAppearance) && ! empty($columnAppearance)) {
                $cell->appearance($columnAppearance);
            }

            $cells[] = $cell->children([
                Text::make($this->normalizeFooterValue($value)),
            ]);
        }

        if ($hasActions) {
            $cells[] = TableCell::make();
        }

        return TableFooter::make()->children([
            TableRow::make()->children($cells),
        ]);
    }

    public function build(LengthAwarePaginator $paginator): Table
    {
        $actionDisplay = $this->actionDisplay ?? config('svarium.table.action_display', 'inline');
        $bulkMode = $this->resolveBulkMode();
        $numberingMode = $this->resolveNumberingMode();
        $resolvedActions = $this->resolveActions();
        $hasActions = ! empty($resolvedActions);

        $body = $this->buildBody($paginator, $resolvedActions, $bulkMode, $numberingMode);
        $header = $this->buildHeader($hasActions, $bulkMode, $numberingMode);
        $footer = $this->resolveFooterValues($paginator);
        $footerComponent = $this->buildFooter($footer, $hasActions, $bulkMode, $numberingMode);
        $tabs = $this->buildTabs();
        $resolvedFilters = $this->resolveFilters();

        $tableChildren = [
            $header,
            $body,
        ];

        if ($footerComponent !== null) {
            $tableChildren[] = $footerComponent;
        }

        $resolvedRowsPerPage = $this->resolvedRowsPerPage ?? $paginator->perPage();
        $resolvedRowsPerPageOptions = $this->getPerPageOptions();

        $table = Table::make()
            ->prop('columns', $this->serializeColumns())
            ->children($tableChildren)
            ->actions($resolvedActions)
            ->actionDisplay($actionDisplay)
            ->slot('searchbar', $this->resolveSearchbarComponents())
            ->prop('searchAppearance', $this->resolveSearchAppearanceProps())
            ->slot('header', $this->resolveHeaderComponents())
            ->prop('views', $this->getSavedViews())
            ->prop('appearance', $this->appearance ?? 'card')
            ->prop('title', $this->title)
            ->prop('description', $this->description)
            ->prop('headerActions', $this->headerActions)
            ->prop('filters', $resolvedFilters)
            ->prop('bulk', $this->bulkEnabled)
            ->prop('bulkMode', $bulkMode)
            ->prop('numbering', $this->numberingEnabled)
            ->prop('numberingMode', $numberingMode)
            ->prop('sticky', $this->stickySections)
            ->prop('hasActions', $hasActions)
            ->prop('footer', $footer)
            ->prop('bulkActions', array_map(
                static fn (BulkAction $action) => $action->toArray(),
                $this->resolveBulkActions()
            ))

            ->prop('pagination', [
                'total' => $paginator->total(),
                'perPage' => $resolvedRowsPerPage,
                'rowsPerPage' => $resolvedRowsPerPage,
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPageOptions' => $resolvedRowsPerPageOptions,
                'rowsPerPageOptions' => $resolvedRowsPerPageOptions,
                'rowsPerPageLabel' => $this->getRowsPerPageLabel(),
                'rowsPerPageAllLabel' => $this->getRowsPerPageAllLabel(),
                'paginationLabel' => $this->getPaginationLabel(),
                'showButtonLabel' => $this->getShowButtonLabel(),
                'showFirstLabel' => $this->getShowFirstLabel(),
                'showLastLabel' => $this->getShowLastLabel(),
                'ellipsisAfter' => $this->getEllipsisAfter(),
                'firstButtonLabel' => $this->getFirstButtonLabel(),
                'previousButtonLabel' => $this->getPreviousButtonLabel(),
                'nextButtonLabel' => $this->getNextButtonLabel(),
                'lastButtonLabel' => $this->getLastButtonLabel(),
            ]);

        if ($tabs !== null) {
            $table->slot('tabs', $tabs);
        }

        return $table;
    }
}
