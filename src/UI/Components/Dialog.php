<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class Dialog extends Component
{
    public static function make(?string $name = null): static
    {
        return (new static)
            ->title('Dialog')
            ->cancel('Cancel')
            ->ok('Save')
            ->maxWidth(600);
    }

    public function title(string $title): static
    {
        return $this->prop('title', $title);
    }

    public function description(?string $description): static
    {
        return $this->prop('description', $description);
    }

    public function cancel(string $label): static
    {
        return $this->prop('cancel', $label);
    }

    public function ok(string $label): static
    {
        return $this->prop('ok', $label);
    }

    public function maxWidth(int $width): static
    {
        return $this->prop('maxWidth', $width);
    }
}
