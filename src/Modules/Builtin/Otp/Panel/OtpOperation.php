<?php

namespace Upsoftware\Svarium\Modules\Builtin\Otp\Panel;

use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\Text;

class OtpOperation extends Operation
{
    public static string|array $panels = '*';

    public static function uri(): string
    {
        return 'otp';
    }

    public static function methods(): array
    {
        return ['GET'];
    }

    public function title(): string
    {
        return (string) svarium_label('modules.otp.plural', __('OTP'));
    }

    public function schema(PanelContext $context): array
    {
        return [
            Block::make()
                ->appearance('space-y-2')
                ->children([
                    Text::make((string) svarium_label('modules.otp.plural', __('OTP')))
                        ->headline('h2')
                        ->fontWeight('semibold'),
                    Text::make(__('Konfiguracja i status metod OTP użytkownika.')),
                ]),
        ];
    }
}

