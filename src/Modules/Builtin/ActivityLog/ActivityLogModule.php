<?php

namespace Upsoftware\Svarium\Modules\Builtin\ActivityLog;

use Upsoftware\Svarium\Modules\Builtin\Support\ResolvesBuiltinMenuPlacement;
use Upsoftware\Svarium\Modules\Module;

class ActivityLogModule extends Module
{
    use ResolvesBuiltinMenuPlacement;

    public function name(): string
    {
        return 'activity_log';
    }

    public function menu(): array
    {
        return $this->buildBuiltinMenuItems(
            'activity_log',
            (string) svarium_label('modules.activity_log.plural', __('Dziennik aktywności')),
            panel_href('activity-log'),
            [
                'target' => 'sidebar_user',
                'order' => 30,
                'icon' => 'lucide:history',
            ]
        );
    }
}
