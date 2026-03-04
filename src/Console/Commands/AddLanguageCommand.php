<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Console\Command;
use LaravelLang\Locales\Facades\Locales;
use function Laravel\Prompts\select;

class AddLanguageCommand extends Command
{
    protected $signature = 'svarium:lang.add {lang?*}';

    protected $description = 'Dodaj nowy język';

    public function handle()
    {
        $settingModel = config('svarium.models.setting', \Upsoftware\Svarium\Models\Setting::class);
        $languages = (array) $this->argument('lang');

        $locales = [];
        foreach (Locales::available() as $locale) {
            $locales[$locale->code] = "{$locale->localized} ({$locale->native})";
        }
        asort($locales);

        if (empty($languages)) {
            $selected = select('Wybierz język:', $locales);
            if (!empty($selected)) {
                $languages[] = $selected;
            }
        }

        $languages = array_values(array_unique(array_filter(array_map('trim', $languages))));
        if (empty($languages)) {
            $this->warn('Nie podano kodu języka.');
            return 1;
        }

        $localesCollection = collect(Locales::available());

        foreach ($languages as $lang) {
            $locale_data = json_decode(json_encode($localesCollection->firstWhere('code', $lang)), true);

            if (!$locale_data) {
                $this->error("Język nie został znaleziony: $lang");
                continue;
            }

            if (!empty($locale_data['regional']) && strlen($locale_data['regional']) === 5) {
                $locale_data['flag'] = strtolower(explode('_', $locale_data['regional'])[1]);
            }

            $this->info("Dodawanie języka: $lang ...");
            passthru("php artisan lang:add $lang");
            $this->newLine();

            passthru("php artisan svarium:lang.prepare $lang");
            passthru("php artisan svarium:lang.merge $lang");

            $settingModel::setSettingGlobal('locales', [$lang => $locale_data]);
        }

        return 0;
    }
}
