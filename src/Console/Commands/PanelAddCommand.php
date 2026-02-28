<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Support\Facades\File;
use Upsoftware\Svarium\Panel\Panel;

class PanelAddCommand extends CoreCommand
{
    protected $signature = 'svarium:panel.add
        {name? : Panel name (example: admin)}
        {--prefix= : URL prefix for panel (example: admin)}
        {--no-prefix : Register panel without prefix}';

    protected $description = 'Add panel definition to app/Svarium/panels.php';

    public function handle(): int
    {
        try {
            $name = $this->resolvePanelName();
            $prefix = $this->resolvePanelPrefix($name);
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $panelsFile = base_path('app/Svarium/panels.php');
        $panels = $this->loadPanelsFromFile($panelsFile);

        if ($this->panelNameExists($panels, $name)) {
            $this->error("Panel [{$name}] already exists.");
            return self::FAILURE;
        }

        if ($prefix === null && $this->hasPanelWithoutPrefix($panels)) {
            $this->error('Only one panel without prefix is allowed.');
            return self::FAILURE;
        }

        if ($prefix !== null && $this->prefixExists($panels, $prefix)) {
            $this->error("Prefix [{$prefix}] is already used by another panel.");
            return self::FAILURE;
        }

        $entry = $this->buildPanelEntry($name, $prefix);
        $this->upsertPanelsFile($panelsFile, $entry);

        $this->info("Panel [{$name}] added.");
        $this->line('File: '.$panelsFile);

        return self::SUCCESS;
    }

    protected function resolvePanelName(): string
    {
        $name = trim((string) ($this->argument('name') ?? ''));

        if ($name === '') {
            $name = trim((string) $this->ask('Panel name', 'admin'));
        }

        if ($name === '') {
            throw new \InvalidArgumentException('Panel name cannot be empty.');
        }

        if (! preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
            throw new \InvalidArgumentException(
                'Panel name may only contain letters, numbers, "_" and "-".'
            );
        }

        return $name;
    }

    protected function resolvePanelPrefix(string $name): ?string
    {
        if ((bool) $this->option('no-prefix')) {
            return null;
        }

        $prefixOption = $this->option('prefix');

        if (is_string($prefixOption)) {
            $prefix = trim($prefixOption, '/');
            return $prefix !== '' ? $prefix : null;
        }

        $prefix = trim((string) $this->ask(
            'Panel prefix (leave empty for no prefix)',
            $name
        ), '/');

        return $prefix !== '' ? $prefix : null;
    }

    protected function loadPanelsFromFile(string $panelsFile): array
    {
        if (! File::exists($panelsFile)) {
            return [];
        }

        $panels = require $panelsFile;

        if (! is_array($panels)) {
            $this->warn('Invalid panels.php format. Command will append entry only.');
            return [];
        }

        return array_values(array_filter($panels, fn ($panel) => $panel instanceof Panel));
    }

    protected function panelNameExists(array $panels, string $name): bool
    {
        foreach ($panels as $panel) {
            if (($panel->name ?? null) === $name) {
                return true;
            }
        }

        return false;
    }

    protected function hasPanelWithoutPrefix(array $panels): bool
    {
        foreach ($panels as $panel) {
            if (($panel->prefix ?? null) === null) {
                return true;
            }
        }

        return false;
    }

    protected function prefixExists(array $panels, string $prefix): bool
    {
        $normalized = trim($prefix, '/');

        foreach ($panels as $panel) {
            if (($panel->prefix ?? null) === null) {
                continue;
            }

            if (trim((string) $panel->prefix, '/') === $normalized) {
                return true;
            }
        }

        return false;
    }

    protected function buildPanelEntry(string $name, ?string $prefix): string
    {
        if ($prefix === null) {
            return "    Panel::make('{$name}')->noPrefix(),";
        }

        return "    Panel::make('{$name}')->prefix('{$prefix}'),";
    }

    protected function upsertPanelsFile(string $panelsFile, string $entry): void
    {
        $directory = dirname($panelsFile);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true, true);
        }

        if (! File::exists($panelsFile)) {
            File::put($panelsFile, $this->newPanelsFileContent($entry));
            return;
        }

        $content = File::get($panelsFile);

        if (! str_contains($content, 'Upsoftware\\Svarium\\Panel\\Panel')) {
            $content = preg_replace(
                '/<\\?php\\s*/',
                "<?php\n\nuse Upsoftware\\Svarium\\Panel\\Panel;\n\n",
                $content,
                1
            ) ?? $content;
        }

        $closingPos = strrpos($content, '];');

        if ($closingPos === false) {
            throw new \RuntimeException('Could not find closing array in panels.php');
        }

        $before = rtrim(substr($content, 0, $closingPos));
        $after = substr($content, $closingPos);

        if (! str_ends_with($before, '[')) {
            $before .= "\n";
        }

        $before .= $entry."\n";

        File::put($panelsFile, $before.$after);
    }

    protected function newPanelsFileContent(string $entry): string
    {
        return <<<PHP
<?php

use Upsoftware\\Svarium\\Panel\\Panel;

return [
{$entry}
];
PHP;
    }
}
