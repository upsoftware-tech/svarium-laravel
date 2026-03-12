<?php

namespace Upsoftware\Svarium\Modules\Builtin\Subscriptions;

use Upsoftware\Svarium\Modules\Builtin\Support\ResolvesBuiltinMenuPlacement;
use Upsoftware\Svarium\Modules\Module;

class SubscriptionsModule extends Module
{
    use ResolvesBuiltinMenuPlacement;

    public function name(): string
    {
        return 'subscriptions';
    }

    public function menu(): array
    {
        return $this->buildBuiltinMenuItems(
            'subscriptions',
            (string) svarium_label('modules.subscriptions.plural', __('svarium::messages.Subscriptions')),
            panel_href('system/subscriptions'),
            [
                'target' => 'main_menu',
                'path' => [__('svarium::messages.System setting')],
                'path_ids' => ['system'],
                'order' => 80,
                'icon' => '',
                'group_icon' => 'lucide:sliders',
            ]
        );
    }

    public function register(): void
    {
        // Operations are auto-discovered from the module Panel directory.
    }
}
