<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateLangJson extends Command
{

    protected $signature = 'svarium:lang.prepare {lang?}';

    protected $description = 'Konwertuje pliki tłumaczeń PHP (messages.php) na pliki JSON (pl.json)';

    public function handle()
    {
        $langPath = __DIR__.'/../../lang';

        $directories = File::directories($langPath);

        if (empty($directories)) {
            $this->warn("Nie znaleziono folderów językowych w: $langPath");
            return;
        }

        foreach ($directories as $dir) {
            $locale = basename($dir);
            $this->info("Przetwarzanie języka: $locale");

            $translations = [];

            $files = File::files($dir);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $content = include $file->getPathname();

                if (!is_array($content)) {
                    continue;
                }

                $translations = array_replace_recursive($translations, $content);
            }

            $jsonFile = $langPath . "/$locale.json";
            $existingJson = [];

            if (File::exists($jsonFile)) {
                $existingJson = json_decode(File::get($jsonFile), true) ?? [];
            }

            $finalTranslations = array_replace_recursive($existingJson, $translations);

            ksort($finalTranslations);

            File::put(
                $jsonFile,
                json_encode($finalTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            $this->info("Zapisano: $locale.json (" . count($finalTranslations) . " kluczy)");
        }

        $this->newLine();
        $this->info('Gotowe.');
    }
}
