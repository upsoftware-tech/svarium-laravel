<?php

namespace Upsoftware\Svarium\Layouts\Panel;

use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Resource\ResourceFormTab;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\Button;
use Upsoftware\Svarium\UI\Components\Flex;
use Upsoftware\Svarium\UI\Components\Grid;
use Upsoftware\Svarium\UI\Components\Icon;
use Upsoftware\Svarium\UI\Components\Text;
use Upsoftware\Svarium\UI\Contracts\LayoutSection;

class FormTabLayout implements LayoutSection
{
    public function __construct(
        protected ResourceFormTab $tab,
        protected PanelContext $context,
        protected array $content,
        protected ?Model $record = null,
    ) {}

    public function build(): Component|array|null
    {
        $icon = $this->tab->resolveIcon();
        $title = $this->tab->resolveTitle();
        $subtitle = $this->tab->resolveSubtitle();
        $action = $this->normalizeAction(
            $this->record instanceof Model
                ? $this->tab->resolveAction($this->context, $this->record)
                : $this->tab->resolveAction($this->context)
        );
        $hasHeader = $icon !== null || $title !== null || $subtitle !== null || $action !== [];

        return [
            Block::make()
                ->border()
                ->rounded('lg')
                ->padding('0.5')
                ->bg('slate-100')
                ->children([
                    Flex::make()
                        ->if($hasHeader)
                        ->padding('x-4 y-3')
                        ->justify('between')
                        ->items('center')
                        ->children([
                            Flex::make()
                                ->gap(3)
                                ->items('start')
                                ->children(array_values(array_filter([
                                    $icon !== null
                                        ? Icon::make($icon)
                                            ->fontSize('20')
                                            ->textColor('slate-600')
                                            ->padding('t-0.5')
                                        : null,
                                    Block::make()
                                        ->children(array_values(array_filter([
                                            $title !== null
                                                ? Text::make($title)
                                                    ->fontWeight('semibold')
                                                    ->fontSize('md')
                                                    ->paragraph()
                                                : null,
                                            $subtitle !== null
                                                ? Text::make($subtitle)
                                                    ->fontWeight('normal')
                                                    ->textColor('slate-500')
                                                    ->fontSize('xs')
                                                : null,
                                        ]))),
                                ]))),
                            Block::make()
                                ->if($action !== [])
                                ->children($action),
                        ]),
                    Block::make()
                        ->padding(4)
                        ->bg('white')
                        ->border()
                        ->rounded('md')
                        ->children(
                            Grid::make()
                                ->cols(12)
                                ->gap(4)
                                ->children($this->content)
                        ),
                ]),
        ];
    }

    protected function normalizeAction(mixed $action): array
    {
        if ($action instanceof Component) {
            return [$action];
        }

        if (is_string($action) && trim($action) !== '') {
            return [Button::make($action)];
        }

        return is_array($action) ? $action : [];
    }
}
