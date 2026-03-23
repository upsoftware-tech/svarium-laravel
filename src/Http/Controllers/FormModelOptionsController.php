<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormModelOptionsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model' => ['required', 'string'],
            'value' => ['nullable', 'string'],
            'label' => ['nullable', 'string'],
            'q' => ['nullable', 'string'],
            'depends' => ['nullable'],
            'depends_field' => ['nullable', 'string'],
            'depends_value' => ['nullable'],
            'show_when_empty' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'selected' => ['nullable'],
            'orders' => ['nullable'],
        ]);

        $modelClass = trim((string) ($validated['model'] ?? ''));
        if ($modelClass === '' || ! $this->safeClassExists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return response()->json(['options' => []]);
        }

        $valueColumn = trim((string) ($validated['value'] ?? 'id'));
        $labelColumn = trim((string) ($validated['label'] ?? 'name'));
        $search = trim((string) ($validated['q'] ?? ''));
        $limit = (int) ($validated['limit'] ?? config('upsoftware.form.select_options.limit', 200));
        $limit = max(1, min($limit, 500));
        $dependencies = $this->normalizeDependencies($validated);

        $query = $modelClass::query();

        if ($this->dependenciesRequireEmptyResult($dependencies)) {
            return response()->json(['options' => []]);
        }

        $this->applyDependenciesToQuery($query, $dependencies);

        if ($search !== '' && $labelColumn !== '' && ! str_contains($labelColumn, '.')) {
            $query->where($labelColumn, 'like', '%'.$search.'%');
        }

        foreach ($this->normalizeOrders($validated['orders'] ?? null) as [$column, $direction]) {
            $query->orderBy($column, $direction);
        }

        $records = $query->limit($limit)->get();
        $options = [];

        foreach ($records as $record) {
            $value = data_get($record, $valueColumn);
            if ($value === null) {
                continue;
            }

            $label = data_get($record, $labelColumn, $value);
            $options[] = [
                'value' => $value,
                'label' => $this->resolveLocalizedLabel($label, $value),
            ];
        }

        $selectedValues = $this->normalizeSelectedValues($validated['selected'] ?? null);
        if ($selectedValues !== []) {
            $existing = [];
            foreach ($options as $option) {
                $existing[(string) ($option['value'] ?? '')] = true;
            }

            $missing = array_values(array_filter($selectedValues, static fn (string $value): bool => ! isset($existing[$value])));

            if ($missing !== []) {
                $selectedQuery = $modelClass::query()->whereIn($valueColumn, $missing);

                if (! $this->dependenciesRequireEmptyResult($dependencies)) {
                    $this->applyDependenciesToQuery($selectedQuery, $dependencies);
                }

                foreach ($selectedQuery->get() as $record) {
                    $value = data_get($record, $valueColumn);
                    if ($value === null) {
                        continue;
                    }

                    $label = data_get($record, $labelColumn, $value);
                    $options[] = [
                        'value' => $value,
                        'label' => $this->resolveLocalizedLabel($label, $value),
                    ];
                }
            }
        }

        return response()->json([
            'options' => array_values($options),
        ]);
    }

    protected function normalizeSelectedValues(mixed $selected): array
    {
        if ($selected === null) {
            return [];
        }

        $values = is_array($selected) ? $selected : [$selected];
        $normalized = [];

        foreach ($values as $value) {
            $text = trim((string) $value);
            if ($text === '' || isset($normalized[$text])) {
                continue;
            }

            $normalized[$text] = $text;
        }

        return array_values($normalized);
    }

    protected function normalizeOrders(mixed $orders): array
    {
        if (is_string($orders)) {
            $decoded = json_decode($orders, true);
            if (is_array($decoded)) {
                $orders = $decoded;
            }
        }

        if (! is_array($orders)) {
            return [];
        }

        $normalized = [];

        foreach ($orders as $order) {
            if (! is_array($order)) {
                continue;
            }

            $column = trim((string) ($order['column'] ?? ''));
            if ($column === '') {
                continue;
            }

            $direction = strtolower(trim((string) ($order['direction'] ?? 'asc'))) === 'desc'
                ? 'desc'
                : 'asc';

            $normalized[] = [$column, $direction];
        }

        return $normalized;
    }

    protected function safeClassExists(string $class): bool
    {
        set_error_handler(static function (): bool {
            return true;
        });

        try {
            return class_exists($class);
        } catch (\Throwable) {
            return false;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, array{field:string,optionField:string,value:mixed,showWhenEmpty:bool,includeNull:bool}>
     */
    protected function normalizeDependencies(array $validated): array
    {
        $rawDependencies = $validated['depends'] ?? null;
        $dependencies = [];

        if (is_string($rawDependencies)) {
            $decoded = json_decode($rawDependencies, true);
            if (is_array($decoded)) {
                $rawDependencies = $decoded;
            }
        }

        if (is_array($rawDependencies)) {
            $list = array_is_list($rawDependencies) ? $rawDependencies : [$rawDependencies];

            foreach ($list as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $field = trim((string) ($entry['field'] ?? ''));
                $optionField = trim((string) ($entry['optionField'] ?? $field));

                if ($field === '' || $optionField === '') {
                    continue;
                }

                $dependencies[] = [
                    'field' => $field,
                    'optionField' => $optionField,
                    'value' => $entry['value'] ?? null,
                    'showWhenEmpty' => filter_var($entry['showWhenEmpty'] ?? false, FILTER_VALIDATE_BOOL),
                    'includeNull' => filter_var($entry['includeNull'] ?? false, FILTER_VALIDATE_BOOL),
                ];
            }
        }

        if ($dependencies !== []) {
            return $dependencies;
        }

        // Backward compatibility with single dependency query params.
        $dependsField = trim((string) ($validated['depends_field'] ?? ''));
        if ($dependsField === '') {
            return [];
        }

        $dependencies[] = [
            'field' => $dependsField,
            'optionField' => $dependsField,
            'value' => $validated['depends_value'] ?? null,
            'showWhenEmpty' => filter_var($validated['show_when_empty'] ?? false, FILTER_VALIDATE_BOOL),
            'includeNull' => false,
        ];

        return $dependencies;
    }

    /**
     * @param  array<int, array{field:string,optionField:string,value:mixed,showWhenEmpty:bool,includeNull:bool}>  $dependencies
     */
    protected function dependenciesRequireEmptyResult(array $dependencies): bool
    {
        foreach ($dependencies as $dependency) {
            $normalizedValue = trim((string) ($dependency['value'] ?? ''));

            if ($normalizedValue === '' && ($dependency['showWhenEmpty'] ?? false) !== true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{field:string,optionField:string,value:mixed,showWhenEmpty:bool,includeNull:bool}>  $dependencies
     */
    protected function applyDependenciesToQuery($query, array $dependencies): void
    {
        foreach ($dependencies as $dependency) {
            $column = trim((string) ($dependency['optionField'] ?? ''));
            if ($column === '') {
                continue;
            }

            $value = $dependency['value'] ?? null;
            $normalizedValue = trim((string) $value);
            if ($normalizedValue === '') {
                continue;
            }
            $query->where($column, $value);
        }
    }

    protected function resolveLocalizedLabel(mixed $label, mixed $fallback): string
    {
        if (is_array($label)) {
            return $this->resolveLabelFromArray($label, $fallback);
        }

        if (is_string($label)) {
            $trimmed = trim($label);

            if ($trimmed !== '' && (($trimmed[0] ?? '') === '{' || ($trimmed[0] ?? '') === '[')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return $this->resolveLabelFromArray($decoded, $fallback);
                }
            }

            return $label;
        }

        if (is_scalar($label)) {
            return (string) $label;
        }

        return is_scalar($fallback) ? (string) $fallback : '';
    }

    protected function resolveLabelFromArray(array $label, mixed $fallback): string
    {
        if ($label === []) {
            return is_scalar($fallback) ? (string) $fallback : '';
        }

        $locale = strtolower(trim((string) app()->getLocale()));
        $fallbackLocale = strtolower(trim((string) config('app.fallback_locale', 'en')));
        $fallbackShort = strtolower(trim((string) strtok($fallbackLocale, '_')));
        $localeShort = strtolower(trim((string) strtok($locale, '_')));

        foreach ([$locale, $localeShort, $fallbackLocale, $fallbackShort, 'en'] as $key) {
            if ($key === '' || ! array_key_exists($key, $label)) {
                continue;
            }

            $value = $label[$key];
            if (is_scalar($value) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        foreach ($label as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return is_scalar($fallback) ? (string) $fallback : '';
    }
}
