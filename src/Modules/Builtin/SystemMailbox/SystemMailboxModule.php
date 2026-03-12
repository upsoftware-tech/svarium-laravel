<?php

namespace Upsoftware\Svarium\Modules\Builtin\SystemMailbox;

use Upsoftware\Svarium\Modules\Builtin\Support\ResolvesBuiltinMenuPlacement;
use Upsoftware\Svarium\Modules\Builtin\SystemMailbox\Panel\SystemMailboxResource;
use Upsoftware\Svarium\Modules\Module;

class SystemMailboxModule extends Module
{
    use ResolvesBuiltinMenuPlacement;

    public function name(): string
    {
        return 'system_mailboxes';
    }

    public function menu(): array
    {
        return $this->buildBuiltinMenuItems(
            'system_mailboxes',
            (string) svarium_label('modules.system_mailboxes.plural', __('Skrzynki nadawcze')),
            panel_href('system/mailboxes'),
            [
                'target' => 'main_menu',
                'path' => [__('svarium::messages.System setting')],
                'path_ids' => ['system'],
                'order' => 40,
                'icon' => '',
                'group_icon' => 'lucide:sliders',
            ]
        );
    }

    public function register(): void
    {
        $this->registerResource(SystemMailboxResource::class);
    }
}
