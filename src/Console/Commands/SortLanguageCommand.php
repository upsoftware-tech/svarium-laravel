<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Console\Command;
use Upsoftware\Svarium\Traits\HasSortCommand;

class SortLanguageCommand extends Command
{
    use HasSortCommand;

    protected $signature = 'svarium:lang.sort';

    protected $description = 'Sortowanie języków';

    protected $settingModel;

    public function __construct()
    {
        parent::__construct();
        $this->settingModel = config('svarium.models.setting', \Upsoftware\Svarium\Models\Setting::class);
    }

    public function handle()
    {
        $locales = $this->settingModel::getSettingGlobal('locales');
        $locales = $this->sequentialSort($locales);

        $this->settingModel::setSettingGlobal('locales', $locales, true);
    }
}
