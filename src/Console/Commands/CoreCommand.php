<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Console\Command;
use Upsoftware\Svarium\Support\QuotedEnvFile;
use Winter\LaravelConfigWriter\ArrayFile;

class CoreCommand extends Command
{
    protected $settingModel;

    public function __construct()
    {
        parent::__construct();
        $this->settingModel = config('svarium.models.setting', \Upsoftware\Svarium\Models\Setting::class);
    }

    protected function addEnvKey(string $key, mixed $value, $force = false, string|bool $newLine = ''): void
    {
        $env = QuotedEnvFile::open(base_path('.env'));
        if ($newLine === true OR $newLine === 'before') {
            $env->addEmptyLine();
        }
        $env->set($key, $value);
        if ($newLine === 'after') {
            $env->addEmptyLine();
        }
        $env->write();
    }

    protected function addConfigKey(string $path, string $key, mixed $value, $force = false): void
    {
        $config = ArrayFile::open(config_path($path));
        if (is_array($value)) {
            $newValue = [];
            foreach ($value as $newKey => $val) {
                if (is_string($val) && str_starts_with($val, '@env')) {
                    if (str_ends_with($val, '\')')) {
                        $val = strtr($val, ['\')' => ')']);
                    }
                    if (str_starts_with($val, '(\'')) {
                        $val = strtr($val, ['(\'' => '(']);
                    }
                    $val = str_replace('@env', "env", $val);
                    $val = strtr($val, ["(" => "('", ")" => "')"]);
                    $newValue[$newKey] = $config->constant($val);
                }
                else {
                    $newValue[$newKey] = $val;
                }
            }
            $config->set([$key => $newValue]);
        } else {
            if (strpos($value, "::")) {
                $value = $config->constant($value);
            }
            $config->set($key, $value);
        }

        $config->write();
    }
}
