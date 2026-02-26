<?php

namespace Upsoftware\Svarium\UI\Concerns\Props;

trait HasDefault
{
    protected $default = null;

    public function default($value): static
    {
        $this->default = $value;
        return $this;
    }

    protected function applyDefault($value)
    {
        if ($value === null && $this->default !== null) {
            return $this->default;
        }

        return $value;
    }
}
