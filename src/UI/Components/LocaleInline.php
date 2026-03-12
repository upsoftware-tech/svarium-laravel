<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class LocaleInline extends Component
{
    public static function make(?string $name = null): static
    {
        return parent::make($name);
    }

    public function multiple(bool $enabled = true): static
    {
        return $this->prop('multiple', $enabled);
    }

    public function showIcon(bool $enabled = true): static
    {
        return $this->prop('showIcon', $enabled);
    }

    public function showLabel(bool $enabled = true): static
    {
        return $this->prop('showLabel', $enabled);
    }

    public function languageSelector(bool $enabled = true): static
    {
        return $this->prop('languageSelector', $enabled);
    }
}
