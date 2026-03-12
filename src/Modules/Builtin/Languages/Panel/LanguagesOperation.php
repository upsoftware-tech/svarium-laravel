<?php

namespace Upsoftware\Svarium\Modules\Builtin\Languages\Panel;

use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\UI\Components\Badge;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\Flex;
use Upsoftware\Svarium\UI\Components\Text;

class LanguagesOperation extends Operation
{
    public static string|array $panels = '*';

    public static function uri(): string
    {
        return 'system/languages';
    }

    public static function methods(): array
    {
        return ['GET'];
    }

    public function title(): string
    {
        return (string) svarium_label('modules.languages.plural', __('Języki'));
    }

    public function schema(PanelContext $context): array
    {
        $locales = $this->resolveInstalledLocales();

        $localeBadges = [];
        foreach ($locales as $locale) {
            $localeBadges[] = Badge::make(strtoupper($locale))->variant('secondary');
        }

        return [
            Block::make()
                ->appearance('space-y-2')
                ->children([
                    Text::make((string) svarium_label('modules.languages.plural', __('Języki')))
                        ->headline('h2')
                        ->fontWeight('semibold'),
                    Text::make(__('Zarządzanie językami systemu i lokalizacją interfejsu.')),
                    Flex::make()
                        ->gap(2)
                        ->appearance('flex-wrap')
                        ->children($localeBadges !== [] ? $localeBadges : [
                            Text::make(__('Brak skonfigurowanych języków.')),
                        ]),
                ]),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function resolveInstalledLocales(): array
    {
        $settingModel = (string) config('upsoftware.models.setting', \Upsoftware\Svarium\Models\Setting::class);

        if (! class_exists($settingModel) || ! method_exists($settingModel, 'getSettingGlobal')) {
            return [];
        }

        $locales = (array) $settingModel::getSettingGlobal('locales', []);

        $resolved = array_values(array_filter(array_map(static function (mixed $payload, mixed $key): string {
            if (is_string($key) && trim($key) !== '') {
                return strtolower(trim($key));
            }

            if (is_array($payload)) {
                $code = trim((string) ($payload['code'] ?? $payload['locale'] ?? $payload['value'] ?? ''));
                if ($code !== '') {
                    return strtolower($code);
                }
            }

            return '';
        }, $locales, array_keys($locales)), static fn (string $locale): bool => $locale !== ''));

        sort($resolved);

        return $resolved;
    }
}

