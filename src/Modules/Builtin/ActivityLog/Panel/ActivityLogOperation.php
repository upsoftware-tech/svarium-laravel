<?php

namespace Upsoftware\Svarium\Modules\Builtin\ActivityLog\Panel;

use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\Text;

class ActivityLogOperation extends Operation
{
    public static string|array $panels = '*';

    public static function uri(): string
    {
        return 'activity-log';
    }

    public static function methods(): array
    {
        return ['GET'];
    }

    public function title(): string
    {
        return (string) svarium_label('modules.activity_log.plural', __('Dziennik aktywności'));
    }

    public function schema(PanelContext $context): array
    {
        return [
            Block::make()
                ->appearance('space-y-2')
                ->children([
                    Text::make((string) svarium_label('modules.activity_log.plural', __('Dziennik aktywności')))
                        ->headline('h2')
                        ->fontWeight('semibold'),
                    Text::make(__('Podgląd historii działań użytkownika i systemu.')),
                ]),
        ];
    }
}

