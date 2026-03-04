<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class ButtonGroupSocials extends Component
{
    public static function make(?string $name = null): static
    {
        return parent::make($name);
    }

    public function socials(array $socials): static
    {
        return $this->prop('socials', $socials);
    }

    public function minimal(bool $enabled = true): static
    {
        return $this->prop('minimal', $enabled);
    }

    public function cols(int $cols): static
    {
        return $this->prop('cols', max(1, min(3, $cols)));
    }

    public function onlySocialName(bool $enabled = true): static
    {
        return $this->prop('onlySocialName', $enabled);
    }

    public function redirectRoute(string $routeName): static
    {
        return $this->prop('redirectRoute', trim($routeName));
    }
}
