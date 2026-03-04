<?php

namespace Upsoftware\Svarium\Layouts\Auth;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\ColorMode;
use Upsoftware\Svarium\UI\Components\Flex;
use Upsoftware\Svarium\UI\Components\LocaleSelect;
use Upsoftware\Svarium\UI\Contracts\LayoutSection;

class HeaderLayout implements LayoutSection
{
    public function build(): Component|array|null
    {
        return [
            Flex::make()
                ->gap(2)
                ->justify('end')
                ->items('center')
                ->padding(4)
                ->children([
                    Block::make()
                        ->width('240px')
                        ->children([
                            LocaleSelect::make(),
                        ]),
                    ColorMode::make(),
                ]),
        ];
    }
}
