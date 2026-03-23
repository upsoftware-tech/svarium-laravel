<?php

namespace Upsoftware\Svarium\Panel\Table;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Upsoftware\Svarium\Enums\TableActionDisplay;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Components\Button;
use Upsoftware\Svarium\UI\Components\Block;
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
use Upsoftware\Svarium\UI\Components\EmptyState;
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
    protected const EXPORT_FORMATS = ['csv', 'tsv', 'xlsx', 'xls', 'ods', 'json', 'xml', 'sql', 'pdf'];

    protected $query;

    protected ?EloquentBuilder $dropdownSearchQueryBase = null;

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
    protected bool $searchableConfigured = false;
    protected bool $searchableAllColumns = false;
    protected array $sortableColumns = [];
    protected bool $sortableConfigured = false;
    protected bool $sortableAllColumns = false;
    protected array $multiSortableColumns = [];
    protected bool $multiSortableConfigured = false;
    protected bool $multiSortableAllColumns = false;
    protected ?string $defaultSortQuery = null;

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
    protected ?bool $columnVisibilityEnabled = null;
    protected ?bool $createHeaderActionEnabled = null;
    protected bool $createHeaderActionSupported = true;

    protected array $headerAppearanceProps = [];

    protected array $searchAppearanceProps = [];

    protected array $bodyAppearanceProps = [];

    protected bool $searchAppearanceDefined = false;

    protected array $tabs = [];
    protected bool $showTabsOnEmpty = false;
    protected bool $showCreateOnEmpty = false;
    protected bool $showAllTab = true;

    protected $searchbar;
    protected bool $searchbarConfigured = false;
    protected ?bool $showInputSearchInSidebar = null;

    protected bool $tabsFromViews = false;

    protected array $columnObjects = [];
    protected array $columnAttributes = [];

    protected array $footerTotalAggregatesCache = [];

    protected ?array $footerTotalRowsCache = null;

    protected array $stickySections = [];

    protected bool $selected = true;
    protected ?bool $selectable = null;
    protected ?bool $customColumns = null;

    protected ?bool $condensed = null;
    protected ?bool $bordered = null;
    protected ?string $dndField = null;

    protected string $filterInputSize = 'default';

    protected ?string $id = null;

    protected bool|array $exported = true;

    protected bool $imported = true;
    protected ?bool $viewsAddable = null;

    protected ?string $exportUrl = null;
    protected array $schemaColumnsCache = [];
    protected ?string $runtimeTableIdentifier = null;
    protected ?string $resolvedTableIdentifier = null;

    public function searchbar($searchbar): static
    {
        $this->searchbarConfigured = true;
        $this->searchbar = $searchbar;

        return $this;
    }

    public function id(?string $id): static
    {
        $normalized = $this->normalizeTableIdentifier((string) $id);

        if ($normalized === '') {
            $this->id = null;
            $this->resolvedTableIdentifier = null;

            return $this;
        }

        $this->id = $normalized;
        $this->resolvedTableIdentifier = null;

        return $this;
    }

    public function withTableIdentifier(?string $identifier): static
    {
        $normalized = $this->normalizeTableIdentifier((string) $identifier);
        $this->runtimeTableIdentifier = $normalized !== '' ? $normalized : null;
        $this->resolvedTableIdentifier = null;

        return $this;
    }

    public function showInputSearchInSidebar(bool $state = true): static
    {
        $this->showInputSearchInSidebar = $state;

        return $this;
    }

    public function pokazCzyMaBycInputSearchWSidebarze(bool $state = true): static
    {
        return $this->showInputSearchInSidebar($state);
    }

    public static function make($query): static
    {
        $instance = new static;
        $instance->query = $query;
        $instance->applyPaginationDefaultsFromConfig();

        return $instance;
    }

    protected function applyPaginationDefaultsFromConfig(): void
    {
        $tableConfig = config('upsoftware.table', []);
        $paginationConfig = config('upsoftware.table.pagination', []);

        if (is_array($paginationConfig)) {
            $this->pagination($paginationConfig);
        } else {
            $paginationConfig = [];
        }

        $hasRowsPerPageOptions = array_key_exists('rowsPerPageOptions', $paginationConfig)
            || array_key_exists('perPageOptions', $paginationConfig);
        $hasRowsPerPage = array_key_exists('rowsPerPage', $paginationConfig)
            || array_key_exists('perPage', $paginationConfig);

        if ((! $hasRowsPerPageOptions || ! $hasRowsPerPage) && is_array($tableConfig) && array_key_exists('per_page', $tableConfig)) {
            $perPage = $tableConfig['per_page'];

            if (is_numeric($perPage)) {
                $perPageValue = max(0, (int) $perPage);

                if (! $hasRowsPerPageOptions) {
                    $this->rowsPerPageOptions([$perPageValue]);
                }

                if (! $hasRowsPerPage) {
                    $this->rowsPerPage($perPageValue);
                }
            }
        }

        if (is_array($tableConfig) && array_key_exists('exported', $tableConfig)) {
            $configuredExported = $tableConfig['exported'];

            if (is_bool($configuredExported)) {
                $this->exported($configuredExported);
            } elseif (is_string($configuredExported) || is_array($configuredExported)) {
                $this->exported($configuredExported);
            }
        }

        if (is_array($tableConfig) && array_key_exists('imported', $tableConfig)) {
            $configuredImported = $tableConfig['imported'];

            if (is_bool($configuredImported)) {
                $this->imported($configuredImported);
            } elseif (is_string($configuredImported)) {
                $parsed = filter_var($configuredImported, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($parsed !== null) {
                    $this->imported($parsed);
                }
            }
        }

        if (is_array($tableConfig) && array_key_exists('views_addable', $tableConfig)) {
            $this->viewsAddable($this->toBoolean($tableConfig['views_addable'], true));
        }

        if (is_array($tableConfig) && array_key_exists('custom_columns', $tableConfig)) {
            $this->customColumns($this->toBoolean($tableConfig['custom_columns'], true));
        }

        if (is_array($tableConfig) && array_key_exists('sortable', $tableConfig)) {
            $configuredSortable = $tableConfig['sortable'];

            if (is_bool($configuredSortable)) {
                $this->sortable($configuredSortable);
            } elseif (is_string($configuredSortable) || is_array($configuredSortable)) {
                $this->sortable($configuredSortable);
            }
        }

        if (is_array($tableConfig) && array_key_exists('multi_sortable', $tableConfig)) {
            $configuredMultiSortable = $tableConfig['multi_sortable'];

            if (is_bool($configuredMultiSortable)) {
                $this->multiSortable($configuredMultiSortable);
            } elseif (is_string($configuredMultiSortable) || is_array($configuredMultiSortable)) {
                $this->multiSortable($configuredMultiSortable);
            }
        }

        if (is_array($tableConfig) && array_key_exists('bordered', $tableConfig)) {
            $this->bordered($this->toBoolean($tableConfig['bordered'], false));
        }

        if (is_array($tableConfig) && array_key_exists('selectable', $tableConfig)) {
            $this->selectable($this->toBoolean($tableConfig['selectable'], true));
        }

        if (is_array($tableConfig) && array_key_exists('dnd', $tableConfig) && is_string($tableConfig['dnd'])) {
            $this->dnd($tableConfig['dnd']);
        }
    }

    public function dnd(?string $field): static
    {
        $normalized = trim((string) $field);
        $this->dndField = $normalized !== '' ? $normalized : null;

        return $this;
    }

    public function disableDnd(): static
    {
        $this->dndField = null;

        return $this;
    }

    public function hasDnd(): bool
    {
        return $this->resolveDndField() !== null;
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

    public function showTabsOnEmpty(bool $enabled = true): static
    {
        $this->showTabsOnEmpty = $enabled;

        return $this;
    }

    public function showCreateOnEmpty(bool $enabled = true): static
    {
        $this->showCreateOnEmpty = $enabled;

        return $this;
    }

    public function emptyTopbar(bool $showTabs = true, bool $showCreate = true): static
    {
        $this->showTabsOnEmpty = $showTabs;
        $this->showCreateOnEmpty = $showCreate;

        return $this;
    }

    public function showAllTab(bool $enabled = true): static
    {
        $this->showAllTab = $enabled;

        return $this;
    }

    public function hideAllTab(bool $enabled = true): static
    {
        $this->showAllTab = ! $enabled;

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
        $this->applyConfiguredColumnAttributes();

        return $this;
    }

    public function columnAttributes(array $attributes): static
    {
        $this->columnAttributes = $attributes;
        $this->applyConfiguredColumnAttributes();

        return $this;
    }

    public function columnsAttributes(array $attributes): static
    {
        return $this->columnAttributes($attributes);
    }

    public function attrs(array $attributes): static
    {
        return $this->columnAttributes($attributes);
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

        if ($this->searchableConfigured && ! $this->searchableAllColumns && ! empty($this->searchable)) {
            $allowedLookup = array_flip($allowedKeys);

            $this->searchable = array_values(array_filter(
                $this->searchable,
                static fn ($column) => isset($allowedLookup[$column]) || str_contains($column, '.')
            ));
        }

        if ($this->sortableConfigured && ! $this->sortableAllColumns && ! empty($this->sortableColumns)) {
            $allowedLookup = array_flip($allowedKeys);

            $this->sortableColumns = array_values(array_filter(
                $this->sortableColumns,
                static fn ($column) => isset($allowedLookup[$column])
            ));
        }

        if ($this->multiSortableConfigured && ! $this->multiSortableAllColumns && ! empty($this->multiSortableColumns)) {
            $allowedLookup = array_flip($allowedKeys);

            $this->multiSortableColumns = array_values(array_filter(
                $this->multiSortableColumns,
                static fn ($column) => isset($allowedLookup[$column])
            ));
        }

        return $this;
    }

    public function searchable(array|string ...$columns): static
    {
        $this->searchableConfigured = true;

        $normalized = $this->normalizeSearchableColumns($columns);

        if ($normalized === []) {
            $this->searchableAllColumns = true;
            $this->searchable = [];

            return $this;
        }

        $this->searchableAllColumns = false;
        $this->searchable = $normalized;

        return $this;
    }

    public function sortable(array|string|bool ...$columns): static
    {
        $this->sortableConfigured = true;

        if ($columns === []) {
            $this->sortableAllColumns = true;
            $this->sortableColumns = [];

            return $this;
        }

        if (count($columns) === 1 && is_bool($columns[0])) {
            $this->sortableAllColumns = $columns[0];
            $this->sortableColumns = [];

            return $this;
        }

        if (count($columns) === 1 && is_string($columns[0])) {
            $normalized = strtolower(trim($columns[0]));

            if (in_array($normalized, ['true', '1', 'yes', 'on'], true)) {
                $this->sortableAllColumns = true;
                $this->sortableColumns = [];

                return $this;
            }

            if (in_array($normalized, ['false', '0', 'no', 'off'], true)) {
                $this->sortableAllColumns = false;
                $this->sortableColumns = [];

                return $this;
            }
        }

        $normalized = $this->normalizeSortableColumns($columns);

        $this->sortableAllColumns = false;
        $this->sortableColumns = $normalized;

        return $this;
    }

    public function multiSortable(array|string|bool ...$columns): static
    {
        $this->multiSortableConfigured = true;

        if ($columns === []) {
            $this->multiSortableAllColumns = true;
            $this->multiSortableColumns = [];

            return $this;
        }

        if (count($columns) === 1 && is_bool($columns[0])) {
            $this->multiSortableAllColumns = $columns[0];
            $this->multiSortableColumns = [];

            return $this;
        }

        if (count($columns) === 1 && is_string($columns[0])) {
            $normalized = strtolower(trim($columns[0]));

            if (in_array($normalized, ['true', '1', 'yes', 'on'], true)) {
                $this->multiSortableAllColumns = true;
                $this->multiSortableColumns = [];

                return $this;
            }

            if (in_array($normalized, ['false', '0', 'no', 'off'], true)) {
                $this->multiSortableAllColumns = false;
                $this->multiSortableColumns = [];

                return $this;
            }
        }

        $this->multiSortableAllColumns = false;
        $this->multiSortableColumns = $this->normalizeSortableColumns($columns);

        return $this;
    }

    public function defaultSort(array|string $columns, ?string $direction = null): static
    {
        $normalizedDirection = strtolower(trim((string) ($direction ?? 'asc')));
        if (! in_array($normalizedDirection, ['asc', 'desc'], true)) {
            $normalizedDirection = 'asc';
        }

        $normalizedColumns = is_array($columns)
            ? $this->normalizeSortableColumns($columns)
            : $this->normalizeSortableColumns([$columns]);

        if ($normalizedColumns === []) {
            $this->defaultSortQuery = null;

            return $this;
        }

        $this->defaultSortQuery = implode(',', array_map(
            static fn (string $column): string => $normalizedDirection === 'desc' ? '-'.$column : $column,
            $normalizedColumns
        ));

        return $this;
    }

    public function actions(array|bool $actions): static
    {
        if (is_bool($actions)) {
            if ($actions === false) {
                $this->actions = [];
                $this->useDefaultActions = false;

                return $this;
            }

            $this->useDefaultActions = true;

            return $this;
        }

        $this->actions = $actions;

        return $this;
    }

    public function viewsAddable(bool $state = true): static
    {
        $this->viewsAddable = $state;

        return $this;
    }

    public function canAddViews(): bool
    {
        return $this->resolveViewsAddable();
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

    public function columnVisibility(bool $state = true): static
    {
        $this->columnVisibilityEnabled = $state;

        return $this;
    }

    public function createAction(bool $state = true): static
    {
        $this->createHeaderActionEnabled = $state;

        return $this;
    }

    public function supportsCreateAction(bool $state = true): static
    {
        $this->createHeaderActionSupported = $state;

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

    public function selected(bool $state = true): static
    {
        $this->selected = $state;

        return $this;
    }

    public function selectable(bool $state = true): static
    {
        $this->selectable = $state;

        return $this;
    }

    public function customColumns(bool $state = true): static
    {
        $this->customColumns = $state;

        return $this;
    }

    public function condensed(bool $state = true): static
    {
        $this->condensed = $state;

        return $this;
    }

    public function bordered(bool $state = true): static
    {
        $this->bordered = $state;

        return $this;
    }

    public function exported(bool|array|string ...$config): static
    {
        if (count($config) === 0) {
            $this->exported = true;

            return $this;
        }

        if (count($config) === 1 && is_bool($config[0])) {
            $this->exported = $config[0];

            return $this;
        }

        if (count($config) === 1 && is_string($config[0])) {
            $normalized = strtolower(trim($config[0]));

            if (in_array($normalized, ['false', '0', 'off', 'no'], true)) {
                $this->exported = false;

                return $this;
            }

            if (in_array($normalized, ['true', '1', 'on', 'yes'], true)) {
                $this->exported = true;

                return $this;
            }
        }

        $formats = $this->normalizeExportFormats($config);

        if ($formats === []) {
            throw new \InvalidArgumentException('Export formats list cannot be empty.');
        }

        $this->exported = $formats;

        return $this;
    }

    public function imported(bool $state = true): static
    {
        $this->imported = $state;

        return $this;
    }

    public function exportUrl(?string $url): static
    {
        $normalized = trim((string) $url);

        if ($normalized === '') {
            $this->exportUrl = null;

            return $this;
        }

        $this->exportUrl = str_starts_with($normalized, '/') ? $normalized : '/'.$normalized;

        return $this;
    }

    public function isExportEnabled(): bool
    {
        if (is_bool($this->exported)) {
            return $this->exported;
        }

        if (! is_array($this->exported)) {
            return false;
        }

        try {
            return count($this->normalizeExportFormats($this->exported)) > 0;
        } catch (\Throwable) {
            return false;
        }
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

    public function applyAutoWithFromColumns(mixed $query): void
    {
        if (! $query instanceof EloquentBuilder) {
            return;
        }

        $relations = $this->resolveAutoWithRelations($query);

        if ($relations === []) {
            return;
        }

        $query->with($relations);
    }

    public function applySearch($query, string $search): void
    {
        $searchableColumns = $this->resolveSearchableColumns();

        if ($searchableColumns === []) {
            return;
        }

        $trimmedSearch = trim($search);
        if ($trimmedSearch === '') {
            return;
        }

        $terms = $this->tokenizeSearchTerms($trimmedSearch);
        if ($terms === []) {
            return;
        }

        $query->where(function ($q) use ($terms, $searchableColumns, $query) {
            $rootGroupQuery = $q;

            if ($q instanceof \Illuminate\Database\Query\Builder && $query instanceof EloquentBuilder) {
                $rootGroupQuery = $query->getModel()
                    ->newEloquentBuilder($q)
                    ->setModel($query->getModel());
            }

            foreach ($terms as $term) {
                $rootGroupQuery->where(function ($termQuery) use ($term, $searchableColumns, $query) {
                    $orGroupQuery = $termQuery;

                    if ($termQuery instanceof \Illuminate\Database\Query\Builder && $query instanceof EloquentBuilder) {
                        $orGroupQuery = $query->getModel()
                            ->newEloquentBuilder($termQuery)
                            ->setModel($query->getModel());
                    }

                    $hasCondition = false;

                    foreach ($searchableColumns as $column) {
                        $applied = $this->applySearchColumnCondition($orGroupQuery, (string) $column, $term, $query);

                        if ($applied) {
                            $hasCondition = true;
                        }
                    }

                    if (! $hasCondition && method_exists($orGroupQuery, 'whereRaw')) {
                        $orGroupQuery->whereRaw('1 = 0');
                    }
                });
            }
        });
    }

    public function applyDropdownSearchFilters($query, ?\Illuminate\Http\Request $request = null): void
    {
        if (! $query instanceof EloquentBuilder) {
            return;
        }

        $request ??= request();
        if (! $request) {
            return;
        }

        if ($this->dropdownSearchQueryBase === null) {
            $this->dropdownSearchQueryBase = clone $query;
        }

        foreach ($this->resolveSearchbarComponents() as $component) {
            if (! $component instanceof DropdownSearch) {
                continue;
            }

            $column = trim((string) ($component->getProp('column') ?? ''));
            if ($column === '') {
                continue;
            }

            $name = trim((string) ($component->getProp('name') ?? $column));
            if ($name === '') {
                continue;
            }

            $values = $this->resolveDropdownRequestValues($request, $name);

            if ($values === []) {
                continue;
            }

            if (count($values) === 1) {
                $query->where($column, '=', $values[0]);
                continue;
            }

            $query->whereIn($column, $values);
        }
    }

    protected function resolveDropdownRequestValues(\Illuminate\Http\Request $request, string $name): array
    {
        $values = [];

        foreach ([$name, "{$name}[]"] as $candidate) {
            $raw = $request->query($candidate);

            if (is_array($raw)) {
                foreach ($raw as $entry) {
                    if (is_scalar($entry)) {
                        $normalized = trim((string) $entry);
                        if ($normalized !== '') {
                            $values[] = $normalized;
                        }
                    }
                }
            } elseif (is_scalar($raw)) {
                $normalized = trim((string) $raw);
                if ($normalized !== '') {
                    $values[] = $normalized;
                }
            }
        }

        $queryBag = $request->query();
        foreach ($queryBag as $key => $raw) {
            if (! is_string($key) || ! preg_match('/^'.preg_quote($name, '/').'\[\d+\]$/', $key)) {
                continue;
            }

            if (is_scalar($raw)) {
                $normalized = trim((string) $raw);
                if ($normalized !== '') {
                    $values[] = $normalized;
                }
            }
        }

        return array_values(array_unique($values));
    }

    protected function tokenizeSearchTerms(string $search): array
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $search) ?? $search);
        if ($normalized === '') {
            return [];
        }

        $tokens = array_values(array_filter(
            preg_split('/\s+/u', $normalized) ?: [],
            static fn (string $token): bool => trim($token) !== ''
        ));

        if ($tokens === []) {
            return [$normalized];
        }

        return array_values(array_unique(array_map(
            static fn (string $token): string => trim($token),
            $tokens
        )));
    }

    protected function applySearchColumnCondition(
        mixed $query,
        string $column,
        string $search,
        mixed $rootQuery
    ): bool {
        $column = trim($column);

        if ($column === '') {
            return false;
        }

        $searchTerm = '%'.$search.'%';

        if (! str_contains($column, '.')) {
            if (method_exists($query, 'orWhere')) {
                $query->orWhere($column, 'like', $searchTerm);
            }

            return true;
        }

        $segments = array_values(array_filter(
            explode('.', $column),
            static fn (string $segment): bool => trim($segment) !== ''
        ));

        if (count($segments) < 2) {
            return false;
        }

        $field = (string) array_pop($segments);
        $relationPath = implode('.', $segments);

        if ($relationPath === '') {
            return false;
        }

        if ($field === '*') {
            if (! $query instanceof EloquentBuilder) {
                return false;
            }

            $relatedColumns = $this->resolveRelatedSearchableColumns($rootQuery, $relationPath);

            if ($relatedColumns === []) {
                return false;
            }

            $query->orWhereHas($relationPath, function (EloquentBuilder $relatedQuery) use ($relatedColumns, $searchTerm) {
                $relatedQuery->where(function ($nested) use ($relatedColumns, $searchTerm) {
                    foreach ($relatedColumns as $relatedColumn) {
                        $nested->orWhere($relatedColumn, 'like', $searchTerm);
                    }
                });
            });

            return true;
        }

        if (! $query instanceof EloquentBuilder) {
            return false;
        }

        $query->orWhereHas($relationPath, function (EloquentBuilder $relatedQuery) use ($field, $searchTerm) {
            $relatedQuery->where($field, 'like', $searchTerm);
        });

        return true;
    }

    public function applySort($query, string $sort): void
    {
        $sort = trim($sort);

        if ($sort === '') {
            return;
        }

        $sortableMap = $this->resolveSortableColumnsMap();
        $multiSortableMap = $this->resolveMultiSortableColumnsMap($sortableMap);
        $directives = $this->parseSortDirectives($sort);
        $directives = $this->sanitizeSortDirectives($directives, $multiSortableMap);

        if ($directives === []) {
            return;
        }

        $appliedSortCount = 0;

        foreach ($directives as $directive) {
            $field = (string) ($directive['field'] ?? '');
            $direction = (string) ($directive['direction'] ?? 'asc');

            if ($field === '') {
                continue;
            }

            $definition = $sortableMap[$field] ?? null;
            if (! is_array($definition) || ! ($definition['enabled'] ?? false)) {
                continue;
            }

            if ($appliedSortCount > 0) {
                $multiDefinition = $multiSortableMap[$field] ?? null;
                if (! is_array($multiDefinition) || ! ($multiDefinition['enabled'] ?? false)) {
                    continue;
                }
            }

            $columns = is_array($definition['columns'] ?? null) ? $definition['columns'] : [];

            foreach ($columns as $column) {
                if (! is_string($column)) {
                    continue;
                }

                $normalized = trim($column);
                if ($normalized === '' || str_contains($normalized, '.')) {
                    continue;
                }

                $query->orderBy($normalized, $direction);
            }

            $appliedSortCount++;
        }
    }

    public function applyDefaultSort($query): void
    {
        $defaultSort = trim((string) ($this->defaultSortQuery ?? ''));
        if ($defaultSort === '') {
            return;
        }

        $sortableMap = $this->resolveSortableColumnsMap();
        $directives = $this->parseSortDirectives($defaultSort);

        if ($directives === []) {
            return;
        }

        $model = $query instanceof EloquentBuilder ? $query->getModel() : null;
        $modelColumns = $model instanceof Model ? $this->resolveModelSearchableColumns($model) : [];
        $modelColumnLookup = array_fill_keys($modelColumns, true);

        foreach ($directives as $directive) {
            $field = trim((string) ($directive['field'] ?? ''));
            $direction = strtolower(trim((string) ($directive['direction'] ?? 'asc'))) === 'desc' ? 'desc' : 'asc';

            if ($field === '') {
                continue;
            }

            $definition = $sortableMap[$field] ?? null;
            if (is_array($definition) && ($definition['enabled'] ?? false)) {
                $columns = is_array($definition['columns'] ?? null) ? $definition['columns'] : [];

                foreach ($columns as $column) {
                    if (! is_string($column)) {
                        continue;
                    }

                    $normalized = trim($column);
                    if ($normalized === '' || str_contains($normalized, '.')) {
                        continue;
                    }

                    $query->orderBy($normalized, $direction);
                }

                continue;
            }

            if (str_contains($field, '.')) {
                continue;
            }

            if (! isset($modelColumnLookup[$field])) {
                continue;
            }

            $query->orderBy($field, $direction);
        }
    }

    /**
     * @param  array<int, array{field:string, direction:string}>  $directives
     * @param  array<string, array{enabled:bool}>  $multiSortableMap
     * @return array<int, array{field:string, direction:string}>
     */
    protected function sanitizeSortDirectives(array $directives, array $multiSortableMap): array
    {
        if ($directives === []) {
            return [];
        }

        $multiSortEnabled = $this->hasEnabledMultiSortableColumns($multiSortableMap);

        if ($multiSortEnabled) {
            return $directives;
        }

        // Multi-sort disabled: honor only the first valid directive from URL.
        return [array_values($directives)[0]];
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
        $settingModel = $this->resolveSettingModelClass();
        if ($settingModel === null || ! method_exists($settingModel, 'getSettingGlobal')) {
            return collect();
        }

        $settingKey = $this->tableViewsSettingKey();
        $publicRaw = $settingModel::getSettingGlobal($settingKey, []);
        $publicViews = $this->normalizeStoredViews($publicRaw, true);

        if (! $this->resolveViewsAddable()) {
            $order = $this->loadStoredViewOrderByScope($settingModel, $this->tableViewsOrderSettingKey(), true);

            if ($order !== []) {
                $publicViews = $this->reorderStoredViewsByKeys($publicViews, $order);
            }

            return collect($publicViews)->map(static fn (array $view) => (object) $view);
        }

        $userViews = [];
        if (auth()->check() && method_exists($settingModel, 'getSettingGlobalForUser')) {
            $userRaw = $settingModel::getSettingGlobalForUser($settingKey, [], null, auth()->id(), false);
            $userViews = $this->normalizeStoredViews($userRaw, false);
        }

        $merged = $this->mergeSavedViewsByKey($publicViews, $userViews);
        $merged = $this->applyStoredViewOrder($settingModel, $merged);

        return collect($merged)->map(static fn (array $view) => (object) $view);
    }

    public function saveView(array $payload): ?array
    {
        $settingModel = $this->resolveSettingModelClass();
        if ($settingModel === null || ! method_exists($settingModel, 'setSettingGlobal')) {
            return null;
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $isPublic = $this->normalizeBooleanValue($payload['is_public'] ?? $payload['public'] ?? false, false);
        $query = $this->normalizeViewQuery($payload['query'] ?? []);

        $settingKey = $this->tableViewsSettingKey();
        $storedViews = $this->loadStoredViewsByScope($settingModel, $settingKey, $isPublic);
        $existing = $this->normalizeStoredViews($storedViews, $isPublic);

        $baseKey = trim((string) ($payload['key'] ?? ''));
        if ($baseKey === '') {
            $baseKey = $name;
        }

        $normalizedBaseKey = $this->normalizeViewKey($baseKey);
        if ($normalizedBaseKey === '') {
            $normalizedBaseKey = 'view';
        }

        $viewKey = $this->ensureUniqueSavedViewKey($existing, $normalizedBaseKey);
        $sort = $this->normalizeSortFromQuery($query);
        $now = now()->toDateTimeString();

        $view = [
            'id' => (string) Str::uuid(),
            'name' => $name,
            'key' => $viewKey,
            'icon' => $this->normalizeNullableString($payload['icon'] ?? null),
            'color' => $this->normalizeNullableString($payload['color'] ?? null),
            'is_public' => $isPublic,
            'query' => $query,
            'filters' => [],
            'sort' => $sort,
            'columns' => [],
            'is_default' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $existing[] = $view;
        $this->storeViewsByScope($settingModel, $settingKey, array_values($existing), $isPublic);

        return $view;
    }

    public function updateView(array $payload): ?array
    {
        $settingModel = $this->resolveSettingModelClass();
        if ($settingModel === null || ! method_exists($settingModel, 'setSettingGlobal')) {
            return null;
        }

        $viewKey = $this->normalizeViewKey((string) ($payload['key'] ?? ''));
        if ($viewKey === '') {
            return null;
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $scopes = [true];
        if (auth()->check()
            && method_exists($settingModel, 'getSettingGlobalForUser')
            && method_exists($settingModel, 'setSettingGlobalForUser')) {
            $scopes[] = false;
        }

        $settingKey = $this->tableViewsSettingKey();
        $viewsByScope = [];
        $sourceScope = null;
        $sourceIndex = null;
        $sourceView = null;

        foreach ($scopes as $scope) {
            $normalizedScope = (bool) $scope;
            $views = $this->normalizeStoredViews(
                $this->loadStoredViewsByScope($settingModel, $settingKey, $normalizedScope),
                $normalizedScope
            );
            $viewsByScope[$normalizedScope] = $views;

            foreach ($views as $index => $view) {
                if ($this->normalizeViewKey((string) ($view['key'] ?? '')) !== $viewKey) {
                    continue;
                }

                $sourceScope = $normalizedScope;
                $sourceIndex = $index;
                $sourceView = $view;
                break 2;
            }
        }

        if ($sourceScope === null || $sourceIndex === null || ! is_array($sourceView)) {
            return null;
        }

        $targetScope = $this->normalizeBooleanValue($payload['is_public'] ?? null, (bool) ($sourceView['is_public'] ?? $sourceScope));
        $hasIconInPayload = array_key_exists('icon', $payload);
        $hasColorInPayload = array_key_exists('color', $payload);

        $queryPayload = $payload['query'] ?? null;
        $query = is_array($queryPayload)
            ? $this->normalizeViewQuery($queryPayload)
            : (is_array($sourceView['query'] ?? null) ? $this->normalizeViewQuery($sourceView['query']) : []);

        $now = now()->toDateTimeString();

        $updatedView = array_merge($sourceView, [
            'name' => $name,
            'icon' => $hasIconInPayload
                ? $this->normalizeNullableString($payload['icon'])
                : $this->normalizeNullableString($sourceView['icon'] ?? null),
            'color' => $hasColorInPayload
                ? $this->normalizeNullableString($payload['color'])
                : $this->normalizeNullableString($sourceView['color'] ?? null),
            'is_public' => $targetScope,
            'query' => $query,
            'sort' => $this->normalizeSortFromQuery($query),
            'updated_at' => $now,
        ]);

        $sourceViews = $viewsByScope[$sourceScope] ?? [];
        unset($sourceViews[$sourceIndex]);
        $viewsByScope[$sourceScope] = array_values($sourceViews);

        $targetViews = $viewsByScope[$targetScope] ?? [];
        $targetViews = array_values(array_filter(
            $targetViews,
            fn (array $view): bool => $this->normalizeViewKey((string) ($view['key'] ?? '')) !== $viewKey
        ));
        $targetViews[] = $updatedView;
        $viewsByScope[$targetScope] = $targetViews;

        foreach ($scopes as $scope) {
            $normalizedScope = (bool) $scope;
            $scopeViews = array_values($viewsByScope[$normalizedScope] ?? []);
            $this->storeViewsByScope($settingModel, $settingKey, $scopeViews, $normalizedScope);

            $orderKey = $this->tableViewsOrderSettingKey();
            $currentOrder = $this->loadStoredViewOrderByScope($settingModel, $orderKey, $normalizedScope);
            if ($currentOrder !== []) {
                $filteredOrder = array_values(array_filter(
                    $currentOrder,
                    fn (string $key): bool => $this->normalizeViewKey($key) !== $viewKey
                ));

                if ($normalizedScope === $targetScope) {
                    $filteredOrder[] = $viewKey;
                }

                $this->storeViewOrderByScope($settingModel, $filteredOrder, $normalizedScope);
            }
        }

        return $updatedView;
    }

    public function reorderViews(array $orderedKeys): bool
    {
        $settingModel = $this->resolveSettingModelClass();
        if ($settingModel === null || ! method_exists($settingModel, 'setSettingGlobal')) {
            return false;
        }

        $normalizedKeys = $this->normalizeViewOrderKeys($orderedKeys);

        if ($normalizedKeys === []) {
            return false;
        }

        $settingKey = $this->tableViewsSettingKey();
        $scopes = [true];

        if (auth()->check()
            && method_exists($settingModel, 'getSettingGlobalForUser')
            && method_exists($settingModel, 'setSettingGlobalForUser')) {
            $scopes[] = false;
        }

        $updated = false;

        foreach ($scopes as $isPublic) {
            $storedViews = $this->loadStoredViewsByScope($settingModel, $settingKey, (bool) $isPublic);
            $existing = $this->normalizeStoredViews($storedViews, (bool) $isPublic);

            if ($existing === []) {
                continue;
            }

            $reordered = $this->reorderStoredViewsByKeys($existing, $normalizedKeys);

            if ($reordered === $existing) {
                continue;
            }

            $this->storeViewsByScope($settingModel, $settingKey, $reordered, (bool) $isPublic);
            $updated = true;
        }

        $orderStored = false;
        foreach ($scopes as $isPublic) {
            $orderStored = $this->storeViewOrderByScope($settingModel, $normalizedKeys, (bool) $isPublic) || $orderStored;
        }

        return $updated || $orderStored;
    }

    public function deleteView(string $viewKey): bool
    {
        $settingModel = $this->resolveSettingModelClass();
        if ($settingModel === null || ! method_exists($settingModel, 'setSettingGlobal')) {
            return false;
        }

        $normalizedViewKey = $this->normalizeViewKey($viewKey);
        if ($normalizedViewKey === '') {
            return false;
        }

        $viewsSettingKey = $this->tableViewsSettingKey();
        $orderSettingKey = $this->tableViewsOrderSettingKey();
        $scopes = [true];

        if (auth()->check()
            && method_exists($settingModel, 'getSettingGlobalForUser')
            && method_exists($settingModel, 'setSettingGlobalForUser')) {
            $scopes[] = false;
        }

        $updated = false;

        foreach ($scopes as $isPublic) {
            $existingViews = $this->normalizeStoredViews(
                $this->loadStoredViewsByScope($settingModel, $viewsSettingKey, (bool) $isPublic),
                (bool) $isPublic
            );

            if ($existingViews !== []) {
                $filteredViews = array_values(array_filter(
                    $existingViews,
                    fn (array $view): bool => $this->normalizeViewKey((string) ($view['key'] ?? '')) !== $normalizedViewKey
                ));

                if (count($filteredViews) !== count($existingViews)) {
                    $this->storeViewsByScope($settingModel, $viewsSettingKey, $filteredViews, (bool) $isPublic);
                    $updated = true;
                }
            }

            $existingOrder = $this->loadStoredViewOrderByScope($settingModel, $orderSettingKey, (bool) $isPublic);
            if ($existingOrder === []) {
                continue;
            }

            $filteredOrder = array_values(array_filter(
                $existingOrder,
                fn (string $key): bool => $this->normalizeViewKey($key) !== $normalizedViewKey
            ));

            if ($filteredOrder === $existingOrder) {
                continue;
            }

            $this->storeViewOrderByScope($settingModel, $filteredOrder, (bool) $isPublic);
            $updated = true;
        }

        return $updated;
    }

    protected function tableViewsSettingKey(): string
    {
        return 'table.views.' . $this->resolveTableIdentifier();
    }

    protected function tableViewsOrderSettingKey(): string
    {
        return 'table.views.order.' . $this->resolveTableIdentifier();
    }

    /**
     * @param  array<int, string>  $orderedKeys
     */
    protected function storeViewOrderByScope(string $settingModel, array $orderedKeys, ?bool $isPublic = null): bool
    {
        $settingKey = $this->tableViewsOrderSettingKey();

        if ($isPublic === true) {
            $settingModel::setSettingGlobal($settingKey, array_values($orderedKeys), true);

            return true;
        }

        if ($isPublic === false && auth()->check() && method_exists($settingModel, 'setSettingGlobalForUser')) {
            $settingModel::setSettingGlobalForUser($settingKey, array_values($orderedKeys), true, auth()->id());

            return true;
        }

        if (auth()->check() && method_exists($settingModel, 'setSettingGlobalForUser')) {
            $settingModel::setSettingGlobalForUser($settingKey, array_values($orderedKeys), true, auth()->id());
        } else {
            $settingModel::setSettingGlobal($settingKey, array_values($orderedKeys), true);
        }

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $views
     * @return array<int, array<string, mixed>>
     */
    protected function applyStoredViewOrder(string $settingModel, array $views): array
    {
        if ($views === []) {
            return [];
        }

        $order = $this->resolveStoredViewOrder($settingModel);
        if ($order === []) {
            return $views;
        }

        return $this->reorderStoredViewsByKeys($views, $order);
    }

    /**
     * @return array<int, string>
     */
    protected function resolveStoredViewOrder(string $settingModel): array
    {
        $settingKey = $this->tableViewsOrderSettingKey();
        $raw = [];

        if (auth()->check() && method_exists($settingModel, 'getSettingGlobalForUser')) {
            $raw = $this->loadStoredViewOrderByScope($settingModel, $settingKey, false);
        }

        if (! is_array($raw) || $raw === []) {
            $raw = $this->loadStoredViewOrderByScope($settingModel, $settingKey, true);
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * @return array<int, string>
     */
    protected function loadStoredViewOrderByScope(string $settingModel, string $settingKey, bool $isPublic): array
    {
        if ($isPublic) {
            $raw = $settingModel::getSettingGlobal($settingKey, []);

            return is_array($raw) ? $this->normalizeViewOrderKeys($raw) : [];
        }

        if (! auth()->check() || ! method_exists($settingModel, 'getSettingGlobalForUser')) {
            return [];
        }

        $raw = $settingModel::getSettingGlobalForUser($settingKey, [], null, auth()->id(), false);

        return is_array($raw) ? $this->normalizeViewOrderKeys($raw) : [];
    }

    /**
     * @param  array<int, mixed>  $orderedKeys
     * @return array<int, string>
     */
    protected function normalizeViewOrderKeys(array $orderedKeys): array
    {
        $normalizedKeys = [];
        foreach ($orderedKeys as $entry) {
            $key = trim((string) $entry);
            if ($key === '' || in_array($key, $normalizedKeys, true)) {
                continue;
            }

            $normalizedKeys[] = $key;
        }

        return $normalizedKeys;
    }

    /**
     * @param  array<int, array<string, mixed>>  $views
     * @param  array<int, string>  $orderedKeys
     * @return array<int, array<string, mixed>>
     */
    protected function reorderStoredViewsByKeys(array $views, array $orderedKeys): array
    {
        $byKey = [];
        foreach ($views as $view) {
            $key = trim((string) ($view['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $byKey[$key] = $view;
        }

        if ($byKey === []) {
            return $views;
        }

        $ordered = [];

        foreach ($orderedKeys as $key) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '' || ! array_key_exists($normalizedKey, $byKey)) {
                continue;
            }

            $ordered[] = $byKey[$normalizedKey];
            unset($byKey[$normalizedKey]);
        }

        foreach ($views as $view) {
            $key = trim((string) ($view['key'] ?? ''));
            if ($key === '' || ! array_key_exists($key, $byKey)) {
                continue;
            }

            $ordered[] = $byKey[$key];
            unset($byKey[$key]);
        }

        return array_values($ordered);
    }

    protected function resolveSettingModelClass(): ?string
    {
        $model = (string) config('upsoftware.models.setting', \Upsoftware\Svarium\Models\Setting::class);
        $model = trim($model);

        if ($model === '' || ! class_exists($model)) {
            return null;
        }

        return $model;
    }

    protected function normalizeStoredViews(mixed $stored, bool $defaultPublic): array
    {
        if (! is_array($stored)) {
            return [];
        }

        $rows = array_is_list($stored) ? $stored : array_values($stored);
        $normalized = [];

        foreach ($rows as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $key = $this->normalizeViewKey($item['key'] ?? $name);
            if ($key === '') {
                continue;
            }

            $query = $this->normalizeViewQuery($item['query'] ?? []);
            $sort = is_array($item['sort'] ?? null) ? $item['sort'] : $this->normalizeSortFromQuery($query);

            $normalized[] = [
                'id' => (string) ($item['id'] ?? ''),
                'name' => $name,
                'key' => $key,
                'icon' => $this->normalizeNullableString($item['icon'] ?? null),
                'color' => $this->normalizeNullableString($item['color'] ?? null),
                'is_public' => $this->normalizeBooleanValue($item['is_public'] ?? null, $defaultPublic),
                'query' => $query,
                'filters' => is_array($item['filters'] ?? null) ? $item['filters'] : [],
                'sort' => $sort,
                'columns' => is_array($item['columns'] ?? null) ? array_values($item['columns']) : [],
                'is_default' => $this->normalizeBooleanValue($item['is_default'] ?? false, false),
                'created_at' => (string) ($item['created_at'] ?? ''),
                'updated_at' => (string) ($item['updated_at'] ?? ''),
            ];
        }

        return $this->deduplicateSavedViews($normalized);
    }

    protected function mergeSavedViewsByKey(array $primary, array $overrides): array
    {
        $merged = [];

        foreach ($primary as $view) {
            $key = trim((string) ($view['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $merged[$key] = $view;
        }

        foreach ($overrides as $view) {
            $key = trim((string) ($view['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $merged[$key] = $view;
        }

        return array_values($merged);
    }

    protected function deduplicateSavedViews(array $views): array
    {
        $result = [];

        foreach ($views as $view) {
            if (! is_array($view)) {
                continue;
            }

            $key = trim((string) ($view['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $result[$key] = $view;
        }

        return array_values($result);
    }

    protected function normalizeViewKey(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        return Str::of($raw)
            ->replaceMatches('/[^A-Za-z0-9\-_:.]+/', '-')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->lower()
            ->value();
    }

    protected function ensureUniqueSavedViewKey(array $existing, string $base): string
    {
        $base = $this->normalizeViewKey($base);
        if ($base === '') {
            $base = 'view';
        }

        $taken = [];
        foreach ($existing as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = $this->normalizeViewKey($item['key'] ?? '');
            if ($key !== '') {
                $taken[$key] = true;
            }
        }

        if (! isset($taken[$base])) {
            return $base;
        }

        $counter = 2;
        while (isset($taken["{$base}-{$counter}"])) {
            $counter++;
        }

        return "{$base}-{$counter}";
    }

    protected function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    protected function normalizeBooleanValue(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) !== 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $default;
    }

    protected function normalizeViewQuery(mixed $query): array
    {
        if (! is_array($query)) {
            return [];
        }

        $excludedKeys = [
            'view',
            'page',
            'perPage',
            'rowsPerPage',
            'per_page',
            'limit',
            'offset',
        ];

        $normalized = [];

        foreach ($query as $rawKey => $rawValue) {
            $key = trim((string) $rawKey);
            if ($key === '' || in_array($key, $excludedKeys, true) || str_starts_with($key, '_')) {
                continue;
            }

            if (is_array($rawValue)) {
                $items = [];

                foreach ($rawValue as $entry) {
                    if (is_string($entry) || is_numeric($entry)) {
                        $value = trim((string) $entry);
                        if ($value !== '') {
                            $items[] = $value;
                        }
                    }
                }

                if ($items !== []) {
                    $normalized[$key] = $items;
                }

                continue;
            }

            if (is_string($rawValue) || is_numeric($rawValue)) {
                $value = trim((string) $rawValue);
                if ($value !== '') {
                    $normalized[$key] = $value;
                }
            }
        }

        return $normalized;
    }

    protected function normalizeSortFromQuery(array $query): ?array
    {
        $raw = trim((string) ($query['sort'] ?? ''));
        if ($raw === '') {
            return null;
        }

        $field = ltrim($raw, '-');
        if ($field === '') {
            return null;
        }

        return [
            'field' => $field,
            'direction' => str_starts_with($raw, '-') ? 'desc' : 'asc',
        ];
    }

    protected function loadStoredViewsByScope(string $settingModel, string $settingKey, bool $isPublic): mixed
    {
        if ($isPublic) {
            return $settingModel::getSettingGlobal($settingKey, []);
        }

        if (! auth()->check() || ! method_exists($settingModel, 'getSettingGlobalForUser')) {
            return [];
        }

        return $settingModel::getSettingGlobalForUser($settingKey, [], null, auth()->id(), false);
    }

    protected function storeViewsByScope(string $settingModel, string $settingKey, array $views, bool $isPublic): void
    {
        if ($isPublic || ! auth()->check() || ! method_exists($settingModel, 'setSettingGlobalForUser')) {
            $settingModel::setSettingGlobal($settingKey, array_values($views), true);
            return;
        }

        $settingModel::setSettingGlobalForUser($settingKey, array_values($views), true, auth()->id());
    }

    protected function resolveSavedView(string $viewKey): ?object
    {
        $normalized = trim($viewKey);
        if ($normalized === '') {
            return null;
        }

        foreach ($this->getSavedViews() as $view) {
            $key = trim((string) ($view->key ?? ''));
            if ($key === '') {
                continue;
            }

            if ($key === $normalized) {
                return $view;
            }
        }

        return null;
    }

    public function applySavedView(EloquentBuilder $query, string $viewKey, bool $applySort = true): ?object
    {
        $view = $this->resolveSavedView($viewKey);
        if ($view === null) {
            return null;
        }

        $filters = is_array($view->filters ?? null) ? $view->filters : [];
        foreach ($filters as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $field = trim((string) ($definition['field'] ?? ''));
            if ($field === '') {
                continue;
            }

            $operator = $this->normalizeFilterOperatorName((string) ($definition['operator'] ?? '='));
            $value = $definition['value'] ?? null;

            $this->applySavedViewFilter($query, $field, $operator, $value);
        }

        if ($applySort && is_array($view->sort ?? null)) {
            $sortField = trim((string) ($view->sort['field'] ?? ''));
            if ($sortField !== '') {
                $direction = strtolower(trim((string) ($view->sort['direction'] ?? 'asc')));
                $this->applySort($query, ($direction === 'desc' ? '-' : '').$sortField);
            }
        }

        $request = request();
        $querySnapshot = $this->normalizeViewQuery((array) ($view->query ?? []));

        if ($querySnapshot !== []) {
            if (! $request?->filled('q') && ! $request?->filled('search')) {
                $search = trim((string) ($querySnapshot['q'] ?? $querySnapshot['search'] ?? ''));
                if ($search !== '') {
                    $this->applySearch($query, $search);
                }
            }

            if ($applySort && ! $request?->filled('sort')) {
                $sort = trim((string) ($querySnapshot['sort'] ?? ''));
                if ($sort !== '') {
                    $this->applySort($query, $sort);
                }
            }
        }

        return $view;
    }

    protected function applySavedViewFilter(EloquentBuilder $query, string $field, string $operator, mixed $value): void
    {
        switch ($operator) {
            case 'contains':
                $query->where($field, 'like', '%'.(string) $value.'%');
                return;
            case 'starts_with':
                $query->where($field, 'like', (string) $value.'%');
                return;
            case 'ends_with':
                $query->where($field, 'like', '%'.(string) $value);
                return;
            case 'in':
                if (is_array($value) && $value !== []) {
                    $query->whereIn($field, $value);
                }
                return;
            case 'not_in':
                if (is_array($value) && $value !== []) {
                    $query->whereNotIn($field, $value);
                }
                return;
            case 'between':
                if (is_array($value) && count($value) >= 2) {
                    $query->whereBetween($field, [array_values($value)[0], array_values($value)[1]]);
                }
                return;
            case 'is_null':
                $query->whereNull($field);
                return;
            case 'is_not_null':
                $query->whereNotNull($field);
                return;
            case 'is_empty':
                $query->where(function (EloquentBuilder $nested) use ($field) {
                    $nested->whereNull($field)->orWhere($field, '=', '');
                });
                return;
            case 'is_not_empty':
                $query->where(function (EloquentBuilder $nested) use ($field) {
                    $nested->whereNotNull($field)->where($field, '!=', '');
                });
                return;
            case 'after':
                $query->where($field, '>', $value);
                return;
            case 'before':
                $query->where($field, '<', $value);
                return;
            default:
                $query->where($field, $operator, $value);
                return;
        }
    }

    protected function buildTabs(): ?Tab
    {
        $items = $this->tabs ?? [];

        if ($this->tabsFromViews) {
            foreach ($this->getSavedViews() as $view) {

                $tabItem = TabItem::make($view->name)
                    ->prop('value', $view->key);

                $icon = trim((string) ($view->icon ?? ''));
                if ($icon !== '') {
                    $tabItem->icon($icon);
                }

                $color = trim((string) ($view->color ?? ''));
                if ($color !== '') {
                    $tabItem->color($color);
                }

                $items[] = $tabItem;
            }
        }

        if (empty($items)) {
            return null;
        }

        $allTabValue = '__all__';
        $items = array_values(array_filter($items, static function ($item) use ($allTabValue): bool {
            if (! $item instanceof TabItem) {
                return true;
            }

            $value = trim((string) ($item->getProp('value') ?? ''));

            return $value !== $allTabValue;
        }));

        if ($this->showAllTab) {
            array_unshift($items, TabItem::make(__('All'))->prop('value', $allTabValue));
        }

        $allowedValues = [];
        foreach ($items as $item) {
            if (! $item instanceof TabItem) {
                continue;
            }

            $value = trim((string) ($item->getProp('value') ?? ''));
            if ($value !== '') {
                $allowedValues[] = $value;
            }
        }

        $requested = trim((string) (request()?->query('view') ?? ''));
        $default = $requested !== '' && in_array($requested, $allowedValues, true)
            ? $requested
            : ($items[0]->getProp('value') ?? null);

        return Tab::make('table_tabs')
            ->prop('defaultValue', $default)
            ->hideContent()
            ->items($items);
    }

    protected function resolveSearchbarConfiguredComponents(): array
    {
        $components = [];

        if ($this->searchbar === true) {
            $components[] = InputSearch::make('q');
        } elseif ($this->searchbar instanceof Component) {
            $components[] = $this->searchbar;
        } elseif (is_array($this->searchbar)) {
            $components = $this->searchbar;
        }

        if ($this->shouldAutoAddSearchbarInput() && ! $this->hasSearchbarInputNamed($components, 'q')) {
            array_unshift($components, InputSearch::make('q')->placeholder(__('Search...')));
        }

        if ($this->shouldShowSidebarInputSearch() && ! $this->hasSearchbarInputNamed($components, 'q')) {
            array_unshift($components, InputSearch::make('q')->placeholder(__('Search...')));
        }

        return $components;
    }

    protected function resolveSearchbarComponents(): array
    {
        $components = $this->resolveSearchbarConfiguredComponents();

        if ($components === []) {
            return [];
        }

        foreach ($components as $component) {
            if ($component instanceof DropdownSearch) {
                $component->resolveFromQuery($this->resolveDropdownSearchComponentQuery($component));
            }
        }

        if ($components) {
            $components[] = $this->resolveFilterComponents($this->resolveFilters());
        }

        return $components;
    }

    protected function resolveDropdownSearchComponentQuery(DropdownSearch $target): EloquentBuilder
    {
        $base = $this->dropdownSearchQueryBase instanceof EloquentBuilder
            ? clone $this->dropdownSearchQueryBase
            : clone $this->query;

        $request = request();
        if (! $request) {
            return $base;
        }

        foreach ($this->resolveSearchbarConfiguredComponents() as $component) {
            if (! $component instanceof DropdownSearch || $component === $target) {
                continue;
            }

            $column = trim((string) ($component->getProp('column') ?? ''));
            $name = trim((string) ($component->getProp('name') ?? $column));

            if ($column === '' || $name === '') {
                continue;
            }

            $values = $this->resolveDropdownRequestValues($request, $name);

            if ($values === []) {
                continue;
            }

            if (count($values) === 1) {
                $base->where($column, '=', $values[0]);
            } else {
                $base->whereIn($column, $values);
            }
        }

        return $base;
    }

    protected function shouldAutoAddSearchbarInput(): bool
    {
        if (! $this->searchableConfigured) {
            return false;
        }

        if ($this->searchbarConfigured && $this->searchbar === false) {
            return false;
        }

        return $this->resolveSearchableColumns() !== [];
    }

    protected function shouldShowSidebarInputSearch(): bool
    {
        if ($this->showInputSearchInSidebar !== null) {
            return $this->showInputSearchInSidebar;
        }

        $configured = config('upsoftware.table.searchbar', false);

        return $this->toBoolean($configured, false);
    }

    protected function hasSearchbarInputNamed(array $components, string $name): bool
    {
        $target = strtolower(trim($name));

        if ($target === '') {
            return false;
        }

        foreach ($components as $component) {
            if (! $component instanceof InputSearch) {
                continue;
            }

            $inputName = trim((string) ($component->getProp('name', '')));

            if ($inputName === '') {
                continue;
            }

            if (strtolower($inputName) === $target) {
                return true;
            }
        }

        return false;
    }

    protected function resolveSearchAppearanceProps(): array
    {
        return $this->searchAppearanceProps;
    }

    protected function resolveHeaderComponents(): array
    {
        $components = $this->collectResolvedHeaderComponents();

        if (! $this->resolveSearchbarComponents()) {
            $components[] = $this->resolveFilterComponents($this->resolveFilters());
        }

        return $components;
    }

    protected function collectResolvedHeaderComponents(): array
    {
        $components = [];
        $serializedColumns = $this->serializeColumns();

        foreach ($this->headerComponents as $component) {
            if (! is_object($component) || ! method_exists($component, 'toArray')) {
                continue;
            }

            if (! $this->shouldRenderHeaderComponent($component)) {
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

        if ($this->shouldAutoAddColumnVisibility($components)) {
            $components[] = ColumnVisibility::make()
                ->variant('outline')
                ->size('sm')
                ->columns($serializedColumns);
        }

        if ($this->shouldAutoAddCreateHeaderAction($components)) {
            $components[] = Action::create()
                ->variant('outline')
                ->size('sm')
                ->baseUri($this->baseUri ?? '');
        }

        return $components;
    }

    protected function resolveEmptyCreateAction(): ?array
    {
        foreach ($this->collectResolvedHeaderComponents() as $component) {
            if (! $component instanceof Action) {
                continue;
            }

            if (strtolower(trim($component->getType())) !== 'create') {
                continue;
            }

            $action = clone $component;
            $action->baseUri($this->baseUri ?? '');
            $resolved = $action->toArray();

            return is_array($resolved) ? $resolved : null;
        }

        return null;
    }

    protected function shouldRenderHeaderComponent(object $component): bool
    {
        if ($component instanceof ColumnVisibility) {
            return $this->columnVisibilityEnabled !== false;
        }

        if ($component instanceof Action && strtolower(trim($component->getType())) === 'create') {
            if (! $this->createHeaderActionSupported) {
                return false;
            }

            return $this->createHeaderActionEnabled !== false;
        }

        return true;
    }

    protected function shouldAutoAddColumnVisibility(array $components): bool
    {
        if (! $this->resolveColumnVisibilityEnabled()) {
            return false;
        }

        foreach ($components as $component) {
            if ($component instanceof ColumnVisibility) {
                return false;
            }
        }

        return true;
    }

    protected function shouldAutoAddCreateHeaderAction(array $components): bool
    {
        if (! $this->createHeaderActionSupported || ! $this->resolveCreateHeaderActionEnabled()) {
            return false;
        }

        foreach ($components as $component) {
            if ($component instanceof Action && strtolower(trim($component->getType())) === 'create') {
                return false;
            }
        }

        return true;
    }

    protected function resolveColumnVisibilityEnabled(): bool
    {
        if ($this->columnVisibilityEnabled !== null) {
            return $this->columnVisibilityEnabled;
        }

        return $this->toBoolean(config('upsoftware.table.column_visibility', false), false);
    }

    protected function resolveCreateHeaderActionEnabled(): bool
    {
        if ($this->createHeaderActionEnabled !== null) {
            return $this->createHeaderActionEnabled;
        }

        return $this->toBoolean(config('upsoftware.table.create_action', false), false);
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
        if ($this->searchableConfigured) {
            if ($this->searchableAllColumns) {
                return $this->collectSearchableColumnsFromDefinitions();
            }

            return $this->normalizeSearchableColumns($this->searchable);
        }

        return $this->collectSearchableColumnsFromDefinitions(true);
    }

    protected function resolveSortableColumnsMap(): array
    {
        $map = [];
        $defaults = $this->resolveSortableDefaults();
        $defaultLookup = array_flip($defaults['columns']);

        foreach ($this->columnObjects as $column) {
            if (! $column instanceof Column) {
                if (! is_array($column)) {
                    continue;
                }

                $key = '';
                if (isset($column['key']) && is_string($column['key'])) {
                    $key = trim($column['key']);
                } elseif (isset($column['field']) && is_string($column['field'])) {
                    $key = trim($column['field']);
                }

                if ($key === '') {
                    continue;
                }

                $sortableDefinition = $column['sortable'] ?? null;
                $sortColumns = $this->resolveSortableColumnsFromArrayDefinition($sortableDefinition, $key);

                $enabled = false;
                if (is_bool($sortableDefinition)) {
                    $enabled = $sortableDefinition;
                } elseif (is_string($sortableDefinition)) {
                    $parsed = filter_var($sortableDefinition, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($parsed !== null) {
                        $enabled = $parsed;
                    }
                } elseif (is_array($sortableDefinition)) {
                    $enabled = $sortColumns !== [];
                } elseif ($defaults['all']) {
                    $enabled = true;
                } elseif (isset($defaultLookup[$key])) {
                    $enabled = true;
                }

                if (! $enabled) {
                    $map[$key] = [
                        'enabled' => false,
                        'columns' => [],
                    ];
                    continue;
                }

                if ($sortColumns === []) {
                    $sortColumns = [$key];
                }

                $map[$key] = [
                    'enabled' => true,
                    'columns' => $sortColumns,
                ];

                continue;
            }

            $key = $column->getKey();
            if ($key === '') {
                continue;
            }

            $enabled = false;
            if ($column->isSortableConfigured()) {
                $enabled = $column->isSortable();
            } elseif ($defaults['all']) {
                $enabled = true;
            } elseif (isset($defaultLookup[$key])) {
                $enabled = true;
            }

            if (! $enabled) {
                $map[$key] = [
                    'enabled' => false,
                    'columns' => [],
                ];
                continue;
            }

            $sortColumns = $column->isSortableConfigured()
                ? $column->getSortColumns()
                : ($column->getConcatKeys() !== [] ? $column->getConcatKeys() : [$key]);

            $sortColumns = $this->normalizeSortColumns($sortColumns);

            if ($sortColumns === []) {
                $sortColumns = [$key];
            }

            $map[$key] = [
                'enabled' => true,
                'columns' => $sortColumns,
            ];
        }

        return $map;
    }

    protected function resolveMultiSortableColumnsMap(array $sortableMap): array
    {
        $map = [];
        $defaults = $this->resolveMultiSortableDefaults();
        $defaultLookup = array_flip($defaults['columns']);

        foreach ($sortableMap as $key => $sortableDefinition) {
            $sortableEnabled = is_array($sortableDefinition) && (bool) ($sortableDefinition['enabled'] ?? false);

            if (! $sortableEnabled) {
                $map[$key] = ['enabled' => false];
                continue;
            }

            $columnDefinition = $this->findColumnDefinitionByKey($key);
            $enabled = false;

            if ($columnDefinition instanceof Column) {
                if ($columnDefinition->isMultiSortableConfigured()) {
                    $enabled = $columnDefinition->isMultiSortable();
                } elseif ($defaults['all']) {
                    $enabled = true;
                } elseif (isset($defaultLookup[$key])) {
                    $enabled = true;
                }
            } elseif (is_array($columnDefinition)) {
                $raw = $columnDefinition['multiSortable'] ?? $columnDefinition['multi_sortable'] ?? null;

                if (is_bool($raw)) {
                    $enabled = $raw;
                } elseif (is_string($raw)) {
                    $parsed = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($parsed !== null) {
                        $enabled = $parsed;
                    }
                } elseif ($defaults['all']) {
                    $enabled = true;
                } elseif (isset($defaultLookup[$key])) {
                    $enabled = true;
                }
            } else {
                if ($defaults['all']) {
                    $enabled = true;
                } elseif (isset($defaultLookup[$key])) {
                    $enabled = true;
                }
            }

            $map[$key] = ['enabled' => $enabled];
        }

        return $map;
    }

    protected function hasEnabledMultiSortableColumns(array $multiSortableMap): bool
    {
        foreach ($multiSortableMap as $definition) {
            if (is_array($definition) && (bool) ($definition['enabled'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    protected function resolveMultiSortableDefaults(): array
    {
        if ($this->multiSortableConfigured) {
            if ($this->multiSortableAllColumns) {
                return [
                    'all' => true,
                    'columns' => [],
                ];
            }

            return [
                'all' => false,
                'columns' => $this->normalizeSortableColumns($this->multiSortableColumns),
            ];
        }

        $configured = config('upsoftware.table.multi_sortable', false);

        if (is_bool($configured)) {
            return [
                'all' => $configured,
                'columns' => [],
            ];
        }

        if (is_string($configured)) {
            $parsed = filter_var($configured, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($parsed !== null) {
                return [
                    'all' => $parsed,
                    'columns' => [],
                ];
            }
        }

        if (is_array($configured)) {
            return [
                'all' => false,
                'columns' => $this->normalizeSortableColumns($configured),
            ];
        }

        return [
            'all' => false,
            'columns' => [],
        ];
    }

    protected function findColumnDefinitionByKey(string $key): mixed
    {
        foreach ($this->columnObjects as $column) {
            if ($column instanceof Column) {
                if ($column->getKey() === $key) {
                    return $column;
                }

                continue;
            }

            if (! is_array($column)) {
                continue;
            }

            $columnKey = '';
            if (isset($column['key']) && is_string($column['key'])) {
                $columnKey = trim($column['key']);
            } elseif (isset($column['field']) && is_string($column['field'])) {
                $columnKey = trim($column['field']);
            }

            if ($columnKey === $key) {
                return $column;
            }
        }

        return null;
    }

    protected function parseSortDirectives(string $sort): array
    {
        $tokens = array_values(array_filter(
            array_map(static fn (string $token): string => trim($token), explode(',', $sort)),
            static fn (string $token): bool => $token !== ''
        ));

        if ($tokens === []) {
            return [];
        }

        $directives = [];
        $seen = [];

        foreach ($tokens as $token) {
            $direction = 'asc';
            $field = $token;

            if (str_starts_with($field, '-')) {
                $direction = 'desc';
                $field = substr($field, 1);
            }

            $field = trim($field);
            if ($field === '' || isset($seen[$field])) {
                continue;
            }

            $seen[$field] = true;
            $directives[] = [
                'field' => $field,
                'direction' => $direction,
            ];
        }

        return $directives;
    }

    protected function resolveActiveSortDirectivesForRendering(): array
    {
        $sort = trim((string) request()->query('sort', ''));

        if ($sort === '') {
            return [];
        }

        $sortableMap = $this->resolveSortableColumnsMap();
        $multiSortableMap = $this->resolveMultiSortableColumnsMap($sortableMap);
        $directives = $this->parseSortDirectives($sort);

        return $this->sanitizeSortDirectives($directives, $multiSortableMap);
    }

    protected function resolveDndField(): ?string
    {
        $field = trim((string) ($this->dndField ?? ''));

        return $field !== '' ? $field : null;
    }

    protected function isDndActive(): bool
    {
        return $this->resolveActiveDndDirection() !== null;
    }

    protected function resolveActiveDndDirection(): ?string
    {
        $field = $this->resolveDndField();
        if ($field === null) {
            return null;
        }

        $directives = $this->resolveActiveSortDirectivesForRendering();
        if ($directives === []) {
            return null;
        }

        $firstField = trim((string) ($directives[0]['field'] ?? ''));
        $firstDirection = strtolower(trim((string) ($directives[0]['direction'] ?? 'asc')));

        if ($firstField === '' || $firstField !== $field) {
            return null;
        }

        $sortableMap = $this->resolveSortableColumnsMap();
        $sortableDefinition = $sortableMap[$field] ?? null;
        if (! is_array($sortableDefinition) || ! ((bool) ($sortableDefinition['enabled'] ?? false))) {
            return null;
        }

        return in_array($firstDirection, ['asc', 'desc'], true) ? $firstDirection : 'asc';
    }

    protected function resolveDndConfig(): array
    {
        $direction = $this->resolveActiveDndDirection();

        return [
            'enabled' => $direction !== null,
            'field' => $this->resolveDndField(),
            'direction' => $direction ?? 'asc',
        ];
    }

    protected function normalizeDndOrderedIds(array|string $orderedIds): array
    {
        $raw = $orderedIds;

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = array_map('trim', explode(',', $raw));
            }
        }

        if (! is_array($raw)) {
            return [];
        }

        $normalized = [];

        foreach ($raw as $id) {
            $value = trim((string) $id);
            if ($value === '' || in_array($value, $normalized, true)) {
                continue;
            }

            $normalized[] = $value;
        }

        return $normalized;
    }

    public function reorderByDnd(array|string $orderedIds): bool
    {
        $field = $this->resolveDndField();
        $direction = $this->resolveActiveDndDirection();
        if ($field === null || $direction === null) {
            return false;
        }

        $query = $this->query;
        if (! $query instanceof EloquentBuilder) {
            return false;
        }

        $model = $query->getModel();
        if (! $model instanceof Model) {
            return false;
        }

        $ordered = $this->normalizeDndOrderedIds($orderedIds);
        if ($ordered === []) {
            return false;
        }

        $keyName = $model->getKeyName();
        $scopeQuery = $this->newDndScopeQuery();
        if (! $scopeQuery instanceof EloquentBuilder) {
            return false;
        }

        $scopeForCurrentPage = clone $scopeQuery;
        $sort = trim((string) request()->query('sort', ''));
        if ($sort !== '') {
            $this->applySort($scopeForCurrentPage, $sort);
        } else {
            $this->applyDefaultSort($scopeForCurrentPage);
        }

        $scopeForCurrentPageIds = clone $scopeForCurrentPage;
        $page = max(1, (int) request()->query('page', 1));
        $rowsPerPage = $this->resolveRowsPerPage(request()->query('rowsPerPage', request()->query('perPage')));
        if ($rowsPerPage > 0) {
            $scopeForCurrentPageIds->forPage($page, $rowsPerPage);
        }

        $currentPageIds = $scopeForCurrentPageIds->get()->map(function ($record): string {
            if ($record instanceof Model) {
                return trim((string) $record->getKey());
            }

            return trim((string) data_get($record, 'id'));
        })->filter(static fn (string $value): bool => $value !== '')->values()->all();

        if ($currentPageIds === []) {
            return false;
        }

        $pageLookup = array_fill_keys($currentPageIds, true);
        $existingIds = array_values(array_filter(
            $ordered,
            static fn (string $value): bool => isset($pageLookup[$value])
        ));

        if ($existingIds === []) {
            return false;
        }

        $totalRows = max(1, (int) (clone $scopeQuery)->toBase()->getCountForPagination());
        $startPosition = 1;

        if ($direction === 'desc') {
            $startPosition = $rowsPerPage > 0
                ? max(1, $totalRows - (($page - 1) * $rowsPerPage))
                : $totalRows;
        } else {
            $startPosition = $rowsPerPage > 0
                ? (($page - 1) * $rowsPerPage) + 1
                : 1;
        }

        DB::transaction(function () use ($model, $keyName, $field, $existingIds, $startPosition, $direction): void {
            foreach ($existingIds as $index => $id) {
                $position = $direction === 'desc'
                    ? ($startPosition - $index)
                    : ($startPosition + $index);

                $model->newQuery()
                    ->where($keyName, $id)
                    ->update([$field => max(1, (int) $position)]);
            }
        });

        return true;
    }

    protected function newDndScopeQuery(): ?EloquentBuilder
    {
        $query = $this->query;
        if (! $query instanceof EloquentBuilder) {
            return null;
        }

        $scope = clone $query;
        $request = request();

        $requestedView = trim((string) $request->query('view', ''));
        if ($requestedView !== '') {
            $this->applySavedView($scope, $requestedView, ! $request->filled('sort'));
        }

        $search = $request->query('q');
        if (! is_string($search) || trim($search) === '') {
            $search = $request->query('search');
        }

        if (is_string($search) && trim($search) !== '') {
            $this->applySearch($scope, $search);
        }

        $this->applyDropdownSearchFilters($scope, $request);

        return $scope;
    }

    protected function resolveSortableDefaults(): array
    {
        if ($this->sortableConfigured) {
            if ($this->sortableAllColumns) {
                return [
                    'all' => true,
                    'columns' => [],
                ];
            }

            return [
                'all' => false,
                'columns' => $this->normalizeSortableColumns($this->sortableColumns),
            ];
        }

        $configured = config('upsoftware.table.sortable', false);

        if (is_bool($configured)) {
            return [
                'all' => $configured,
                'columns' => [],
            ];
        }

        if (is_string($configured)) {
            $parsed = filter_var($configured, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($parsed !== null) {
                return [
                    'all' => $parsed,
                    'columns' => [],
                ];
            }
        }

        if (is_array($configured)) {
            return [
                'all' => false,
                'columns' => $this->normalizeSortableColumns($configured),
            ];
        }

        return [
            'all' => false,
            'columns' => [],
        ];
    }

    protected function normalizeSortableColumns(array $definitions): array
    {
        $columns = [];

        foreach ($definitions as $definition) {
            if (is_array($definition)) {
                foreach ($definition as $nested) {
                    if (! is_string($nested)) {
                        continue;
                    }

                    $parts = array_map('trim', explode(',', $nested));
                    foreach ($parts as $part) {
                        if ($part !== '') {
                            $columns[] = $part;
                        }
                    }
                }

                continue;
            }

            if (! is_string($definition)) {
                continue;
            }

            $parts = array_map('trim', explode(',', $definition));
            foreach ($parts as $part) {
                if ($part !== '') {
                    $columns[] = $part;
                }
            }
        }

        return array_values(array_filter(
            array_unique($columns),
            static fn (string $column): bool => $column !== ''
        ));
    }

    protected function normalizeSortColumns(array $columns): array
    {
        return array_values(array_filter(
            array_map(static fn ($column): string => is_string($column) ? trim($column) : '', $columns),
            static fn (string $column): bool => $column !== ''
        ));
    }

    protected function resolveSortableColumnsFromArrayDefinition(mixed $definition, string $key): array
    {
        if (! is_array($definition)) {
            return [];
        }

        $normalized = $this->normalizeSortableColumns($definition);

        if ($normalized !== []) {
            return $normalized;
        }

        return [$key];
    }

    protected function normalizeSearchableColumns(array $definitions): array
    {
        $columns = [];

        foreach ($definitions as $definition) {
            if (is_array($definition)) {
                foreach ($definition as $nested) {
                    if (! is_string($nested)) {
                        continue;
                    }

                    $value = trim($nested);
                    if ($value !== '') {
                        $columns[] = $value;
                    }
                }

                continue;
            }

            if (! is_string($definition)) {
                continue;
            }

            $value = trim($definition);
            if ($value !== '') {
                $columns[] = $value;
            }
        }

        return array_values(array_filter(
            array_unique($columns),
            static fn (string $column): bool => $column !== ''
        ));
    }

    protected function collectSearchableColumnsFromDefinitions(bool $requireSearchableFlag = false): array
    {
        $columns = [];

        foreach ($this->columnObjects as $column) {
            if ($column instanceof Column) {
                if ($requireSearchableFlag && ! $column->isSearchable()) {
                    continue;
                }

                if (! $column->isVisible() || ! $column->isSelected()) {
                    continue;
                }

                $paths = $column->getConcatKeys();
                if ($paths === []) {
                    $paths = [$column->getKey()];
                }

                foreach ($paths as $path) {
                    $normalized = trim((string) $path);
                    if ($normalized !== '') {
                        $columns[] = $normalized;
                    }
                }

                continue;
            }

            if (! is_array($column)) {
                continue;
            }

            if ($requireSearchableFlag && ! $this->toBoolean($column['searchable'] ?? false, false)) {
                continue;
            }

            if (! $this->toBoolean($column['visible'] ?? true, true) || ! $this->toBoolean($column['selected'] ?? true, true)) {
                continue;
            }

            $key = '';
            if (isset($column['key']) && is_string($column['key'])) {
                $key = trim($column['key']);
            } elseif (isset($column['field']) && is_string($column['field'])) {
                $key = trim($column['field']);
            }

            if ($key !== '') {
                $columns[] = $key;
            }
        }

        return array_values(array_filter(
            array_unique($columns),
            static fn (string $column): bool => $column !== ''
        ));
    }

    protected function resolveRelatedSearchableColumns(mixed $rootQuery, string $relationPath): array
    {
        if (! $rootQuery instanceof EloquentBuilder) {
            return [];
        }

        $rootModel = $rootQuery->getModel();
        if (! $rootModel instanceof Model) {
            return [];
        }

        $relatedModel = $this->resolveRelatedModelFromRelationPath($rootModel, $relationPath);
        if (! $relatedModel instanceof Model) {
            return [];
        }

        return $this->resolveModelSearchableColumns($relatedModel);
    }

    protected function resolveRelatedModelFromRelationPath(Model $rootModel, string $relationPath): ?Model
    {
        $segments = array_values(array_filter(
            explode('.', $relationPath),
            static fn (string $segment): bool => trim($segment) !== ''
        ));

        if ($segments === []) {
            return null;
        }

        $currentModel = $rootModel;

        foreach ($segments as $segment) {
            if (! method_exists($currentModel, $segment)) {
                return null;
            }

            try {
                $relation = $currentModel->{$segment}();
            } catch (\Throwable) {
                return null;
            }

            if (! $relation instanceof Relation) {
                return null;
            }

            $currentModel = $relation->getRelated();
        }

        return $currentModel;
    }

    protected function resolveModelSearchableColumns(Model $model): array
    {
        $connection = (string) $model->getConnectionName();
        $table = (string) $model->getTable();
        $cacheKey = strtolower($connection.'|'.$table);

        if (array_key_exists($cacheKey, $this->schemaColumnsCache)) {
            return $this->schemaColumnsCache[$cacheKey];
        }

        try {
            $columns = Schema::connection($connection !== '' ? $connection : null)->getColumnListing($table);
        } catch (\Throwable) {
            $columns = [];
        }

        $normalized = array_values(array_filter(
            array_map(static fn ($column): string => trim((string) $column), $columns),
            static fn (string $column): bool => $column !== ''
        ));

        return $this->schemaColumnsCache[$cacheKey] = $normalized;
    }

    protected function resolveAutoWithRelations(EloquentBuilder $query): array
    {
        $model = $query->getModel();

        if (! $model instanceof Model) {
            return [];
        }

        $relations = [];

        foreach ($this->columnObjects as $column) {
            if (! $column instanceof Column) {
                continue;
            }

            $paths = array_values(array_unique(array_filter([
                trim((string) $column->getKey()),
                ...array_map(
                    static fn (mixed $path): string => trim((string) $path),
                    $column->getConcatKeys()
                ),
            ], static fn (string $path): bool => $path !== '')));

            foreach ($paths as $path) {
                if (! str_contains($path, '.')) {
                    continue;
                }

                $resolved = $this->resolveRelationPathFromColumnKey($model, $path);

                if ($resolved === null) {
                    continue;
                }

                $relations[] = $resolved;
            }
        }

        return array_values(array_unique($relations));
    }

    protected function resolveRelationPathFromColumnKey(Model $rootModel, string $key): ?string
    {
        $segments = array_values(array_filter(explode('.', $key), static fn ($segment) => trim((string) $segment) !== ''));

        if (count($segments) < 2) {
            return null;
        }

        // Last segment is treated as field name. All previous segments must be valid Eloquent relations.
        $relationSegments = array_slice($segments, 0, -1);
        $currentModel = $rootModel;
        $resolvedPath = [];

        foreach ($relationSegments as $segment) {
            if (! method_exists($currentModel, $segment)) {
                return null;
            }

            try {
                $relation = $currentModel->{$segment}();
            } catch (\Throwable) {
                return null;
            }

            if (! $relation instanceof Relation) {
                return null;
            }

            $resolvedPath[] = $segment;
            $currentModel = $relation->getRelated();
        }

        if ($resolvedPath === []) {
            return null;
        }

        return implode('.', $resolvedPath);
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
        $sortableMap = $this->resolveSortableColumnsMap();
        $multiSortableMap = $this->resolveMultiSortableColumnsMap($sortableMap);
        $languageExpansionLocales = $this->resolveLanguageColumnExpansionLocales();
        $languageLabels = $this->resolveTableLanguageLabelsByLocale();
        $languageIcons = $this->resolveTableLanguageIconsByLocale();
        $serializedColumns = [];

        foreach ($this->columnObjects as $column) {
            if ($column instanceof Column) {
                $baseSerialized = $column->toArray();
                $baseKey = trim((string) ($baseSerialized['key'] ?? ''));
                $sortable = $baseKey !== '' ? ($sortableMap[$baseKey] ?? null) : null;

                if (is_array($sortable)) {
                    $baseSerialized['sortable'] = (bool) ($sortable['enabled'] ?? false);
                    $baseSerialized['sortColumns'] = is_array($sortable['columns'] ?? null) ? $sortable['columns'] : [];
                }

                $multiSortable = $baseKey !== '' ? ($multiSortableMap[$baseKey] ?? null) : null;
                if (is_array($multiSortable)) {
                    $baseSerialized['multiSortable'] = (bool) ($multiSortable['enabled'] ?? false);
                }

                if ($column->isLanguage() && count($languageExpansionLocales) > 1) {
                    foreach ($languageExpansionLocales as $locale) {
                        $localeCode = strtolower(trim((string) $locale));
                        if ($localeCode === '') {
                            continue;
                        }

                        $expanded = $baseSerialized;
                        $expanded['key'] = ($baseKey !== '' ? $baseKey : 'column').'__'.$localeCode;
                        $expanded['label'] = $this->buildExpandedLanguageColumnLabel(
                            (string) ($baseSerialized['label'] ?? $baseKey),
                            $localeCode,
                            $languageLabels,
                            $languageIcons
                        );
                        $expanded['icon'] = $languageIcons[$localeCode] ?? null;
                        $expanded['sortable'] = false;
                        $expanded['multiSortable'] = false;
                        $expanded['sortColumns'] = [];
                        $expanded['languageLocale'] = $localeCode;
                        $serializedColumns[] = $expanded;
                    }

                    continue;
                }

                $serializedColumns[] = $baseSerialized;
                continue;
            }

            if (is_array($column)) {
                $serialized = $column;
                $key = '';

                if (isset($serialized['key']) && is_string($serialized['key'])) {
                    $key = trim($serialized['key']);
                } elseif (isset($serialized['field']) && is_string($serialized['field'])) {
                    $key = trim($serialized['field']);
                }

                $sortable = $key !== '' ? ($sortableMap[$key] ?? null) : null;

                if (is_array($sortable)) {
                    $serialized['sortable'] = (bool) ($sortable['enabled'] ?? false);
                    $serialized['sortColumns'] = is_array($sortable['columns'] ?? null) ? $sortable['columns'] : [];
                }

                $multiSortable = $key !== '' ? ($multiSortableMap[$key] ?? null) : null;
                if (is_array($multiSortable)) {
                    $serialized['multiSortable'] = (bool) ($multiSortable['enabled'] ?? false);
                }

                $serializedColumns[] = $serialized;
                continue;
            }

            throw new \InvalidArgumentException('Invalid column definition.');
        }

        return $serializedColumns;
    }

    protected function resolveBulkMode(): ?string
    {
        if (! $this->resolveSelectable()) {
            return null;
        }

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

    protected function buildRowMultiSelectHeaderCell(): TableHead
    {
        return TableHead::make()
            ->appearance($this->rowMultiSelectColumnAppearance())
            ->prop('data-row-multi-select-header', true);
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

    protected function buildRowMultiSelectCell(array $row): TableCell
    {
        $rowKey = (string) ($row['id'] ?? $row['uuid'] ?? uniqid('row_', true));

        return TableCell::make()
            ->appearance($this->rowMultiSelectColumnAppearance())
            ->prop('data-row-multi-select-cell', true)
            ->prop('data-row-key', $rowKey)
            ->children([
                Icon::make('lucide:menu')
                    ->appearance([
                        'class' => 'mx-auto h-4 w-4 select-none',
                    ]),
            ]);
    }

    protected function buildDndHeaderCell(): TableHead
    {
        return TableHead::make()->appearance($this->dndColumnAppearance());
    }

    protected function buildDndCell(): TableCell
    {
        return TableCell::make()
            ->appearance($this->dndColumnAppearance())
            ->children([
                Button::make()
                    ->variant('ghost')
                    ->size('icon-sm')
                    ->prop('type', 'button')
                    ->prop('data-table-dnd-handle', '1')
                    ->prop('data-column-select-ignore', 'always')
                    ->icon(
                        Icon::make('lucide:grip-vertical')
                            ->appearance([
                                'class' => 'h-4 w-4 cursor-grab active:cursor-grabbing text-slate-400',
                            ])
                    ),
            ]);
    }

    protected function selectionColumnAppearance(): array
    {
        return [
            'width' => '36px',
        ];
    }

    protected function rowMultiSelectColumnAppearance(): array
    {
        return [
            'width' => '24px',
            'class' => 'cursor-pointer select-none text-center text-slate-400',
        ];
    }

    protected function dndColumnAppearance(): array
    {
        return [
            'style' => 'width:49px;min-width:49px;max-width:49px;',
            'class' => 'text-center select-none',
        ];
    }

    protected function shouldRenderRowMultiSelectColumn(?string $bulkMode): bool
    {
        return $this->resolveSelectable() && $this->selected && $bulkMode === 'multiple';
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
        $dndEnabled = $this->isDndActive();
        $sortableMap = $this->resolveSortableColumnsMap();
        $multiSortableMap = $this->resolveMultiSortableColumnsMap($sortableMap);
        $multiSortingEnabled = $this->hasEnabledMultiSortableColumns($multiSortableMap);
        $languageExpansionLocales = $this->resolveLanguageColumnExpansionLocales();
        $languageLabels = $this->resolveTableLanguageLabelsByLocale();
        $languageIcons = $this->resolveTableLanguageIconsByLocale();
        $globalHeaderAppearance = $this->headerAppearanceProps['appearance'] ?? [];
        $globalHeaderAppearance = is_array($globalHeaderAppearance) ? $globalHeaderAppearance : [];

        if ($dndEnabled) {
            $heads[] = $this->buildDndHeaderCell();
        }

        if ($this->shouldRenderRowMultiSelectColumn($bulkMode)) {
            $heads[] = $this->buildRowMultiSelectHeaderCell();
        }

        if ($bulkMode !== null) {
            $heads[] = $this->buildSelectionHeaderCell($bulkMode);
        }

        if ($numberingMode !== null) {
            $heads[] = $this->buildNumberingHeaderCell();
        }

        foreach ($this->columnObjects as $column) {
            if ($column instanceof Column && $column->isLanguage() && count($languageExpansionLocales) > 1) {
                $baseHeadProps = $column->toHeaderProps();
                $baseKey = trim((string) ($baseHeadProps['key'] ?? $column->getKey()));
                $baseLabel = (string) ($baseHeadProps['label'] ?? ucfirst($baseKey));

                foreach ($languageExpansionLocales as $locale) {
                    $localeCode = strtolower(trim((string) $locale));
                    if ($localeCode === '') {
                        continue;
                    }

                    $headProps = $baseHeadProps;
                    $headProps['key'] = ($baseKey !== '' ? $baseKey : 'column').'__'.$localeCode;
                    $headProps['sortKey'] = $baseKey !== '' ? $baseKey : $headProps['key'];
                    $headProps['label'] = $this->buildExpandedLanguageColumnLabel($baseLabel, $localeCode, $languageLabels, $languageIcons);
                    $headProps['icon'] = $languageIcons[$localeCode] ?? null;
                    $headProps['sortable'] = false;
                    $headProps['sortColumns'] = [];
                    $headProps['multiSortable'] = false;
                    $headProps['multiSortingEnabled'] = $multiSortingEnabled;
                    $headProps['languageLocale'] = $localeCode;

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

                continue;
            }

            if ($column instanceof Column) {
                $headProps = $column->toHeaderProps();
            } elseif (is_array($column)) {
                $key = '';
                if (isset($column['key']) && is_string($column['key'])) {
                    $key = trim($column['key']);
                } elseif (isset($column['field']) && is_string($column['field'])) {
                    $key = trim($column['field']);
                }

                $headProps = $column;
                $headProps['key'] = $key;
                $headProps['label'] = $column['label'] ?? ($key !== '' ? ucfirst($key) : '');
            } else {
                continue;
            }

            $columnKey = trim((string) ($headProps['key'] ?? ''));
            if ($columnKey !== '') {
                $headProps['sortKey'] = $columnKey;
            }

            $sortable = $columnKey !== '' ? ($sortableMap[$columnKey] ?? null) : null;

            if (is_array($sortable)) {
                $headProps['sortable'] = (bool) ($sortable['enabled'] ?? false);
                $headProps['sortColumns'] = is_array($sortable['columns'] ?? null) ? $sortable['columns'] : [];
            }

            $multiSortable = $columnKey !== '' ? ($multiSortableMap[$columnKey] ?? null) : null;
            if (is_array($multiSortable)) {
                $headProps['multiSortable'] = (bool) ($multiSortable['enabled'] ?? false);
            }
            $headProps['multiSortingEnabled'] = $multiSortingEnabled;

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
            $actionsHead = TableHead::make();

            if ($this->resolveCustomColumnsEnabled()) {
                $actionsHead->children([
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

            $heads[] = $actionsHead;
        }

        $rows = [
            TableRow::make()->children($heads),
        ];

        if ($this->shouldRenderInlineFilters()) {
            $filterHeads = [];

            if ($dndEnabled) {
                $filterHeads[] = TableHead::make()
                    ->appearance($this->dndColumnAppearance())
                    ->prop('class', 'py-2');
            }

            if ($this->shouldRenderRowMultiSelectColumn($bulkMode)) {
                $filterHeads[] = TableHead::make()
                    ->appearance($this->rowMultiSelectColumnAppearance())
                    ->prop('class', 'py-2');
            }

            if ($bulkMode !== null) {
                $filterHeads[] = TableHead::make()
                    ->appearance($this->selectionColumnAppearance())
                    ->prop('class', 'py-2');
            }

            if ($numberingMode !== null) {
                $filterHeads[] = TableHead::make()->prop('class', 'py-2');
            }

            foreach ($this->columnObjects as $column) {
                if ($column instanceof Column && $column->isLanguage() && count($languageExpansionLocales) > 1) {
                    foreach ($languageExpansionLocales as $locale) {
                        $localeCode = strtolower(trim((string) $locale));
                        if ($localeCode === '') {
                            continue;
                        }

                        $filterHeads[] = TableHead::make()->prop('class', 'py-2');
                    }

                    continue;
                }

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

        if ($rows === []) {
            $rows[] = $this->buildEmptyRow($bulkMode, $numberingMode, ! empty($resolvedActions));
        }

        return TableBody::make()
            ->props($this->bodyAppearanceProps)
            ->children($rows);
    }

    protected function buildEmptyRow(?string $bulkMode, ?string $numberingMode, bool $hasActions): TableRow
    {
        return TableRow::make()->children([
            TableCell::make()
                ->prop('colspan', $this->resolveBodyColspan($bulkMode, $numberingMode, $hasActions))
                ->appearance(
                    Appearance::make()
                        ->padding('y-8')
                )
                ->children([
                    EmptyState::make()
                        ->title(__('No results found'))
                        ->icon('lucide:inbox'),
                ]),
        ]);
    }

    protected function resolveBodyColspan(?string $bulkMode, ?string $numberingMode, bool $hasActions): int
    {
        $columnsCount = $this->resolveRenderableColumnsCount();

        if ($this->isDndActive()) {
            $columnsCount++;
        }

        if ($this->shouldRenderRowMultiSelectColumn($bulkMode)) {
            $columnsCount++;
        }

        if ($bulkMode !== null) {
            $columnsCount++;
        }

        if ($numberingMode !== null) {
            $columnsCount++;
        }

        if ($hasActions) {
            $columnsCount++;
        }

        return max(1, $columnsCount);
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
        $rowKey = trim((string) $model->getKey());
        if ($rowKey === '') {
            $rowKey = (string) ($data['id'] ?? $data['uuid'] ?? uniqid('row_', true));
        }
        $tableLanguage = $this->resolveTableLanguageSelector();
        $languageExpansionLocales = $this->resolveLanguageColumnExpansionLocales();
        $dndEnabled = $this->isDndActive();

        $cells = [];

        if ($dndEnabled) {
            $cells[] = $this->buildDndCell();
        }

        if ($this->shouldRenderRowMultiSelectColumn($bulkMode)) {
            $cells[] = $this->buildRowMultiSelectCell($data);
        }

        if ($bulkMode !== null) {
            $cells[] = $this->buildSelectionCell($data, $bulkMode);
        }

        if ($number !== null) {
            $cells[] = $this->buildNumberingCell($number);
        }

        foreach ($this->columnObjects as $column) {
            if ($column instanceof Column && $column->isLanguage() && count($languageExpansionLocales) > 1) {
                foreach ($languageExpansionLocales as $locale) {
                    $localeCode = strtolower(trim((string) $locale));
                    if ($localeCode === '') {
                        continue;
                    }

                    $cells[] = $this->buildCell($column, $data, $localeCode, $tableLanguage);
                }

                continue;
            }

            if (! $column instanceof Column) {
                continue;
            }

            $cells[] = $this->buildCell($column, $data, null, $tableLanguage);
        }

        if (! empty($resolvedActions)) {
            $cells[] = $this->buildActionsCell($data, $resolvedActions);
        }

        return TableRow::make()
            ->prop('data-row-key', $rowKey)
            ->children($cells);
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
                ->appearance([
                    'style' => [
                        'paddingLeft' => '2px',
                        'paddingRight' => '2px',
                    ],
                ])
                ->width('10')
                ->children([$dropdown]);
        }

        return TableCell::make()
            ->appearance([
                'class' => 'flex gap-3',
            ])
            ->children($components);
    }

    protected function buildCell(Column $column, array $row, ?string $languageLocale = null, ?array $tableLanguage = null): TableCell
    {
        if ($tableLanguage === null) {
            $tableLanguage = $this->resolveTableLanguageSelector();
        }

        $stackLocales = $this->resolveLanguageStackLocales($column, $tableLanguage, $languageLocale);

        if ($stackLocales !== []) {
            return $this->buildStackedLanguageCell($column, $row, $stackLocales, $tableLanguage);
        }

        if ($column->isLanguage() && is_string($languageLocale) && trim($languageLocale) !== '') {
            $localizedValue = $column->resolveLanguageValueForLocale($row, $languageLocale);
            $value = $localizedValue !== null ? $localizedValue : $column->resolveState($row);
        } else {
            $value = $column->resolveState($row);
        }
        $displayValue = $this->normalizeCellValue($value);
        $isPlaceholder = $column->wasPlaceholderApplied();

        $cell = TableCell::make();
        $bodyAppearance = $column->getBodyAppearance();
        $dndField = $this->resolveDndField();
        $columnWidth = $column->getWidth();

        if (is_array($bodyAppearance) && ! empty($bodyAppearance)) {
            $cell->appearance($bodyAppearance);
        }

        if ($columnWidth !== null && $columnWidth !== '') {
            $cell->prop('width', $columnWidth);
        }

        if ($dndField !== null && $column->getKey() === $dndField) {
            $cell->prop('data-table-dnd-position-cell', '1');
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

        if ($column->getValueDisplayType() === 'bool') {
            $icon = $this->buildBooleanCellIcon($value, $displayValue, $isPlaceholder);

            if ($icon !== null) {
                return $cell->children([$icon]);
            }
        }

        if ($column->getValueDisplayType() === 'icon') {
            $icon = $this->buildIconCellValue($value, $displayValue, $isPlaceholder);

            if ($icon !== null) {
                return $cell->children([$icon]);
            }
        }

        return $cell->children([
            Text::make($displayValue),
        ]);
    }

    protected function resolveLanguageStackLocales(Column $column, ?array $tableLanguage, ?string $languageLocale): array
    {
        if (! $column->isLanguage()) {
            return [];
        }

        if ($languageLocale !== null && trim($languageLocale) !== '') {
            return [];
        }

        if (! is_array($tableLanguage)) {
            return [];
        }

        $isMulti = $this->toBoolean($tableLanguage['multi'] ?? false, false);
        $layout = strtolower(trim((string) ($tableLanguage['layout'] ?? 'stack')));
        if (! $isMulti || $layout !== 'stack') {
            return [];
        }

        $values = is_array($tableLanguage['values'] ?? null) ? $tableLanguage['values'] : [];
        $locales = [];

        foreach ($values as $value) {
            $locale = strtolower(trim((string) $value));
            if ($locale === '' || in_array($locale, $locales, true)) {
                continue;
            }

            $locales[] = $locale;
        }

        return count($locales) > 1 ? $locales : [];
    }

    protected function buildStackedLanguageCell(Column $column, array $row, array $locales, ?array $tableLanguage = null): TableCell
    {
        $cell = TableCell::make();
        $bodyAppearance = $column->getBodyAppearance();

        if (is_array($bodyAppearance) && ! empty($bodyAppearance)) {
            $cell->appearance($bodyAppearance);
        }

        $labels = $this->resolveTableLanguageLabelsByLocale($tableLanguage);
        $icons = $this->resolveTableLanguageIconsByLocale($tableLanguage);
        $lines = [];

        foreach ($locales as $locale) {
            $value = $column->resolveLanguageValueForLocale($row, $locale);
            if ($value === null || trim($value) === '') {
                continue;
            }

            $localeLabel = $labels[$locale] ?? strtoupper($locale);
            $localeIcon = $icons[$locale] ?? null;

            if (is_string($localeIcon) && trim($localeIcon) !== '') {
                $lines[] = Block::make()
                    ->appearance('flex items-center gap-2')
                    ->children([
                        Icon::make($localeIcon)->appearance('size-4'),
                        Text::make($value)->appearance('text-sm'),
                    ]);
                continue;
            }

            $lines[] = Text::make("{$localeLabel}: {$value}")->appearance('text-sm');
        }

        if ($lines === []) {
            return $cell->children([Text::make('')]);
        }

        return $cell->children([
            Block::make()
                ->appearance('grid gap-1')
                ->children($lines),
        ]);
    }

    protected function buildBooleanCellIcon(mixed $value, string $displayValue, bool $isPlaceholder): ?Icon
    {
        if ($isPlaceholder) {
            return null;
        }

        $normalized = $this->normalizeBooleanCellValue($value);

        if ($normalized === null && $displayValue !== '') {
            $normalized = $this->normalizeBooleanCellValue($displayValue);
        }

        if ($normalized === null) {
            return null;
        }

        return $normalized
            ? Icon::make('lucide:check')
                ->appearance([
                    'class' => 'h-4 w-4 text-green-600 dark:text-green-500',
                ])
            : Icon::make('lucide:x')
                ->appearance([
                    'class' => 'h-4 w-4 text-red-600 dark:text-red-500',
                ]);
    }

    protected function normalizeBooleanCellValue(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((float) $value) !== 0.0;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'n', 'off'], true)) {
            return false;
        }

        return null;
    }

    protected function buildIconCellValue(mixed $value, string $displayValue, bool $isPlaceholder): ?Icon
    {
        if ($isPlaceholder) {
            return null;
        }

        $candidate = '';

        if (is_string($value)) {
            $candidate = trim($value);
        } elseif ($displayValue !== '') {
            $candidate = trim($displayValue);
        }

        if ($candidate === '') {
            return null;
        }

        return Icon::make($candidate);
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
        $dndEnabled = $this->isDndActive();
        $languageExpansionLocales = $this->resolveLanguageColumnExpansionLocales();

        if ($dndEnabled) {
            $cells[] = TableCell::make()->appearance($this->dndColumnAppearance());
        }

        if ($this->shouldRenderRowMultiSelectColumn($bulkMode)) {
            $cells[] = TableCell::make()->appearance($this->rowMultiSelectColumnAppearance());
        }

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

            if ($column->isLanguage() && count($languageExpansionLocales) > 1) {
                foreach ($languageExpansionLocales as $locale) {
                    $localeCode = strtolower(trim((string) $locale));
                    if ($localeCode === '') {
                        continue;
                    }

                    $cells[] = TableCell::make();
                }

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

    protected function resolveRenderableColumnsCount(): int
    {
        $count = 0;
        $languageExpansionLocales = $this->resolveLanguageColumnExpansionLocales();

        foreach ($this->columnObjects as $column) {
            if ($column instanceof Column && $column->isLanguage() && count($languageExpansionLocales) > 1) {
                $count += count($languageExpansionLocales);
                continue;
            }

            $count++;
        }

        return max(1, $count);
    }

    public function build(LengthAwarePaginator $paginator): Table
    {
        $this->applyConfiguredColumnAttributes();

        $actionDisplay = $this->actionDisplay ?? config('svarium.table.action_display', 'inline');
        $selectable = $this->resolveSelectable();
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
        $resolvedTableId = $this->resolveTableIdentifier();
        $hasRecords = $paginator->total() > 0;
        $emptyCreateAction = $this->resolveEmptyCreateAction();
        $multiSortingEnabled = $this->hasEnabledMultiSortableColumns(
            $this->resolveMultiSortableColumnsMap($this->resolveSortableColumnsMap())
        );

        $table = Table::make()
            ->prop('id', $resolvedTableId)
            ->prop('hasRecords', $hasRecords)
            ->prop('emptyState', [
                'title' => __('No results found'),
                'icon' => 'lucide:inbox',
            ])
            ->prop('emptyCreateAction', $emptyCreateAction)
            ->prop('emptyTopbar', [
                'showTabs' => $this->showTabsOnEmpty,
                'showCreate' => $this->showCreateOnEmpty,
            ])
            ->prop('columns', $this->serializeColumns())
            ->children($tableChildren)
            ->actions($resolvedActions)
            ->actionDisplay($actionDisplay)
            ->slot('searchbar', $this->resolveSearchbarComponents())
            ->prop('searchAppearance', $this->resolveSearchAppearanceProps())
            ->slot('header', $this->resolveHeaderComponents())
            ->prop('views', $this->getSavedViews())
            ->prop('viewsAddable', $this->resolveViewsAddable())
            ->prop('appearance', $this->appearance ?? 'card')
            ->prop('title', $this->title)
            ->prop('description', $this->description)
            ->prop('headerActions', $this->headerActions)
            ->prop('filters', $resolvedFilters)
            ->prop('bulk', $selectable ? $this->bulkEnabled : false)
            ->prop('bulkMode', $bulkMode)
            ->prop('numbering', $this->numberingEnabled)
            ->prop('numberingMode', $numberingMode)
            ->prop('sticky', $this->stickySections)
            ->prop('columnSelection', $selectable ? $this->selected : false)
            ->prop('condensed', $this->resolveCondensed())
            ->prop('bordered', $this->resolveBordered())
            ->prop('exported', $this->exported)
            ->prop('exportUrl', $this->exportUrl)
            ->prop('imported', $this->imported)
            ->prop('dnd', $this->resolveDndConfig())
            ->prop('tableLanguage', $this->resolveTableLanguageSelector())
            ->prop('multiSortingEnabled', $multiSortingEnabled)
            ->prop('rowSelectionColumn', $this->shouldRenderRowMultiSelectColumn($bulkMode))
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

    protected function resolveTableLanguageSelector(): ?array
    {
        if (! $this->hasLanguageColumns()) {
            return null;
        }

        $rawLocales = [];
        if (function_exists('locales')) {
            try {
                $rawLocales = (array) locales();
            } catch (\Throwable) {
                $rawLocales = [];
            }
        }
        $options = [];

        foreach ($rawLocales as $locale) {
            if (! is_array($locale)) {
                continue;
            }

            $value = strtolower(trim((string) ($locale['value'] ?? '')));
            if ($value === '') {
                continue;
            }

            $label = trim((string) ($locale['label'] ?? strtoupper($value)));
            if ($label === '') {
                $label = strtoupper($value);
            }

            $icon = null;
            $localeIcon = $locale['icon'] ?? null;
            if (is_array($localeIcon)) {
                $iconValue = trim((string) ($localeIcon['value'] ?? ''));
                if ($iconValue !== '') {
                    $icon = $iconValue;
                }
            } elseif (is_string($localeIcon)) {
                $iconValue = trim($localeIcon);
                if ($iconValue !== '') {
                    $icon = $iconValue;
                }
            }

            $options[] = [
                'value' => $value,
                'label' => $label,
                'icon' => $icon,
            ];
        }

        if ($options === []) {
            $fallback = strtolower(trim((string) app()->getLocale()));
            if ($fallback !== '') {
                $options[] = [
                    'value' => $fallback,
                    'label' => strtoupper($fallback),
                ];
            }
        }

        if ($options === []) {
            return null;
        }

        $allowed = array_values(array_unique(array_map(
            static fn (array $option): string => (string) ($option['value'] ?? ''),
            $options
        )));

        $requestedRaw = strtolower(trim((string) request()->query('table_locale', '')));
        $requestedValues = array_values(array_filter(array_map(
            static fn (string $value): string => strtolower(trim($value)),
            explode(',', $requestedRaw)
        ), static fn (string $value): bool => $value !== ''));
        $requestedValues = array_values(array_unique(array_filter(
            $requestedValues,
            static fn (string $value) => in_array($value, $allowed, true)
        )));

        $appLocale = strtolower(trim((string) app()->getLocale()));
        $selected = $requestedValues !== []
            ? $requestedValues
            : [(in_array($appLocale, $allowed, true) ? $appLocale : ($allowed[0] ?? null))];
        $selected = array_values(array_filter($selected, static fn ($item): bool => is_string($item) && trim($item) !== ''));

        if ($selected === []) {
            return null;
        }

        $multi = $this->toBoolean(request()->query('table_locale_multi'), false) || count($selected) > 1;
        if (! $multi && count($selected) > 1) {
            $selected = [$selected[0]];
        }

        $layout = strtolower(trim((string) request()->query('table_locale_layout', 'stack')));
        if (! in_array($layout, ['stack', 'columns'], true)) {
            $layout = 'stack';
        }

        return [
            'enabled' => true,
            'queryParam' => 'table_locale',
            'multiQueryParam' => 'table_locale_multi',
            'layoutQueryParam' => 'table_locale_layout',
            'label' => __('Language'),
            'value' => $selected[0] ?? null,
            'values' => $selected,
            'multi' => $multi,
            'layout' => $layout,
            'supportsMulti' => true,
            'supportsLayout' => true,
            'options' => $options,
        ];
    }

    protected function resolveTableLanguageLabelsByLocale(?array $languageConfig = null): array
    {
        $languageConfig ??= $this->resolveTableLanguageSelector();
        if (! is_array($languageConfig)) {
            return [];
        }

        $options = is_array($languageConfig['options'] ?? null) ? $languageConfig['options'] : [];
        $map = [];

        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            $value = strtolower(trim((string) ($option['value'] ?? '')));
            if ($value === '') {
                continue;
            }

            $label = trim((string) ($option['label'] ?? strtoupper($value)));
            $map[$value] = $label !== '' ? $label : strtoupper($value);
        }

        return $map;
    }

    protected function resolveTableLanguageIconsByLocale(?array $languageConfig = null): array
    {
        $languageConfig ??= $this->resolveTableLanguageSelector();
        if (! is_array($languageConfig)) {
            return [];
        }

        $options = is_array($languageConfig['options'] ?? null) ? $languageConfig['options'] : [];
        $map = [];

        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            $value = strtolower(trim((string) ($option['value'] ?? '')));
            if ($value === '') {
                continue;
            }

            $icon = trim((string) ($option['icon'] ?? ''));
            if ($icon === '') {
                continue;
            }

            $map[$value] = $icon;
        }

        return $map;
    }

    protected function resolveLanguageColumnExpansionLocales(?array $languageConfig = null): array
    {
        $languageConfig ??= $this->resolveTableLanguageSelector();
        if (! is_array($languageConfig)) {
            return [];
        }

        $multi = $this->toBoolean($languageConfig['multi'] ?? false, false);
        $layout = strtolower(trim((string) ($languageConfig['layout'] ?? 'stack')));
        if (! $multi || $layout !== 'columns') {
            return [];
        }

        $values = is_array($languageConfig['values'] ?? null) ? $languageConfig['values'] : [];
        $locales = [];

        foreach ($values as $value) {
            $locale = strtolower(trim((string) $value));
            if ($locale === '' || in_array($locale, $locales, true)) {
                continue;
            }

            $locales[] = $locale;
        }

        return count($locales) > 1 ? $locales : [];
    }

    protected function buildExpandedLanguageColumnLabel(
        string $baseLabel,
        string $locale,
        array $languageLabels,
        array $languageIcons = []
    ): string
    {
        $normalizedBase = trim($baseLabel);
        $localeCode = strtolower(trim($locale));
        $localeLabel = $languageLabels[$localeCode] ?? strtoupper($localeCode);
        $hasIcon = is_string($languageIcons[$localeCode] ?? null)
            && trim((string) ($languageIcons[$localeCode] ?? '')) !== '';

        if ($normalizedBase === '') {
            return $localeLabel;
        }

        if ($hasIcon) {
            return $normalizedBase;
        }

        return $normalizedBase.' ('.$localeLabel.')';
    }

    protected function hasLanguageColumns(): bool
    {
        foreach ($this->columnObjects as $column) {
            if ($column instanceof Column && $column->isLanguage()) {
                return true;
            }
        }

        return false;
    }

    protected function resolveTableIdentifier(): string
    {
        if (is_string($this->resolvedTableIdentifier) && trim($this->resolvedTableIdentifier) !== '') {
            return $this->resolvedTableIdentifier;
        }

        if (is_string($this->runtimeTableIdentifier) && trim($this->runtimeTableIdentifier) !== '') {
            $this->resolvedTableIdentifier = $this->runtimeTableIdentifier;

            return $this->resolvedTableIdentifier;
        }

        $preferred = $this->id;

        if (! is_string($preferred) || trim($preferred) === '') {
            $preferred = $this->generateDefaultTableIdentifier();
        }

        $this->resolvedTableIdentifier = $this->ensureUniqueTableIdentifier($preferred);

        return $this->resolvedTableIdentifier;
    }

    protected function generateDefaultTableIdentifier(): string
    {
        $segments = $this->resolveIdentifierPathSegments();

        if ($segments === []) {
            return 'page-index-table';
        }

        $resource = $this->normalizeTableIdentifier($segments[0] ?? '');
        if ($resource === '') {
            $resource = 'page';
        }

        $action = $this->normalizeTableIdentifier($this->resolveIdentifierAction($segments));
        if ($action === '') {
            $action = 'index';
        }

        return "{$resource}-{$action}-table";
    }

    protected function normalizeTableIdentifier(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        $normalized = Str::of($trimmed)
            ->replaceMatches('/[^A-Za-z0-9\-_:.]+/', '-')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->lower()
            ->value();

        return $normalized;
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

    protected function resolveCondensed(): bool
    {
        if ($this->condensed !== null) {
            return $this->condensed;
        }

        $configured = config('upsoftware.table.condensed');

        if ($configured === null) {
            $configured = config('upsoftware.table.condensed', false);
        }

        return $this->toBoolean($configured, false);
    }

    protected function resolveBordered(): bool
    {
        if ($this->bordered !== null) {
            return $this->bordered;
        }

        return $this->toBoolean(config('upsoftware.table.bordered'), false);
    }

    protected function resolveSelectable(): bool
    {
        if ($this->selectable !== null) {
            return $this->selectable;
        }

        return $this->toBoolean(config('upsoftware.table.selectable'), true);
    }

    protected function resolveCustomColumnsEnabled(): bool
    {
        if ($this->customColumns !== null) {
            return $this->customColumns;
        }

        return $this->toBoolean(config('upsoftware.table.custom_columns'), true);
    }

    protected function resolveViewsAddable(): bool
    {
        if ($this->viewsAddable !== null) {
            return $this->viewsAddable;
        }

        return $this->toBoolean(config('upsoftware.table.views_addable'), true);
    }

    protected function applyConfiguredColumnAttributes(): void
    {
        if ($this->columnAttributes === []) {
            return;
        }

        foreach ($this->columnObjects as $index => $column) {
            $key = null;

            if ($column instanceof Column) {
                $key = $column->getKey();
            } elseif (is_array($column)) {
                $key = isset($column['key']) && is_string($column['key'])
                    ? trim($column['key'])
                    : (isset($column['field']) && is_string($column['field']) ? trim($column['field']) : null);
            }

            if (! is_string($key) || $key === '' || ! array_key_exists($key, $this->columnAttributes)) {
                continue;
            }

            $attributes = $this->columnAttributes[$key];

            if (is_string($attributes)) {
                $attributes = ['label' => $attributes];
            }

            if (! is_array($attributes)) {
                continue;
            }

            if ($column instanceof Column) {
                $this->applyAttributesToColumnObject($column, $attributes);
                continue;
            }

            if (is_array($column)) {
                $this->columnObjects[$index] = [
                    ...$attributes,
                    ...$column,
                ];
            }
        }
    }

    protected function applyAttributesToColumnObject(Column $column, array $attributes): void
    {
        foreach ($attributes as $name => $value) {
            if (! is_string($name) || trim($name) === '') {
                continue;
            }

            $attribute = trim($name);

            switch ($attribute) {
                case 'label':
                    if (is_string($value) && trim($value) !== '') {
                        $column->label($value);
                    }
                    break;

                case 'sortable':
                    if (is_bool($value)) {
                        $column->sortable($value);
                    } elseif (is_string($value) || is_array($value)) {
                        $column->sortable($value);
                    }
                    break;

                case 'multi_sortable':
                case 'multiSortable':
                    if (is_bool($value)) {
                        $column->multiSortable($value);
                    } elseif (is_string($value)) {
                        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        if ($parsed !== null) {
                            $column->multiSortable($parsed);
                        }
                    }
                    break;

                case 'searchable':
                    $column->searchable((bool) $value);
                    break;

                case 'selected':
                    $column->selected((bool) $value);
                    break;

                case 'visible':
                    if ($value === false) {
                        $column->hide();
                    }
                    break;

                case 'filter':
                case 'filterable':
                    if (is_bool($value)) {
                        $column->filter($value);
                    } elseif (is_array($value)) {
                        $column->filter($value);
                    } elseif (is_string($value)) {
                        $column->filter($value);
                    }
                    break;

                case 'operators':
                    if (is_array($value)) {
                        $column->operators($value);
                    }
                    break;

                case 'type':
                    if (is_string($value) && trim($value) !== '') {
                        $column->type($value);
                    }
                    break;

                case 'footer':
                    if (is_string($value)) {
                        $column->footer($value);
                    }
                    break;

                case 'appearanceHeader':
                case 'headerAppearance':
                    if (is_array($value) || $value instanceof Appearance) {
                        $column->appearanceHeader($value);
                    }
                    break;

                case 'appearanceSearch':
                case 'searchAppearance':
                    if (is_array($value) || $value instanceof Appearance) {
                        $column->appearanceSearch($value);
                    }
                    break;

                case 'appearanceFooter':
                case 'footerAppearance':
                    if (is_array($value) || $value instanceof Appearance) {
                        $column->appearanceFooter($value);
                    }
                    break;

                case 'appearance':
                case 'bodyAppearance':
                    if (is_string($value)) {
                        $column->appearance($value);
                    } elseif (is_array($value) || $value instanceof Appearance) {
                        $column->bodyAppearance($value);
                    }
                    break;

                default:
                    $column->prop($attribute, $value);
                    break;
            }
        }
    }
}
