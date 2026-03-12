<?php

namespace Upsoftware\Svarium\Modules\Builtin\Languages;

use Upsoftware\Svarium\Modules\Builtin\Support\ResolvesBuiltinMenuPlacement;
use Upsoftware\Svarium\Modules\Module;

class LanguagesModule extends Module
{
    use ResolvesBuiltinMenuPlacement;

    public function name(): string
    {
        return 'languages';
    }

    public function menu(): array
    {
        return $this->buildBuiltinMenuItems(
            'languages',
            (string) svarium_label('modules.languages.plural', __('Języki')),
            panel_href('system/languages'),
            [
                'target' => 'main_menu',
                'path' => [__('svarium::messages.System setting')],
                'path_ids' => ['system'],
                'order' => 60,
                'icon' => '',
                'group_icon' => 'lucide:sliders',
            ]
        );
    }
}
