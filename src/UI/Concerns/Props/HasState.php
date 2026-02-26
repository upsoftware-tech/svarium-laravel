<?php

namespace Upsoftware\Svarium\UI\Concerns\Props;

trait HasState
{
    protected $stateCallback = null;

    public function state(callable $callback): static
    {
        $this->stateCallback = $callback;
        return $this;
    }

    protected function resolveRawState(array $row)
    {
        $value = data_get($row, $this->key);

        if ($this->stateCallback) {
            $value = call_user_func($this->stateCallback, $row);
        }

        return $value;
    }
}
