<?php

namespace Upsoftware\Svarium\Modules\Builtin\Translation;

use Upsoftware\Svarium\Modules\Builtin\Support\ResolvesBuiltinMenuPlacement;
use Upsoftware\Svarium\Modules\Builtin\Translation\Panel\TranslationKeyResource;
use Upsoftware\Svarium\Modules\Builtin\Translation\Panel\TranslationKeysetResource;
use Upsoftware\Svarium\Modules\Builtin\Translation\Panel\TranslationOrderResource;
use Upsoftware\Svarium\Modules\Module;

class TranslationModule extends Module
{
    use ResolvesBuiltinMenuPlacement;

    public function name(): string
    {
        return 'translation';
    }

    public function menu(): array
    {
        return $this->buildBuiltinMenuItems(
            'translation',
            (string) svarium_label('modules.translation.plural', __('Tłumaczenia')),
            panel_href('system/translations'),
            [
                'target' => 'main_menu',
                'path' => [__('svarium::messages.System setting')],
                'path_ids' => ['system'],
                'order' => 70,
                'icon' => '',
                'group_icon' => 'lucide:sliders',
            ]
        );
    }

    public function register(): void
    {
        $this->registerResource(TranslationKeysetResource::class);
        $this->registerResource(TranslationKeyResource::class);
        $this->registerResource(TranslationOrderResource::class);
    }
}
