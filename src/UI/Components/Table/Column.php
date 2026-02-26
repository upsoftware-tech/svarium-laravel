<?php

namespace Upsoftware\Svarium\UI\Components\Table;

use Illuminate\Support\Carbon;
use Upsoftware\Svarium\UI\Appearance;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasDefault;
use Upsoftware\Svarium\UI\Concerns\Props\HasPlaceholder;
use Upsoftware\Svarium\UI\Concerns\Props\HasState;

class Column extends Component
{
    use HasDefault, HasState, HasPlaceholder;

    protected string $key = '';

    protected ?Action $action = null;

    protected bool $sortable = false;

    protected bool $searchable = false;

    protected bool $visible = true;

    protected bool $filterEnabled = false;

    protected ?string $filterType = null;

    protected array $filterOperators = [];

    protected ?string $filterLabel = null;

    protected ?string $filterAppearance = null;

    protected string $filterMode = 'multiple';

    protected bool $filterRuleConfigured = false;

    protected array $concatKeys = [];

    protected ?string $valueDisplayType = null;

    protected ?string $valueDisplayFormat = null;

    protected ?string $footerDefinition = null;

    public static function make(array|string|null $name = null): static
    {
        $instance = new static;

        if (is_array($name)) {
            return $instance->concat($name);
        }

        if ($name !== null && trim($name) !== '') {
            $instance->key = trim($name);
        }

        return $instance;
    }

    public function resolveState(array|object $row)
    {
        if (is_object($row)) {
            $row = $row->toArray();
        }

        if ($this->key === '' && empty($this->concatKeys) && $this->stateCallback === null) {
            throw new \InvalidArgumentException('Column key is required when concat() or state() is not used.');
        }

        $value = ! empty($this->concatKeys)
            ? $this->resolveConcatState($row)
            : $this->resolveRawState($row);

        $value = $this->applyValueFormatting($value);
        $value = $this->applyDefault($value);
        $value = $this->applyPlaceholder($value);

        return $value;
    }

    public function label(string $label): static
    {
        $this->filterLabel = $label;

        return $this->prop('label', $label);
    }

    public function action(string|Action $action): static
    {
        if (is_string($action)) {

            if (! method_exists(Action::class, $action)) {
                throw new \InvalidArgumentException("Action {$action} does not exist.");
            }

            $this->action = Action::$action();
        }

        if ($action instanceof Action) {
            $this->action = $action;
        }

        return $this;
    }

    public function toHeaderProps(): array
    {
        $key = $this->resolveKey();
        $props = [
            ...$this->props,
        ];

        unset($props['appearanceHeader'], $props['appearanceFooter'], $props['appearanceSearch']);

        $headerAppearance = $this->getHeaderAppearance();

        if ($headerAppearance !== null) {
            $props['appearance'] = $headerAppearance;
        } elseif (array_key_exists('appearance', $props)) {
            unset($props['appearance']);
        }

        return [
            'key' => $key,
            'label' => $this->props['label'] ?? ucfirst($key),
            'sortable' => $this->sortable,
            'searchable' => $this->searchable,
            'visible' => $this->visible,
            ...$props,
        ];
    }

    public function hasAction(): bool
    {
        return $this->action !== null;
    }

    public function getAction(): ?Action
    {
        return $this->action;
    }

    public function getKey(): string
    {
        return $this->resolveKey();
    }

    public function sortable(bool $state = true): static
    {
        $this->sortable = $state;

        return $this;
    }

    public function searchable(bool $state = true): static
    {
        $this->searchable = $state;

        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function hide(): static
    {
        $this->visible = false;

        return $this;
    }

    public function filter(bool|array|string ...$config): static
    {
        if (count($config) === 0) {
            $this->filterEnabled = true;

            return $this;
        }

        if (count($config) === 1 && is_bool($config[0])) {
            $this->filterEnabled = $config[0];

            return $this;
        }

        if (count($config) === 1 && is_array($config[0]) && $this->isAssocArray($config[0])) {
            return $this->filterFromAssocConfig($config[0]);
        }

        $tokens = $this->normalizeStringList($config);

        if (empty($tokens)) {
            $this->filterEnabled = true;

            return $this;
        }

        $this->filterEnabled = true;

        foreach ($tokens as $token) {
            $normalized = strtolower($token);

            if (in_array($normalized, ['drawer', 'inline', 'both'], true)) {
                $this->filterAppearance = $normalized;
                continue;
            }

            if (in_array($normalized, ['single', 'multiple'], true)) {
                $this->filterMode = $normalized;
                continue;
            }

            throw new \InvalidArgumentException(
                "Invalid filter option [{$token}]. Allowed values: drawer, inline, both, single, multiple."
            );
        }

        return $this;
    }

    public function filterRule(array|string ...$rules): static
    {
        $this->filterEnabled = true;
        $this->filterRuleConfigured = true;

        if (count($rules) === 0) {
            $this->filterOperators = [];

            return $this;
        }

        $this->filterOperators = $this->normalizeOperatorsList($rules);

        return $this;
    }

    public function type(string $type): static
    {
        $this->filterType = trim($type);

        return $this;
    }

    public function operators(array $operators): static
    {
        $this->filterEnabled = true;
        $this->filterRuleConfigured = true;
        $this->filterOperators = $this->normalizeOperatorsList($operators);

        return $this;
    }

    public function hasFilter(): bool
    {
        return $this->filterEnabled;
    }

    public function date(?string $format = null): static
    {
        $this->valueDisplayType = 'date';

        if ($format !== null) {
            $this->format($format);
        }

        return $this;
    }

    public function dateTime(?string $format = null): static
    {
        $this->valueDisplayType = 'datetime';

        if ($format !== null) {
            $this->format($format);
        }

        return $this;
    }

    public function time(?string $format = null): static
    {
        $this->valueDisplayType = 'time';

        if ($format !== null) {
            $this->format($format);
        }

        return $this;
    }

    public function format(string $format): static
    {
        $normalized = trim($format);
        $this->valueDisplayFormat = $normalized !== '' ? $normalized : null;

        return $this;
    }

    public function footer(?string $definition): static
    {
        $normalized = trim((string) $definition);
        $this->footerDefinition = $normalized !== '' ? $normalized : null;

        return $this;
    }

    public function appearanceHeader(array|Appearance $appearance): static
    {
        return $this->prop('appearanceHeader', $this->normalizeAppearanceValue($appearance));
    }

    public function headerAppearance(array|Appearance $appearance): static
    {
        return $this->appearanceHeader($appearance);
    }

    public function appearanceFooter(array|Appearance $appearance): static
    {
        return $this->prop('appearanceFooter', $this->normalizeAppearanceValue($appearance));
    }

    public function appearanceSearch(array|Appearance $appearance): static
    {
        return $this->prop('appearanceSearch', $this->normalizeAppearanceValue($appearance));
    }

    public function searchAppearance(array|Appearance $appearance): static
    {
        return $this->appearanceSearch($appearance);
    }

    public function footerAppearance(array|Appearance $appearance): static
    {
        return $this->appearanceFooter($appearance);
    }

    public function bodyAppearance(array|Appearance $appearance): static
    {
        return $this->appearance($appearance);
    }

    public function getBodyAppearance(): ?array
    {
        $appearance = $this->getProp('appearance');

        return is_array($appearance) ? $appearance : null;
    }

    public function getHeaderAppearance(): ?array
    {
        $header = $this->getProp('appearanceHeader');

        if (is_array($header)) {
            return $header;
        }

        return null;
    }

    public function getFooterAppearance(): ?array
    {
        $footer = $this->getProp('appearanceFooter');

        if (is_array($footer)) {
            return $footer;
        }

        return null;
    }

    public function getSearchAppearance(): ?array
    {
        $search = $this->getProp('appearanceSearch');

        if (is_array($search)) {
            return $search;
        }

        return $this->getHeaderAppearance();
    }

    public function getFooterDefinition(): ?string
    {
        return $this->footerDefinition;
    }

    public function toFilterDefinition(?string $defaultAppearance = null): ?array
    {
        if (! $this->filterEnabled) {
            return null;
        }

        $field = $this->resolveKey();
        $type = $this->filterType ?? 'string';
        $appearance = $this->resolveFilterAppearance($defaultAppearance ?? 'drawer');
        $ruleEnabled = $this->filterRuleConfigured || $this->filterMode === 'multiple';
        $operators = [];

        if ($ruleEnabled) {
            $operators = ! empty($this->filterOperators)
                ? $this->filterOperators
                : $this->defaultOperatorsForType($type);
        }

        return [
            'field' => $field,
            'label' => $this->filterLabel ?? ($this->props['label'] ?? ucfirst($field)),
            'type' => $type,
            'appearance' => $appearance,
            'mode' => $this->filterMode,
            'multiple' => $this->filterMode === 'multiple',
            'rule' => $ruleEnabled,
            'operators' => $this->toOperatorOptions($operators),
        ];
    }

    public function resolveFilterAppearance(string $fallback = 'drawer'): string
    {
        $appearance = strtolower(trim($this->filterAppearance ?? $fallback));

        if (! in_array($appearance, ['drawer', 'inline', 'both'], true)) {
            return 'drawer';
        }

        return $appearance;
    }

    public function getFilterMode(): string
    {
        return $this->filterMode;
    }

    public function concat(array|string ...$keys): static
    {
        $flattened = [];

        foreach ($keys as $key) {
            if (is_array($key)) {
                foreach ($key as $nestedKey) {
                    if (is_string($nestedKey) && trim($nestedKey) !== '') {
                        $flattened[] = trim($nestedKey);
                    }
                }

                continue;
            }

            if (trim($key) !== '') {
                $flattened[] = trim($key);
            }
        }

        $this->concatKeys = array_values(array_unique($flattened));

        if ($this->key === '' && ! empty($this->concatKeys)) {
            $this->key = $this->buildConcatKey($this->concatKeys);
        }

        return $this;
    }

    protected function resolveConcatState(array $row): ?string
    {
        $parts = [];

        foreach ($this->concatKeys as $path) {
            $part = $this->normalizeConcatPart(data_get($row, $path));

            if ($part === null || $part === '') {
                continue;
            }

            $parts[] = $part;
        }

        if (empty($parts)) {
            return null;
        }

        return implode(' ', $parts);
    }

    protected function applyValueFormatting(mixed $value): mixed
    {
        $format = $this->resolveValueDisplayFormat();

        if ($format === null || $value === null || $value === '') {
            return $value;
        }

        $date = $this->parseDateValue($value);

        if ($date === null) {
            return $value;
        }

        return $date->format($this->normalizeValueDisplayFormat($format));
    }

    protected function resolveValueDisplayFormat(): ?string
    {
        if ($this->valueDisplayFormat !== null) {
            return $this->valueDisplayFormat;
        }

        return match ($this->valueDisplayType) {
            'date' => 'Y-m-d',
            'time' => 'H:i',
            'datetime' => 'Y-m-d H:i',
            default => null,
        };
    }

    protected function parseDateValue(mixed $value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_int($value) || is_float($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        try {
            if (ctype_digit($trimmed) && strlen($trimmed) >= 10) {
                return Carbon::createFromTimestamp((int) $trimmed);
            }

            return Carbon::parse($trimmed);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function normalizeValueDisplayFormat(string $format): string
    {
        return strtr($format, [
            'YYYY' => 'Y',
            'YY' => 'y',
            'DD' => 'd',
            'HH' => 'H',
            'hh' => 'h',
            'ii' => 'i',
            'ss' => 's',
        ]);
    }

    protected function normalizeConcatPart(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded === false ? null : $encoded;
        }

        return null;
    }

    protected function defaultOperatorsForType(string $type): array
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

    protected function normalizeStringList(array $items): array
    {
        $flattened = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                foreach ($item as $nestedItem) {
                    if (is_string($nestedItem) && trim($nestedItem) !== '') {
                        $flattened[] = trim($nestedItem);
                    }
                }

                continue;
            }

            if (is_string($item) && trim($item) !== '') {
                $flattened[] = trim($item);
            }
        }

        return array_values(array_unique($flattened));
    }

    protected function normalizeOperatorsList(array $operators): array
    {
        return array_values(array_unique(array_map(
            fn (string $operator) => $this->normalizeOperatorName($operator),
            $this->normalizeStringList($operators)
        )));
    }

    protected function normalizeOperatorName(string $operator): string
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

    protected function isAssocArray(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    protected function filterFromAssocConfig(array $config): static
    {
        $this->filterEnabled = true;

        if (isset($config['label']) && is_string($config['label'])) {
            $this->filterLabel = trim($config['label']);
        }

        if (isset($config['type']) && is_string($config['type'])) {
            $this->filterType = trim($config['type']);
        }

        if (isset($config['appearance']) && is_string($config['appearance'])) {
            $appearance = strtolower(trim($config['appearance']));
            if (in_array($appearance, ['drawer', 'inline', 'both'], true)) {
                $this->filterAppearance = $appearance;
            }
        }

        if (isset($config['mode']) && is_string($config['mode'])) {
            $mode = strtolower(trim($config['mode']));
            if (in_array($mode, ['single', 'multiple'], true)) {
                $this->filterMode = $mode;
            }
        }

        if (isset($config['operators']) && is_array($config['operators'])) {
            $this->filterRuleConfigured = true;
            $this->filterOperators = $this->normalizeOperatorsList($config['operators']);
        }

        return $this;
    }

    protected function resolveKey(): string
    {
        if ($this->key !== '') {
            return $this->key;
        }

        if (! empty($this->concatKeys)) {
            return $this->buildConcatKey($this->concatKeys);
        }

        return 'column';
    }

    protected function buildConcatKey(array $keys): string
    {
        $normalized = array_map(
            static fn (string $key) => preg_replace('/[^a-zA-Z0-9_]+/', '_', trim($key)) ?? '',
            $keys
        );

        $normalized = array_map(
            static fn (string $key) => trim($key, '_'),
            $normalized
        );

        $normalized = array_values(array_filter($normalized, static fn (string $key) => $key !== ''));

        if (empty($normalized)) {
            return 'column';
        }

        return implode('_', $normalized);
    }

    protected function normalizeAppearanceValue(array|Appearance $appearance): array
    {
        if ($appearance instanceof Appearance) {
            return $appearance->toArray();
        }

        return $appearance;
    }

    public function toArray(): array
    {
        $key = $this->resolveKey();

        return [
            'key' => $key,
            'label' => $this->props['label'] ?? ucfirst($key),
            'sortable' => $this->sortable,
            'searchable' => $this->searchable,
            'visible' => $this->visible,
            'filterable' => $this->filterEnabled,
            'filter' => $this->toFilterDefinition(),
            'footer' => $this->footerDefinition,
        ];
    }
}
