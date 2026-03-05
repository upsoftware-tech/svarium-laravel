<?php

namespace Upsoftware\Svarium\Layouts\Panel;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\ColorMode;
use Upsoftware\Svarium\UI\Components\Flex;
use Upsoftware\Svarium\UI\Components\SidebarToggle;
use Upsoftware\Svarium\UI\Components\Title;
use Upsoftware\Svarium\UI\Contracts\LayoutSection;

class HeaderLayout implements LayoutSection
{
    public function build(): Component|array|null
    {
        return [
            Flex::make()
                ->gap(2)
                ->justify('between')
                ->items('center')
                ->padding('x-6')
                ->height('52px')
                ->border('b')
                ->children([
                    Flex::make()
                        ->gap(3)
                        ->items('center')
                        ->children([
                            SidebarToggle::make(),
                            Title::make(),
                        ]),
                    Block::make()
                        ->children([
                            ColorMode::make()
                                ->variant('switch'),
                        ]),
                ]),
        ];
    }
}
