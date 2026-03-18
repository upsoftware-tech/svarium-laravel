<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;
use Upsoftware\Svarium\Services\NavigationService;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class MenuAddCommand extends CoreCommand
{
    protected $signature = 'svarium:menu.add';
    protected $description = 'Dodaje pozycję menu (separator, etykieta, pozycja menu)';

    public function handle(): int
    {
        $kind = $this->resolveMenuItemKind();
        $target = $this->resolveTarget();
        $moduleName = $target === 'module' ? $this->resolveModuleName() : null;
        $name = $this->resolveName($kind);
        [$order] = $this->resolveMenuOrderFromPrompt();

        $menuItemLines = $this->buildMenuItemLines($kind, $name, $order, $moduleName);

        if ($target === 'global') {
            $path = $this->writeGlobalMenuItem($menuItemLines);
        } else {
            $path = $this->writeModuleMenuItem((string) $moduleName, $menuItemLines);
        }

        $this->info('Dodano pozycję menu.');
        $this->line('Plik: '.$path);

        return self::SUCCESS;
    }

    protected function resolveMenuItemKind(): string
    {
        return (string) select(
            'Wybierz rodzaj pozycji',
            [
                'separator' => 'Separator',
                'label' => 'Etykieta',
                'item' => 'Pozycja menu',
            ],
            'item'
        );
    }

    protected function resolveTarget(): string
    {
        return (string) select(
            'Gdzie zarejestrować?',
            [
                'global' => 'Ogólne (app/Svarium/functions.php)',
                'module' => 'Konkretny moduł',
            ],
            'global'
        );
    }

    protected function resolveName(string $kind): string
    {
        if ($kind === 'separator') {
            return trim((string) text('Nazwa (opcjonalnie, dla separatora nie jest używana)', 'np. Sekcja'));
        }

        while (true) {
            $name = trim((string) text('Nazwa', 'np. Ustawienia'));
            if ($name !== '') {
                return $name;
            }

            $this->warn('Nazwa nie może być pusta.');
        }
    }

    protected function resolveModuleName(): string
    {
        $modules = $this->discoverModules();

        if ($modules === []) {
            throw new \RuntimeException('Brak modułów w app/Svarium/Modules.');
        }

        $options = [];
        foreach ($modules as $module) {
            $options[$module] = $module;
        }

        return (string) select('Wybierz moduł', $options, array_key_first($options));
    }

    /**
     * @return array<int, string>
     */
    protected function discoverModules(): array
    {
        $files = glob(svarium_modules('*/').'*Module.php');
        if (! is_array($files)) {
            return [];
        }

        $modules = [];

        foreach ($files as $file) {
            $base = basename((string) $file);
            if (! str_ends_with($base, 'Module.php')) {
                continue;
            }

            $module = (string) Str::before($base, 'Module.php');
            if ($module === '') {
                continue;
            }

            $modules[$module] = $module;
        }

        ksort($modules);

        return array_values($modules);
    }

    /**
     * @return array{0: int, 1: bool}
     */
    protected function resolveMenuOrderFromPrompt(): array
    {
        $positions = $this->topLevelMenuPositions();

        if ($positions === []) {
            return [1, false];
        }

        $options = [
            '__end' => 'Na końcu listy',
        ];

        foreach ($positions as $index => $position) {
            $options["item_{$index}"] = "{$position['label']} (etykieta, order: {$position['order']})";
        }

        $selected = (string) select(
            'Za którą pozycją dodać nową pozycję menu?',
            $options,
            '__end'
        );

        if ($selected === '__end') {
            $maxOrder = max(array_map(static fn (array $position): int => (int) $position['order'], $positions));

            return [$maxOrder + 1, false];
        }

        $index = (int) str_replace('item_', '', $selected);
        $currentOrder = (int) ($positions[$index]['order'] ?? 0);

        return [$currentOrder + 1, true];
    }

    /**
     * @return array<int, array{label: string, order: int}>
     */
    protected function topLevelMenuPositions(): array
    {
        try {
            $tree = NavigationService::make()->getRegisteredTree();
            $children = is_array($tree['children'] ?? null) ? $tree['children'] : [];
        } catch (Throwable) {
            return [];
        }

        $positions = [];
        $seen = [];

        foreach ($children as $node) {
            if (! is_array($node)) {
                continue;
            }

            $type = (string) ($node['type'] ?? 'item');
            if (! in_array($type, ['item', 'group'], true)) {
                continue;
            }

            $label = trim((string) ($node['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $normalizedLabel = mb_strtolower($label);
            if (isset($seen[$normalizedLabel])) {
                continue;
            }

            $seen[$normalizedLabel] = true;

            $positions[] = [
                'label' => $label,
                'order' => (int) ($node['order'] ?? 0),
            ];
        }

        usort($positions, static function (array $left, array $right): int {
            if ($left['order'] !== $right['order']) {
                return $left['order'] <=> $right['order'];
            }

            return strcmp($left['label'], $right['label']);
        });

        return $positions;
    }

    /**
     * @return array<int, string>
     */
    protected function buildMenuItemLines(string $kind, string $name, int $order, ?string $moduleName = null): array
    {
        $nameEscaped = str_replace("'", "\\'", $name);

        if ($kind === 'separator') {
            return [
                'MenuItem::separator()',
                '    ->order('.$order.'),',
            ];
        }

        if ($kind === 'label') {
            return [
                "MenuItem::labelItem('{$nameEscaped}')",
                '    ->order('.$order.'),',
            ];
        }

        $lines = [
            "MenuItem::make('{$nameEscaped}')",
        ];

        if (is_string($moduleName) && $moduleName !== '') {
            $moduleKey = (string) Str::of($moduleName)->snake()->toString();
            $moduleKeyEscaped = str_replace("'", "\\'", $moduleKey);
            $lines[] = "    ->url('/'.ltrim(module_route('{$moduleKeyEscaped}'), '/'))";
        }

        $lines[] = '    ->order('.$order.'),';

        return $lines;
    }

    /**
     * @param array<int, string> $lines
     */
    protected function writeGlobalMenuItem(array $lines): string
    {
        $path = base_path('app/Svarium/functions.php');
        $this->ensureGlobalFunctionsFileExists($path);

        $content = (string) File::get($path);
        $content = $this->ensureMenuItemUseStatement($content);

        $item = $this->indentLines($lines, 4);
        $append = "\n\nregister_menu([\n{$item}\n], source: 'app/Svarium/functions.php');\n";

        File::put($path, rtrim($content).$append);

        return $path;
    }

    protected function writeModuleMenuItem(string $moduleName, array $lines): string
    {
        $path = svarium_modules($moduleName.'/'.$moduleName.'Module.php');

        if (! File::exists($path)) {
            throw new \RuntimeException("Nie znaleziono pliku modułu: {$path}");
        }

        $content = (string) File::get($path);
        $content = $this->ensureMenuItemUseStatement($content);

        $item = $this->indentLines($lines, 8);
        $updated = $this->appendToExistingMenuMethod($content, $item);

        if ($updated === null) {
            $updated = $this->appendNewMenuMethod($content, $item);
        }

        File::put($path, $updated);

        return $path;
    }

    protected function ensureGlobalFunctionsFileExists(string $path): void
    {
        if (File::exists($path)) {
            return;
        }

        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true, true);
        }

        File::put($path, <<<'PHP'
<?php

use Upsoftware\Svarium\Menu\MenuItem;

register_menu([
], source: 'app/Svarium/functions.php');
PHP);
    }

    protected function ensureMenuItemUseStatement(string $content): string
    {
        if (str_contains($content, 'use Upsoftware\\Svarium\\Menu\\MenuItem;')) {
            return $content;
        }

        $replaced = preg_replace(
            '/^(namespace\s+[^\n]+;\s*\n)/m',
            "$1\nuse Upsoftware\\Svarium\\Menu\\MenuItem;\n",
            $content,
            1
        );

        if (is_string($replaced) && $replaced !== '') {
            return $replaced;
        }

        return "use Upsoftware\\Svarium\\Menu\\MenuItem;\n\n".$content;
    }

    protected function appendToExistingMenuMethod(string $content, string $item): ?string
    {
        $updated = preg_replace_callback(
            '/(public function menu\(\): array\s*\{\s*return\s*\[)([\s\S]*?)(\n\s*];\s*\n\s*})/m',
            static function (array $matches) use ($item): string {
                $body = rtrim((string) ($matches[2] ?? ''));

                if (trim($body) === '') {
                    $newBody = "\n{$item}\n    ";
                } else {
                    if (! preg_match('/,\s*$/', $body)) {
                        $body .= ',';
                    }

                    $newBody = $body."\n{$item}\n    ";
                }

                return (string) ($matches[1] ?? '').$newBody.(string) ($matches[3] ?? '');
            },
            $content,
            1
        );

        if (! is_string($updated) || $updated === $content) {
            return null;
        }

        return $updated;
    }

    protected function appendNewMenuMethod(string $content, string $item): string
    {
        $method = "\n    public function menu(): array\n    {\n        return [\n{$item}\n        ];\n    }\n";

        $classEndPos = strrpos($content, '}');

        if ($classEndPos === false) {
            return rtrim($content)."\n".$method;
        }

        return substr($content, 0, $classEndPos).$method."\n}";
    }

    /**
     * @param array<int, string> $lines
     */
    protected function indentLines(array $lines, int $spaces): string
    {
        $indent = str_repeat(' ', max(0, $spaces));

        return implode("\n", array_map(
            static fn (string $line): string => $indent.$line,
            $lines
        ));
    }
}
