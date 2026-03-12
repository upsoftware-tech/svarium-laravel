<?php

namespace Upsoftware\Svarium\Modules\Builtin\SystemMailTemplate;

use Upsoftware\Svarium\Modules\Builtin\Support\ResolvesBuiltinMenuPlacement;
use Upsoftware\Svarium\Modules\Module;

class SystemMailTemplateModule extends Module
{
    use ResolvesBuiltinMenuPlacement;

    public function name(): string
    {
        return 'system_mail_templates';
    }

    public function menu(): array
    {
        return $this->buildBuiltinMenuItems(
            'system_mail_templates',
            (string) svarium_label('modules.system_mail_templates.plural', __('Szablony mailowe')),
            panel_href('system/mail-templates'),
            [
                'target' => 'main_menu',
                'path' => [__('svarium::messages.System setting')],
                'path_ids' => ['system'],
                'order' => 50,
                'icon' => '',
                'group_icon' => 'lucide:sliders',
            ]
        );
    }
}
