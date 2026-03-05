<?php

namespace Upsoftware\Svarium\Layouts;

use Upsoftware\Svarium\Layouts\Auth\HeaderLayout;
use Upsoftware\Svarium\UI\Components\Body;
use Upsoftware\Svarium\UI\Components\Flex;
use Upsoftware\Svarium\UI\Components\Logo;
use Upsoftware\Svarium\UI\Components\Text;
use Upsoftware\Svarium\UI\Layouts\PanelLayout;

class AuthLayout extends PanelLayout
{
    protected Text $authTitleComponent;
    protected Text $authSubtitleComponent;

    public function defineHeader(): string
    {
        return HeaderLayout::class;
    }
    protected function define(): void
    {
        $this->prop('layout', 'auth');
        $this->authTitleComponent = Text::make($this->defaultTitle())
            ->headline('h2')
            ->appearance('text-2xl font-semibold');
        $this->authSubtitleComponent = Text::make($this->defaultSubtitle());

        $this->body([
            Flex::make()
                ->appearance('w-screen h-screen p-3')
                ->children([
                    Flex::make()
                        ->direction('col')
                        ->bg('white', 'slate-900')
                        ->rounded('xl')
                        ->appearance('border w-full h-full')
                        ->header($this->defineHeader())
                        ->children([
                            Flex::make()
                                ->justify('center')
                                ->items('center')
                                ->flex(1)
                                ->children([
                                    Flex::make()
                                        ->width('full')
                                        ->maxWidth('520px')
                                        ->items('start')
                                        ->gap(6)
                                        ->direction('col')
                                        ->children([
                                            Flex::make()
                                                ->width('full')
                                                ->justify('center')
                                                ->children([
                                                    Logo::make()->height('64px'),
                                                ]),
                                            Flex::make()
                                                ->gap(2)
                                                ->width('full')
                                                ->direction('col')
                                                ->children([
                                                    $this->authTitleComponent,
                                                    $this->authSubtitleComponent,
                                                ]),
                                            Body::make()->width('full'),
                                        ]),
                                ]),
                        ]),
                ]),
        ]);
    }

    protected function defaultTitle(): string
    {
        return __('Welcome back!');
    }

    protected function defaultSubtitle(): string
    {
        return __('Enter your email address and password');
    }

    public function toArray(): array
    {
        $title = trim((string) $this->getProp('title', $this->defaultTitle()));
        $subtitle = trim((string) $this->getProp('subtitle', $this->defaultSubtitle()));

        if ($title === '') {
            $this->authTitleComponent->phpIf(false);
        } else {
            $this->authTitleComponent->text(__($title))->phpIf(true);
        }

        if ($subtitle === '') {
            $this->authSubtitleComponent->phpIf(false);
        } else {
            $this->authSubtitleComponent->text(__($subtitle))->phpIf(true);
        }

        return parent::toArray();
    }
}
