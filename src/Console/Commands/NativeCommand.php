<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Throwable;

class NativeCommand extends CoreCommand
{
    protected $signature = 'svarium:native
        {action=install : Dostępna akcja: install}
        {--without-ide-helper : Pomiń ide-helper podczas instalacji}';

    protected $description = 'Run native installer and optional IDE helper generation';
    protected $descriptionKey = 'native.install';

    public function handle(): int
    {
        $action = strtolower(trim((string) $this->argument('action')));

        if ($action !== 'install') {
            $this->error("Nieznana akcja [{$action}]. Dostępna: install");

            return self::FAILURE;
        }

        if (! (bool) $this->option('without-ide-helper')) {
            $ideExitCode = $this->runIdeHelperCommands();
            if ($ideExitCode !== self::SUCCESS) {
                return $ideExitCode;
            }
        }

        return $this->runNativeInstallCommand();
    }

    protected function runIdeHelperCommands(): int
    {
        $commands = [
            ['name' => 'ide-helper:generate', 'arguments' => [], 'required' => false],
            ['name' => 'ide-helper:models', 'arguments' => ['--nowrite' => true], 'required' => false],
            ['name' => 'ide-helper:meta', 'arguments' => [], 'required' => false],
        ];

        foreach ($commands as $command) {
            $exitCode = $this->runArtisanCommand(
                command: $command['name'],
                arguments: $command['arguments'],
                required: $command['required']
            );

            if ($exitCode !== self::SUCCESS) {
                return $exitCode;
            }
        }

        return self::SUCCESS;
    }

    protected function runNativeInstallCommand(): int
    {
        return $this->runArtisanCommand(
            command: 'native:install',
            arguments: [],
            required: true
        );
    }

    protected function runArtisanCommand(string $command, array $arguments = [], bool $required = true): int
    {
        if (! $this->artisanCommandExists($command)) {
            $message = "Komenda [{$command}] nie jest dostępna.";
            if ($required) {
                $this->error($message);

                return self::FAILURE;
            }

            $this->warn($message.' Pomijam.');

            return self::SUCCESS;
        }

        $this->line('Uruchamiam: php artisan '.$command);
        $exitCode = $this->call($command, $arguments);

        if ($exitCode !== self::SUCCESS) {
            $this->error("Komenda [{$command}] zakończyła się błędem (kod: {$exitCode}).");
        }

        return $exitCode;
    }

    protected function artisanCommandExists(string $command): bool
    {
        try {
            return array_key_exists($command, Artisan::all());
        } catch (Throwable) {
            return false;
        }
    }
}

