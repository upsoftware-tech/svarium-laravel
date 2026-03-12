<?php

namespace Upsoftware\Svarium\Modules\Builtin\MyProfile;

use Upsoftware\Svarium\Modules\Builtin\Support\ResolvesBuiltinMenuPlacement;
use Upsoftware\Svarium\Modules\Module;

class MyProfileModule extends Module
{
    use ResolvesBuiltinMenuPlacement;

    public function name(): string
    {
        return 'my_profile';
    }

    public function menu(): array
    {
        return $this->buildBuiltinMenuItems(
            'my_profile',
            (string) svarium_label('modules.my_profile.plural', __('Mój profil')),
            panel_href('my-profile'),
            [
                'target' => 'sidebar_user',
                'order' => 10,
                'icon' => 'lucide:user-round',
            ]
        );
    }
}
