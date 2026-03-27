<?php

namespace Upsoftware\Svarium\Layouts;

use Upsoftware\Svarium\Layouts\Panel\HeaderLayout;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\Body;
use Upsoftware\Svarium\UI\Components\Container;
use Upsoftware\Svarium\UI\Components\Flex;
use Upsoftware\Svarium\UI\Components\PanelNavigation;
use Upsoftware\Svarium\UI\Components\ScrollArea;
use Upsoftware\Svarium\UI\Components\Sidebar;
use Upsoftware\Svarium\UI\Components\SidebarHeader;
use Upsoftware\Svarium\UI\Components\SidebarUser;
use Upsoftware\Svarium\UI\Layouts\PanelLayout as BasePanelLayout;

class PanelLayout extends BasePanelLayout
{
    public function defineHeader(): string
    {
        return HeaderLayout::class;
    }

    public function defineFooter(): array|string
    {
        return [];
    }

    protected function define(): void
    {
        $this->prop('layout', 'panel');
        // Mark as definition layout so it is flattened into root layout (CleanLayout).
        $this->prop('__definition_layout', true);

        $this->body(function (): array {
            $bodyContent = Body::make();

            if ((bool) $this->getProp('containerEnabled', true)) {
                $bodyContent = Container::make()
                    ->fluid((bool) $this->getProp('containerFluid', false))
                    ->position((string) $this->getProp('containerPosition', 'center'))
                    ->children([
                        $bodyContent,
                    ]);
            }

            return [
                Flex::make()
                    ->height('screen')
                    ->children([
                        Sidebar::make()
                            ->header(SidebarHeader::make())
                            ->children(PanelNavigation::make())
                            ->footer(SidebarUser::make()),
                        Flex::make()
                            ->flex(1)
                            ->class('min-w-0')
                            ->padding('2 s-0')
                            ->children([
                                Flex::make()
                                    ->flex(1)
                                    ->class('min-w-0')
                                    ->direction('col')
                                    ->bg('white', 'slate-900')
                                    ->rounded('xl')
                                    ->border()
                                    ->header($this->defineHeader())
                                    ->footer($this->defineFooter())
                                    ->children([
                                        Block::make()->flex(1)
                                            ->children([
                                                ScrollArea::make()
                                                    ->height('calc(100vh-70px)')
                                                    ->children([
                                                        Block::make()
                                                            ->padding(6)
                                                            ->children([
                                                                $bodyContent,
                                                            ]),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ];
        });
    }
}
