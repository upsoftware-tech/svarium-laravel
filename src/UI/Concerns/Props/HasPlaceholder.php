<?php

namespace Upsoftware\Svarium\UI\Concerns\Props;

trait HasPlaceholder
{
    protected ?string $placeholder = null;
    protected bool $placeholderApplied = false;

    public function placeholder(string $value): static
    {
        $this->placeholder = $value;
        return $this;
    }

    public function hasPlaceholder(): bool
    {
        return $this->placeholder !== null;
    }

    public function wasPlaceholderApplied(): bool
    {
        return $this->placeholderApplied;
    }

    protected function applyPlaceholder($value)
    {
        $this->placeholderApplied = false;

        if (
            ($value === null || $value === '') &&
            $this->placeholder !== null
        ) {
            $this->placeholderApplied = true;
            return $this->placeholder;
        }

        return $value;
    }
}
