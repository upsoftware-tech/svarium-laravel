<?php

namespace Upsoftware\Svarium\Console\Commands;

class AppInstallCommand extends CoreCommand
{
    protected $signature = 'svarium:app:install';

    protected $description = 'Install Svarium in an existing Laravel application';

    protected $descriptionKey = 'app.install';

    public function handle(): int
    {
        $this->info('Uruchamiam instalator aplikacji Svarium...');

        return (int) $this->call('svarium:app.init');
    }
}

