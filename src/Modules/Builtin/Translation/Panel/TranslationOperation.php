<?php

namespace Upsoftware\Svarium\Modules\Builtin\Translation\Panel;

use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\Text;

class TranslationOperation extends Operation
{
    public static string|array $panels = '*';

    public static function uri(): string
    {
        return 'system/translations';
    }

    public static function methods(): array
    {
        return ['GET'];
    }

    public function title(): string
    {
        return (string) svarium_label('modules.translation.plural', __('Tłumaczenia'));
    }

    public function schema(PanelContext $context): array
    {
        return [
            Block::make()
                ->appearance('space-y-2')
                ->children([
                    Text::make((string) svarium_label('modules.translation.plural', __('Tłumaczenia')))
                        ->headline('h2')
                        ->fontWeight('semibold'),
                    Text::make(__('Zarządzanie słownikami tłumaczeń i kluczami językowymi.')),
                ]),
        ];
    }
}

