<?php

namespace Upsoftware\Svarium\Layouts\Panel;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Components\Card;
use Upsoftware\Svarium\UI\Components\Grid;
use Upsoftware\Svarium\UI\Contracts\LayoutSection;

class FormTabCardLayout implements LayoutSection
{
    public function __construct(
        protected array $content,
        protected bool $card = true,
        protected ?string $title = null,
        protected ?string $subtitle = null,
        protected ?string $icon = null,
        protected array $action = [],
        protected string|int $cols = 'full',
        protected int $gridColumns = 12,
        protected string|int $contentCols = 12,
        protected string|int|float $contentPadding = '4',
        protected string|int|float|null $contentWidth = null,
    ) {}

    public function build(): Component|array|null
    {
        if (! $this->card) {
            return $this->content;
        }

        $card = Card::make()
            ->colSpan($this->cols, $this->gridColumns)
            ->variant('form-tab')
            ->contentPadding($this->contentPadding)
            ->children([
                Grid::make()
                    ->cols($this->contentCols)
                    ->gap(4)
                    ->children($this->content),
            ]);

        if ($this->contentWidth !== null) {
            $card->contentWidth($this->contentWidth);
        }

        if (is_string($this->title) && trim($this->title) !== '') {
            $card->title($this->title);
        }

        if (is_string($this->subtitle) && trim($this->subtitle) !== '') {
            $card->description($this->subtitle);
        }

        if (is_string($this->icon) && trim($this->icon) !== '') {
            $card->icon($this->icon);
        }

        if (is_array($this->action) && $this->action !== []) {
            $card->headerComponents($this->action);
        }

        return $card;
    }
}
