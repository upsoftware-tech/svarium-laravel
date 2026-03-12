<?php

namespace Upsoftware\Svarium\Modules\Builtin\SystemMailTemplate\Panel;

use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Services\Notifications\NotificationCatalogService;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Components\Badge;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\Flex;
use Upsoftware\Svarium\UI\Components\Link;
use Upsoftware\Svarium\UI\Components\Text;

class SystemMailTemplateOperation extends Operation
{
    public static string|array $panels = '*';

    public static function uri(): string
    {
        return 'system/mail-templates';
    }

    public static function methods(): array
    {
        return ['GET'];
    }

    public function title(): string
    {
        return (string) svarium_label('modules.system_mail_templates.plural', __('Szablony mailowe'));
    }

    public function schema(PanelContext $context): array
    {
        $notifications = app(NotificationCatalogService::class)->all();

        if ($notifications === []) {
            return [
                Block::make()
                    ->appearance('space-y-2')
                    ->children([
                        Text::make((string) svarium_label('modules.system_mail_templates.plural', __('Szablony mailowe')))
                            ->headline('h2')
                            ->fontWeight('semibold'),
                        Text::make(__('Brak wykrytych klas Notification do edycji szablonów.')),
                    ]),
            ];
        }

        return [
            Block::make()
                ->appearance('space-y-4')
                ->children([
                    Text::make((string) svarium_label('modules.system_mail_templates.plural', __('Szablony mailowe')))
                        ->headline('h2')
                        ->fontWeight('semibold'),
                    Text::make(__('Lista wykrytych klas Notification do konfiguracji treści e-mail.')),
                    Block::make()
                        ->appearance('space-y-3')
                        ->children($this->notificationRows($notifications)),
                ]),
        ];
    }

    /**
     * @param array<int, array{id:string,key:string,label:string,class:string,source:string,file:string,placeholders:array<int,string>}> $notifications
     * @return array<int, Component>
     */
    protected function notificationRows(array $notifications): array
    {
        $rows = [];

        foreach ($notifications as $notification) {
            $rows[] = Block::make()
                ->appearance('border rounded-lg p-4 space-y-2')
                ->children([
                    Flex::make()
                        ->justify('between')
                        ->items('center')
                        ->gap(2)
                        ->children([
                            Text::make((string) ($notification['label'] ?? ''))
                                ->fontWeight('semibold'),
                            Badge::make((string) ($notification['source'] ?? ''))
                                ->variant('secondary'),
                        ]),
                    Text::make((string) ($notification['class'] ?? ''))
                        ->appearance('text-xs text-slate-500'),
                    Text::make('template_key: '.(string) ($notification['key'] ?? ''))
                        ->appearance('text-xs text-slate-500'),
                    Link::make(__('Edytuj szablon'))
                        ->panelHref('system/mail-templates/'.(string) ($notification['id'] ?? '').'/edit')
                        ->appearance('text-sm underline'),
                ]);
        }

        return $rows;
    }
}
