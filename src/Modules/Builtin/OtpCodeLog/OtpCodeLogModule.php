<?php

namespace Upsoftware\Svarium\Modules\Builtin\OtpCodeLog;

use Upsoftware\Svarium\Modules\Builtin\OtpCodeLog\Panel\OtpCodeLogResource;
use Upsoftware\Svarium\Modules\Builtin\Support\ResolvesBuiltinMenuPlacement;
use Upsoftware\Svarium\Modules\Module;

class OtpCodeLogModule extends Module
{
    use ResolvesBuiltinMenuPlacement;

    public function name(): string
    {
        return 'otp_code_logs';
    }

    public function menu(): array
    {
        return $this->buildBuiltinMenuItems(
            'otp_code_logs',
            (string) svarium_label('modules.otp_code_logs.plural', __('OTP code logs')),
            panel_href('system/otp-code-logs'),
            [
                'target' => 'main_menu',
                'path' => [__('svarium::messages.System setting')],
                'path_ids' => ['system'],
                'order' => 55,
                'icon' => '',
                'group_icon' => 'lucide:sliders',
            ]
        );
    }

    public function register(): void
    {
        $this->registerResource(OtpCodeLogResource::class);
    }
}
