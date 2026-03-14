<?php

namespace Upsoftware\Svarium\Modules\Builtin\Translation\Panel;

use Illuminate\Database\Eloquent\Model;
use Throwable;
use Upsoftware\Svarium\Models\TranslationKey;
use Upsoftware\Svarium\Models\TranslationKeyset;
use Upsoftware\Svarium\Models\TranslationOrder;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\Flex;
use Upsoftware\Svarium\UI\Components\Link;
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
        $keysetsCount = $this->safeCount($this->keysetModelClass());
        $keysCount = $this->safeCount($this->keyModelClass());
        $ordersCount = $this->safeCount($this->orderModelClass());

        return [
            Block::make()
                ->appearance('space-y-4')
                ->children([
                    Text::make((string) svarium_label('modules.translation.plural', __('Tłumaczenia')))
                        ->headline('h2')
                        ->fontWeight('semibold'),
                    Text::make(__('Zarządzanie zestawami kluczy, tłumaczeniami i zleceniami tłumaczeń.')),
                    Flex::make()
                        ->direction('col')
                        ->gap(3)
                        ->children([
                            $this->resourceCard(
                                __('Zestawy kluczy'),
                                __('Zakresy tłumaczeń: globalne, modułowe i własne.'),
                                $keysetsCount,
                                'system/translations/keysets'
                            ),
                            $this->resourceCard(
                                __('Klucze tłumaczeń'),
                                __('Definicje kluczy i wartości per język.'),
                                $keysCount,
                                'system/translations/keys'
                            ),
                            $this->resourceCard(
                                __('Zlecenia tłumaczeń'),
                                __('Praca zespołowa nad tłumaczeniami i review.'),
                                $ordersCount,
                                'system/translations/orders'
                            ),
                        ]),
                ]),
        ];
    }

    protected function resourceCard(
        string $title,
        string $description,
        ?int $count,
        string $href
    ): Block {
        $countLabel = $count !== null
            ? (string) $count
            : __('Brak danych');

        return Block::make()
            ->appearance('border rounded-lg p-4 space-y-2')
            ->children([
                Flex::make()
                    ->justify('between')
                    ->items('center')
                    ->children([
                        Text::make($title)->fontWeight('semibold'),
                        Text::make($countLabel)->appearance('text-xs text-slate-500'),
                    ]),
                Text::make($description)->appearance('text-sm text-slate-600 dark:text-slate-400'),
                Link::make(__('Otwórz'))
                    ->panelHref($href)
                    ->appearance('text-sm underline'),
            ]);
    }

    protected function safeCount(string $modelClass): ?int
    {
        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return null;
        }

        try {
            return (int) $modelClass::query()->count();
        } catch (Throwable) {
            return null;
        }
    }

    protected function keysetModelClass(): string
    {
        $configured = config('upsoftware.models.translation_keyset');

        if (is_string($configured) && $configured !== '' && class_exists($configured)) {
            return $configured;
        }

        return TranslationKeyset::class;
    }

    protected function keyModelClass(): string
    {
        $configured = config('upsoftware.models.translation_key');

        if (is_string($configured) && $configured !== '' && class_exists($configured)) {
            return $configured;
        }

        return TranslationKey::class;
    }

    protected function orderModelClass(): string
    {
        $configured = config('upsoftware.models.translation_order');

        if (is_string($configured) && $configured !== '' && class_exists($configured)) {
            return $configured;
        }

        return TranslationOrder::class;
    }
}
