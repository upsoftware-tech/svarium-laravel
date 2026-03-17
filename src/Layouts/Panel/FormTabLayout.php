<?php

namespace Upsoftware\Svarium\Layouts\Panel;

use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Resource\ResourceFormTab;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Components\Button;
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
        $card = $this->record instanceof Model
            ? ($this->tab->resolveCard($this->context, $this->record) ?? true)
            : ($this->tab->resolveCard($this->context) ?? true);
        $widthContent = $this->record instanceof Model
            ? $this->tab->resolveWidthContent($this->context, $this->record)
            : $this->tab->resolveWidthContent($this->context);
        $paddingContent = $this->record instanceof Model
            ? $this->tab->resolvePaddingContent($this->context, $this->record)
            : $this->tab->resolvePaddingContent($this->context);
        $colSpan = $this->record instanceof Model
            ? $this->tab->resolveColSpan($this->context, $this->record)
            : $this->tab->resolveColSpan($this->context);
        $grid = $this->record instanceof Model
            ? $this->tab->resolveGrid($this->context, $this->record)
            : $this->tab->resolveGrid($this->context);
        $contentCols = $this->record instanceof Model
            ? $this->tab->resolveContentCols($this->context, $this->record)
            : $this->tab->resolveContentCols($this->context);

        return (new FormTabCardLayout(
            content: $this->content,
            card: $card,
            title: $title,
            subtitle: $subtitle,
            icon: $icon,
            action: $action,
            cols: $colSpan ?? 'full',
            gridColumns: $grid ?? 12,
            contentCols: $contentCols ?? 12,
            contentPadding: $paddingContent ?? '4',
            contentWidth: $widthContent,
        ))->build();
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
