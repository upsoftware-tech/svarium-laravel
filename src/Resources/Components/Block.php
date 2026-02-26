<?php

namespace Upsoftware\Svarium\Resources\Components;

abstract class Block extends Component
{
    public array $gap = [];
    public array $gapX = [];
    public array $gapY = [];
    public ?array $rows = [];

    protected function handleBreakpointProp(string $property, int|string|array $value, ?int $amount = null): static
    {
        if (is_array($value)) {
            $this->{$property} = array_merge($this->{$property}, $value);
        } else {
            $this->setBreakpointValue($property, $value, $amount);
        }

        return $this;
    }

    public function gap(int|string|array $value, ?int $amount = null): static
    {
        return $this->handleBreakpointProp('gap', $value, $amount);
    }

    public function gapX(int|string|array $value, ?int $amount = null): static
    {
        return $this->handleBreakpointProp('gapX', $value, $amount);
    }

    public function gapY(int|string|array $value, ?int $amount = null): static
    {
        return $this->handleBreakpointProp('gapY', $value, $amount);
    }

    protected function setBreakpointValue(string $property, int|string $key, ?int $value = null): void
    {
        if (is_numeric($key)) {
            $this->{$property}['default'] = (int) $key;
        } else {
            $this->{$property}[$key] = $value;
        }
    }

    public function rows(array $value): static
    {
        $this->rows = $value;
        return $this;
    }

    public function getRows(): array {
        return $this->renderComponent($this->rows);
    }

    public function props(): array
    {
        return $this->mergeOptions(array_filter([
            'gap'  => $this->gap,
            'gapX' => $this->gapX,
            'gapY' => $this->gapY,
            'rows' => $this->getRows()
        ], fn($val) => !empty($val)));
    }
}
