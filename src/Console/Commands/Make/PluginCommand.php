<?php

namespace Upsoftware\Svarium\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PluginCommand extends Command
{
    protected string $pluginName = '';
    protected string $pluginDir = '';

    protected $signature = 'svarium:make:plugin {name?}';
    protected $description = 'Tworzy szablon pluginu';

    public function handle() {
        $name = $this->argument('name');
        while (!$name || strlen($name) < 3) {
            $name = $this->ask('Podaj nazwę pluginu');
            if (strlen($name) < 3) {
                $this->error('Nazwa musi zawierać minimum 3 znaki');
            }

            $name = Str::ucfirst(Str::camel(Str::slug(Str::headline($name, ' '))));
            $plugin_dir = svarium_plugins($name);
            if (File::isDirectory($plugin_dir)) {
                $this->error('Folder pluginu już istnieje');
                $name = '';
            }
            $this->pluginDir = $plugin_dir;
            $this->pluginName = $name;
        }

        File::makeDirectory($this->pluginDir, 0755, true);

        $this->info('Nazwa pluginu: ' . $this->pluginName);
    }
}
