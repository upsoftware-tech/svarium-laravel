<?php

namespace Upsoftware\Svarium\UI\Components;

use InvalidArgumentException;
use Upsoftware\Svarium\UI\Component;

class ColorMode extends Component
{
    protected const ALLOWED_VARIANTS = [
        'default',
        'switch',
        'dropdown',
        'group',
    ];

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
        $normalizedVariant = strtolower(trim($variant));

        if ($normalizedVariant === '') {
            $normalizedVariant = 'default';
        }

        if (! in_array($normalizedVariant, self::ALLOWED_VARIANTS, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'ColorMode variant must be one of: %s.',
                    implode(', ', self::ALLOWED_VARIANTS)
                )
            );
        }

        return $this->prop('variant', $normalizedVariant);
    }
}

