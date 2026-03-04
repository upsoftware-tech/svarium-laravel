<?php

namespace Upsoftware\Svarium\UI\Components;

use InvalidArgumentException;
use Upsoftware\Svarium\UI\Component;

class Logo extends Component
{
    protected const ALLOWED_VARIANTS = ['default', 'small'];
    protected const ALLOWED_MODES = ['auto', 'light', 'dark'];

    public static function make(?string $variant = null): static
    {
        $instance = parent::make();

        if ($variant !== null) {
            $instance->variant($variant);
        }

        return $instance;
    }

    public function variant(string $variant): static
    {
        $normalized = strtolower(trim($variant));

        if (! in_array($normalized, self::ALLOWED_VARIANTS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Logo variant must be one of: %s.',
                implode(', ', self::ALLOWED_VARIANTS)
            ));
        }

        return $this->prop('variant', $normalized);
    }

    public function mode(string $mode): static
    {
        $normalized = strtolower(trim($mode));

        if (! in_array($normalized, self::ALLOWED_MODES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Logo mode must be one of: %s.',
                implode(', ', self::ALLOWED_MODES)
            ));
        }

        return $this->prop('mode', $normalized);
    }

    public function auto(): static
    {
        return $this->mode('auto');
    }

    public function light(): static
    {
        return $this->mode('light');
    }

    public function dark(): static
    {
        return $this->mode('dark');
    }

    public function small(): static
    {
        return $this->variant('small');
    }

    public function standard(): static
    {
        return $this->variant('default');
    }

    public function alt(string $alt): static
    {
        return $this->prop('alt', $alt);
    }

    public function title(string $title): static
    {
        return $this->prop('title', $title);
    }

    public function __call(string $method, array $arguments): mixed
    {
        if ($method === 'default') {
            return $this->standard();
        }

        return parent::__call($method, $arguments);
    }
}

