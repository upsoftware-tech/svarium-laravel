<?php

namespace Upsoftware\Svarium\Modules\Builtin\MenuManager;

use Upsoftware\Svarium\Modules\Builtin\Support\ResolvesBuiltinMenuPlacement;
use Upsoftware\Svarium\Modules\Module;

class MenuManagerModule extends Module
{
    use ResolvesBuiltinMenuPlacement;

    public function name(): string
    {
        return 'menu_manager';
    }

    public function menu(): array
    {
        return $this->buildBuiltinMenuItems(
            'menu_manager',
            (string) svarium_label('modules.menu_manager.plural', __('Menu manager')),
            panel_href('system/menu-manager'),
            [
                'target' => 'main_menu',
                'path' => [__('svarium::messages.System setting')],
                'path_ids' => ['system'],
                'order' => 65,
                'icon' => 'lucide:menu-square',
                'group_icon' => 'lucide:sliders',
            ]
        );
    }
}
