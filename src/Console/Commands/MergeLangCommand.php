<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MergeLangCommand extends Command
{
    protected $signature = 'svarium:lang.merge {lang?}';

    protected $description = 'Łączy pliki JSON z paczki Svarium z plikami JSON głównej aplikacji.';

    public function handle()
    {
        $appLangPath = lang_path();
        $packageLangPath = __DIR__ . '/../../lang';

        if (!File::isDirectory($packageLangPath)) {
            $this->error("Nie znaleziono folderu lang w paczce: $packageLangPath");
            return;
        }

        $packageFiles = File::files($packageLangPath);

        $this->info("Znaleziono plików w paczce: " . count($packageFiles));

        foreach ($packageFiles as $packageFile) {
            if ($packageFile->getExtension() !== 'json') {
                continue;
            }

            $filename = $packageFile->getFilename(); // np. 'pl.json'
            $appFilePath = $appLangPath . '/' . $filename;

            $this->info("Przetwarzanie: $filename");

            $packageContent = json_decode(File::get($packageFile->getPathname()), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("Błąd JSON w paczce ($filename): " . json_last_error_msg());
                continue;
            }

            $appContent = [];
            if (!File::exists($appFilePath)) {
                $this->warn("  - Pominięto ($filename) — język nie istnieje w aplikacji.");
                continue;
            }

            $appContent = json_decode(File::get($appFilePath), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("Błąd JSON w aplikacji ($filename): " . json_last_error_msg());
                continue;
            }

            $mergedContent = array_replace($packageContent ?? [], $appContent ?? []);

            ksort($mergedContent);

            File::put(
                $appFilePath,
                json_encode($mergedContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            $addedKeys = count($mergedContent) - count($appContent);
            $this->line("  - Zaktualizowano. Kluczy łącznie: " . count($mergedContent));
        }

        $this->newLine();
        $this->info('Sukces! Tłumaczenia zostały scalone.');
    }
}
