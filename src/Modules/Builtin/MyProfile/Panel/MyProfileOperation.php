<?php

namespace Upsoftware\Svarium\Modules\Builtin\MyProfile\Panel;

use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\Text;

class MyProfileOperation extends Operation
{
    public static string|array $panels = '*';

    public static function uri(): string
    {
        return 'my-profile';
    }

    public static function methods(): array
    {
        return ['GET'];
    }

    public function title(): string
    {
        return (string) svarium_label('modules.my_profile.plural', __('Mój profil'));
    }

    public function schema(PanelContext $context): array
    {
        return [
            Block::make()
                ->appearance('space-y-2')
                ->children([
                    Text::make((string) svarium_label('modules.my_profile.plural', __('Mój profil')))
                        ->headline('h2')
                        ->fontWeight('semibold'),
                    Text::make(__('Zarządzanie profilem użytkownika.')),
                ]),
        ];
    }
}

