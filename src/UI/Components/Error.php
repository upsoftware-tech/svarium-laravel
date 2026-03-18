<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class Error extends Component
{
    public static function make(?string $name = null): static
    {
        $instance = parent::make();

        if (is_string($name) && trim($name) !== '') {
            $instance->name($name);
        }

        return $instance;
    }

    public function name(string $name): static
    {
        return $this->prop('name', trim($name));
    }

    public function errors(array|string|null $errors): static
    {
        return $this->prop('errors', $errors);
    }

    public function message(string $message): static
    {
        return $this->errors($message);
    }

    public function toArray(): array
    {
        $array = parent::toArray();
        $array['type'] = 'FormError';

        return $array;
    }
}

