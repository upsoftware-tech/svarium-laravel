<?php

namespace Upsoftware\Svarium\UI\Components\Search;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasPlaceholder;
use Upsoftware\Svarium\UI\Concerns\Props\HasWidth;

class InputSearch extends Component
{
    use HasPlaceholder, HasWidth;

    protected ?string $name = null;

    public function __construct(?string $name = null)
    {
        $this->name = $name;

        if (is_string($name) && trim($name) !== '') {
            $this->prop('name', trim($name));
        }
    }

    public static function make(?string $name = null): static
    {
        return new static($name);
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        $parent = parent::toArray();
        $props = $parent['props'] ?? [];

        return array_merge($parent, ['props' => $props]);
    }
}
