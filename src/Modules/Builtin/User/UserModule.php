<?php

namespace Upsoftware\Svarium\Modules\Builtin\User;

use Upsoftware\Svarium\Menu\MenuItem;
use Upsoftware\Svarium\Modules\Builtin\User\Panel\UserResource;
use Upsoftware\Svarium\Modules\Module;

class UserModule extends Module
{
    public function name(): string
    {
        return 'User';
    }

    public function menu(): array
    {
        return [
            MenuItem::make(svarium_label('modules.user.plural', __('Users')))
                ->icon('lucide:users')
                ->url('/'.ltrim(module_route('user'), '/'))
                ->order(90),
        ];
    }

    public function register(): void
    {
        $this->registerResource(UserResource::class);
    }
}
