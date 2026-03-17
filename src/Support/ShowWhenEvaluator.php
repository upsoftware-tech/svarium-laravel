<?php

namespace Upsoftware\Svarium\Support;

class ShowWhenEvaluator
{
    public static function matches(mixed $condition, array $data): bool
    {
        $definition = self::normalizeCondition($condition);

        if ($definition === null) {
            return true;
        }

        $actual = self::getValue($data, $definition['field']);
        $operator = $definition['operator'];
        $expected = $definition['value'];

        return match ($operator) {
            'truthy' => self::isTruthy($actual),
            'falsy' => ! self::isTruthy($actual),
            'empty', 'is_empty' => self::isEmpty($actual),
            'not_empty', 'is_not_empty', 'notempty' => ! self::isEmpty($actual),
            'in' => self::inList($actual, $expected),
            'not_in', 'notin' => ! self::inList($actual, $expected),
            '!=', '<>', 'neq', 'not' => ! self::equals($actual, $expected),
            default => self::equals($actual, $expected),
        };
    }

    protected static function normalizeCondition(mixed $condition): ?array
    {
        if (! is_array($condition)) {
            return null;
        }

        $field = trim((string) ($condition['field'] ?? ''));

        if ($field === '') {
            return null;
        }

        $operator = strtolower(trim((string) ($condition['operator'] ?? 'truthy')));
        $operator = str_replace(' ', '_', $operator);

        return [
            'field' => $field,
            'operator' => $operator,
            'value' => $condition['value'] ?? null,
        ];
    }

    protected static function getValue(array $data, string $field): mixed
    {
        $normalized = str_replace([']', '['], ['', '.'], $field);
        $normalized = trim($normalized, '.');

        if ($normalized === '') {
            return null;
        }

        return data_get($data, $normalized);
    }

    protected static function equals(mixed $left, mixed $right): bool
    {
        if (is_array($left) || is_array($right)) {
            $leftValues = is_array($left) ? $left : [$left];
            $rightValues = is_array($right) ? $right : [$right];

            foreach ($leftValues as $leftValue) {
                foreach ($rightValues as $rightValue) {
                    if (self::equals($leftValue, $rightValue)) {
                        return true;
                    }
                }
            }

            return false;
        }

        return (string) ($left ?? '') === (string) ($right ?? '');
    }

    protected static function inList(mixed $actual, mixed $expected): bool
    {
        $expectedValues = is_array($expected) ? $expected : [$expected];

        foreach ($expectedValues as $expectedValue) {
            if (self::equals($actual, $expectedValue)) {
                return true;
            }
        }

        return false;
    }

    protected static function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }

    protected static function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value !== 0.0;
        }

        if (is_string($value)) {
            return ! in_array(strtolower(trim($value)), ['', '0', 'false', 'null', 'undefined'], true);
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return (bool) $value;
    }
}
