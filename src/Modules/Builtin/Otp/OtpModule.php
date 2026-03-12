<?php

namespace Upsoftware\Svarium\Modules\Builtin\Otp;

use Upsoftware\Svarium\Modules\Builtin\Support\ResolvesBuiltinMenuPlacement;
use Upsoftware\Svarium\Modules\Module;

class OtpModule extends Module
{
    use ResolvesBuiltinMenuPlacement;

    public function name(): string
    {
        return 'otp';
    }

    public function menu(): array
    {
        return $this->buildBuiltinMenuItems(
            'otp',
            (string) svarium_label('modules.otp.plural', __('OTP')),
            panel_href('otp'),
            [
                'target' => 'sidebar_user',
                'order' => 20,
                'icon' => 'lucide:shield-check',
            ]
        );
    }
}
