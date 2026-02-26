<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasSize;
use Upsoftware\Svarium\UI\Concerns\Props\HasVariant;

class ColumnVisibility extends Component
{
    use HasVariant, HasSize;

    protected array $columns = [];
    protected ?string $label = null;

    public static function make(?string $label = null): static
    {
        $instance = new static;
        $instance->label($label ?: __('View'));

        return $instance;
    }

    public function columns(array $columns): static
    {
        $this->columns = $columns;
        $this->prop('columns', $columns);

        return $this;
    }

    public function label(?string $label): static
    {
        $this->label = $label;
        $this->prop('label', $label);
        return $this;
    }

    public function toArray(): array
    {
        $parent = parent::toArray();

        $props = array_merge($parent['props'] ?? [], $this->props);

        return array_merge($parent, ['props' => $props]);
    }
}
