<?php

namespace Upsoftware\Svarium\Console\Commands\Make;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use Upsoftware\Svarium\Console\Commands\CoreCommand;
use Upsoftware\Svarium\Services\NavigationService;

class ModuleCommand extends CoreCommand
{
    protected $signature = 'svarium:make.module {name?}';
    protected $description = 'Create a new Svarium module';
    protected $descriptionKey = 'make.module';
    protected string $menuMethod = '';
    protected string $entryView = 'table';
    protected string $resourceMode = 'crud';
    /**
     * @var array{create: bool, preview: bool, edit: bool, duplicate: bool, delete: bool}
     */
    protected array $resourceActions = [
        'create' => true,
        'preview' => true,
        'edit' => true,
        'duplicate' => true,
        'delete' => true,
    ];
    /**
     * @var array<string, array{locale: string, label: string, name: string, name_plural: string}>
     */
    protected array $moduleTranslations = [];
    /**
     * @var array<int, array{code: string, label: string}>
     */
    protected array $availableLocales = [];
    /**
     * @var array{label: string, order: int}|null
     */
    protected ?array $currentMenuPosition = null;

    public function handle(): void
    {
        try {
            $name = $this->resolveModuleName();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return;
        }

        $this->availableLocales = $this->resolveAvailableLocales();
        $this->moduleTranslations = $this->resolveModuleTranslations($name, $this->availableLocales);
        $this->entryView = $this->resolveEntryView();
        $this->resourceMode = $this->resolveResourceMode();
        $this->resourceActions = $this->resolveResourceActions($this->resourceMode);
        $this->menuMethod = $this->buildMenuMethod($name, $this->resolveMenuConfig($name));
        $base = svarium_path("Modules/{$name}");

        $this->createStructure($base);
        $this->createTranslationFiles($base, $this->moduleTranslations, $name);
        $this->createModuleClass($name, $base);
        $this->createModelClass($name, $base);
        $this->createResourceClass($name, $base);
        $this->createTableClass($name, $base);
        $this->createFormClass($name, $base);
        $this->createEntryOperationClass($name, $base);
        $this->syncLanguageFiles();

        $this->info("Svarium module {$name} created.");
    }

    protected function resolveModuleName(): string
    {
        $nameInput = (string) ($this->argument('name') ?? '');

        if (! $this->input->isInteractive()) {
            $name = Str::studly(trim($nameInput));

            if ($name === '') {
                throw new RuntimeException('Nazwa modułu jest wymagana.');
            }

            $base = svarium_path("Modules/{$name}");
            if (File::exists($base)) {
                throw new RuntimeException(
                    "Moduł {$name} już istnieje. Uruchom komendę interaktywnie, aby potwierdzić nadpisanie."
                );
            }

            return $name;
        }

        while (true) {
            if (trim($nameInput) === '') {
                $nameInput = (string) text('Wpisz nazwę modułu', 'np. User');
            }

            $name = Str::studly(trim($nameInput));
            if ($name === '') {
                $nameInput = '';
                continue;
            }

            $base = svarium_path("Modules/{$name}");
            if (! File::exists($base)) {
                $this->currentMenuPosition = null;
                return $name;
            }

            $overwrite = (bool) confirm(
                "Moduł {$name} już istnieje. Nadpisać cały moduł?",
                false,
                'Tak',
                'Nie'
            );

            if ($overwrite) {
                $this->currentMenuPosition = $this->resolveCurrentMenuPosition($name);
                File::deleteDirectory($base);
                return $name;
            }

            $nameInput = '';
        }
    }

    /**
     * @return array<int, array{code: string, label: string}>
     */
    protected function resolveAvailableLocales(): array
    {
        $collected = [];

        if (function_exists('locales')) {
            try {
                $configured = locales();

                if (is_array($configured)) {
                    foreach ($configured as $locale) {
                        $rawCode = is_array($locale) ? (string) ($locale['value'] ?? '') : '';
                        $code = $this->normalizeLocaleCode($rawCode);

                        if ($code === null || isset($collected[$code])) {
                            continue;
                        }

                        $label = is_array($locale)
                            ? trim((string) ($locale['label'] ?? strtoupper($code)))
                            : strtoupper($code);

                        $collected[$code] = [
                            'code' => $code,
                            'label' => $label !== '' ? $label : strtoupper($code),
                        ];
                    }
                }
            } catch (Throwable) {
                // Ignore and use fallback locale list.
            }
        }

        $fallback = $this->normalizeLocaleCode((string) config('app.locale', 'pl')) ?? 'pl';

        if (! isset($collected[$fallback])) {
            $collected[$fallback] = [
                'code' => $fallback,
                'label' => strtoupper($fallback),
            ];
        }

        return array_values($collected);
    }

    /**
     * @param array<int, array{code: string, label: string}> $locales
     * @return array<string, array{locale: string, label: string, name: string, name_plural: string}>
     */
    protected function resolveModuleTranslations(string $moduleName, array $locales): array
    {
        $singularDefault = $moduleName;
        $pluralDefault = Str::plural($moduleName);

        $translations = [];

        foreach ($locales as $locale) {
            $code = (string) ($locale['code'] ?? '');
            $label = (string) ($locale['label'] ?? strtoupper($code));

            $name = $singularDefault;
            $namePlural = $pluralDefault;

            if ($this->input->isInteractive()) {
                $name = trim((string) text(
                    "Nazwa modułu ({$label} / {$code}) - liczba pojedyncza",
                    '',
                    $singularDefault
                ));

                if ($name === '') {
                    $name = $singularDefault;
                }

                $namePlural = trim((string) text(
                    "Nazwa modułu ({$label} / {$code}) - liczba mnoga",
                    '',
                    Str::plural($name)
                ));

                if ($namePlural === '') {
                    $namePlural = Str::plural($name);
                }
            }

            $translations[$code] = [
                'locale' => $code,
                'label' => $label,
                'name' => $name,
                'name_plural' => $namePlural,
            ];
        }

        return $translations;
    }

    protected function normalizeLocaleCode(string $value): ?string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9_-]/', '', $normalized) ?? '';
        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : null;
    }

    protected function syncLanguageFiles(): void
    {
        try {
            Artisan::call('svarium:lang.prepare');
            Artisan::call('svarium:lang.merge');
        } catch (Throwable $exception) {
            $this->warn('Nie udało się automatycznie zaktualizować tłumaczeń (svarium:lang.prepare / svarium:lang.merge).');
            $this->warn($exception->getMessage());
        }
    }

    protected function createStructure(string $base): void
    {
        $dirs = [
            '',
            'Panel',
            'Web',
            'Api',
            'Forms',
            'Tables',
            'Models',
            'Policies',
        ];

        foreach ($dirs as $dir) {
            File::makeDirectory($base.'/'.$dir, 0755, true, true);
        }
    }

    /**
     * @param array<string, array{locale: string, label: string, name: string, name_plural: string}> $translations
     */
    protected function createTranslationFiles(string $base, array $translations, string $moduleNameEn): void
    {
        if ($translations === []) {
            return;
        }

        foreach ($translations as $translation) {
            $locale = trim((string) ($translation['locale'] ?? ''));
            if ($locale === '') {
                continue;
            }

            $langDirectory = $base.'/Lang/'.$locale;
            File::makeDirectory($langDirectory, 0755, true, true);

            $moduleName = (string) ($translation['name'] ?? '');
            if ($moduleName === '') {
                $moduleName = ''.$this->argument('name');
            }

            $moduleNamePlural = (string) ($translation['name_plural'] ?? '');
            if ($moduleNamePlural === '') {
                $moduleNamePlural = Str::plural($moduleName);
            }

            $moduleNameEn = (string) Str::studly(trim($moduleNameEn));
            if ($moduleNameEn === '') {
                $moduleNameEn = $moduleName;
            }

            $moduleNamePluralEn = Str::plural($moduleNameEn);

            $payload = [
                $moduleNameEn => $moduleName,
                $moduleNamePluralEn => $moduleNamePlural,
                $moduleNamePluralEn.' calendar' => $moduleNamePlural.' calendar',
                $moduleNamePluralEn.' page' => $moduleNamePlural.' page',
            ];

            $content = "<?php\n\nreturn ".var_export($payload, true).";\n";

            File::put($langDirectory.'/module.php', $content);
        }
    }

    protected function createModuleClass(string $name, string $base): void
    {
        File::put($base."/{$name}Module.php", $this->renderStub('svarium.module.php.stub', $name));
    }

    protected function createResourceClass(string $name, string $base): void
    {
        File::put($base."/Panel/{$name}Resource.php", $this->renderStub('svarium.module.resource.php.stub', $name));
    }

    protected function createModelClass(string $name, string $base): void
    {
        File::put($base."/Models/{$name}.php", $this->renderStub('svarium.module.model.php.stub', $name));
    }

    protected function createTableClass(string $name, string $base): void
    {
        File::put($base."/Tables/{$name}Table.php", $this->renderStub('svarium.module.table.php.stub', $name));
    }

    protected function createFormClass(string $name, string $base): void
    {
        File::put($base."/Forms/{$name}Form.php", $this->renderStub('svarium.module.form.php.stub', $name));
    }

    protected function renderStub(string $stubFile, string $name): string
    {
        $path = $this->stubPath($stubFile);

        if (! File::exists($path)) {
            throw new \RuntimeException("Stub file [{$stubFile}] does not exist.");
        }

        $content = File::get($path);

        return strtr($content, [
            '{{ModuleName}}' => $name,
            '{{ModuleNamePlural}}' => Str::plural($name),
            '{{ModuleNameLower}}' => Str::of($name)->snake()->toString(),
            '{{ModuleNamePluralLower}}' => Str::of(Str::plural($name))->snake()->toString(),
            '{{ModuleSlug}}' => $this->moduleSlug($name),
            '{{ResourceAccessMethods}}' => $this->buildResourceAccessMethods(),
            '{{TableDefaultActionsMode}}' => $this->buildTableDefaultActionsMode(),
            '{{TableHeaderCreateAction}}' => $this->buildTableHeaderCreateAction(),
            '{{TableRowActions}}' => $this->buildTableRowActions(),
            '{{MenuMethod}}' => $this->menuMethod !== '' ? $this->menuMethod : $this->buildMenuMethod($name, [
                'enabled' => true,
                'with_submenu' => true,
                'icon' => 'lucide:users',
                'order' => 1,
            ]),
        ]);
    }

    protected function stubPath(string $stubFile): string
    {

        return __DIR__.'/../../../stubs/'.$stubFile;
    }

    /**
     * @return array{
     *   enabled: bool,
     *   with_submenu?: bool,
     *   icon?: string,
     *   order?: int,
     *   parent_menu?: string|null
     * }
     */
    protected function resolveMenuConfig(string $name): array
    {
        if (! $this->input->isInteractive()) {
            $positions = $this->topLevelMenuPositions();
            $maxOrder = $positions === []
                ? 0
                : max(array_map(static fn (array $position): int => (int) $position['order'], $positions));

            return [
                'enabled' => true,
                'with_submenu' => true,
                'icon' => 'lucide:users',
                'order' => $maxOrder + 1,
                'parent_menu' => null,
            ];
        }

        $addToMenu = (bool) confirm('Czy chcesz dodać pozycję do menu?', true, 'Tak', 'Nie');

        if (! $addToMenu) {
            return ['enabled' => false];
        }

        $parentMenu = null;
        $addAsSubmenu = (bool) confirm(
            'Czy dodać jako podpozycję istniejącego menu?',
            false,
            'Tak',
            'Nie'
        );

        if ($addAsSubmenu) {
            $parentMenu = $this->resolveParentMenuFromPrompt();
        }

        $withSubmenu = (bool) confirm(
            $parentMenu !== null
                ? 'Czy ta podpozycja ma mieć własne podpozycje?'
                : 'Czy menu ma mieć podpozycje?',
            $parentMenu === null,
            'Tak',
            'Nie'
        );

        $randomIcon = $this->randomLucideIcon();
        $iconInput = trim((string) text('Ikonka (lucide:...)', 'np. lucide:users', $randomIcon));
        $icon = $iconInput !== '' ? $iconInput : $randomIcon;
        if (! str_starts_with($icon, 'lucide:')) {
            $icon = 'lucide:'.$icon;
        }

        if ($parentMenu !== null) {
            $order = $this->resolveChildMenuOrder($parentMenu);
        } else {
            [$order, $shouldShift] = $this->resolveMenuOrderFromPrompt();
            if ($shouldShift) {
                $this->shiftExistingMenuOrders($order);
            }
        }

        return [
            'enabled' => true,
            'with_submenu' => $withSubmenu,
            'icon' => $icon,
            'order' => $order,
            'parent_menu' => $parentMenu,
        ];
    }

    protected function resolveEntryView(): string
    {
        if (! $this->input->isInteractive()) {
            return 'table';
        }

        return (string) select(
            'Jaki ma być widok wejściowy modułu?',
            [
                'table' => 'Tabela (domyślny CRUD)',
                'calendar' => 'Kalendarz (operation + schema)',
                'page' => 'Pusta strona (Operation + Block/Text)',
            ],
            'table'
        );
    }

    protected function resolveResourceMode(): string
    {
        if (! $this->input->isInteractive()) {
            return 'crud';
        }

        return (string) select(
            'Jaki ma być tryb zasobu?',
            [
                'crud' => 'Pełny CRUD (create, edit, delete)',
                'custom' => 'Wybrane akcje (checkbox)',
            ],
            'crud'
        );
    }

    /**
     * @return array{create: bool, preview: bool, edit: bool, duplicate: bool, delete: bool}
     */
    protected function resolveResourceActions(string $mode): array
    {
        $defaults = [
            'create' => true,
            'preview' => true,
            'edit' => true,
            'duplicate' => true,
            'delete' => true,
        ];

        if ($mode === 'list_delete') {
            return [
                'create' => false,
                'preview' => false,
                'edit' => false,
                'duplicate' => false,
                'delete' => true,
            ];
        }

        if ($mode !== 'custom' || ! $this->input->isInteractive()) {
            return $defaults;
        }

        $selected = multiselect(
            'Wybierz akcje zasobu',
            [
                'create' => 'Create',
                'preview' => 'Preview',
                'edit' => 'Edit',
                'duplicate' => 'Duplicate',
                'delete' => 'Delete',
            ],
            array_keys(array_filter($defaults))
        );

        $picked = is_array($selected)
            ? array_map(static fn (mixed $value): string => (string) $value, $selected)
            : [];

        return [
            'create' => in_array('create', $picked, true),
            'preview' => in_array('preview', $picked, true),
            'edit' => in_array('edit', $picked, true),
            'duplicate' => in_array('duplicate', $picked, true),
            'delete' => in_array('delete', $picked, true),
        ];
    }

    protected function resourceActionEnabled(string $action): bool
    {
        return (bool) ($this->resourceActions[$action] ?? false);
    }

    protected function phpBool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    /**
     * @return array{0: int, 1: bool} [order, shouldShiftExisting]
     */
    protected function resolveMenuOrderFromPrompt(): array
    {
        $positions = $this->topLevelMenuPositions();
        $positions = $this->mergeCurrentMenuPosition($positions);

        if ($positions === []) {
            return [1, false];
        }

        $options = [
            '__end' => 'Na końcu listy',
        ];

        foreach ($positions as $index => $position) {
            $isCurrent = $this->isCurrentMenuPosition($position);
            $suffix = $isCurrent ? ' (aktualna pozycja)' : '';
            $options["item_{$index}"] = "{$position['label']} (order: {$position['order']}){$suffix}";
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
     * @param array<int, array{label: string, order: int}> $positions
     * @return array<int, array{label: string, order: int}>
     */
    protected function mergeCurrentMenuPosition(array $positions): array
    {
        if ($this->currentMenuPosition === null) {
            return $positions;
        }

        $current = $this->currentMenuPosition;
        $exists = false;

        foreach ($positions as $position) {
            if (
                $this->menuLabelsMatch((string) $position['label'], (string) $current['label'])
                && (int) $position['order'] === (int) $current['order']
            ) {
                $exists = true;
                break;
            }
        }

        if (! $exists) {
            $positions[] = $current;
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
     * @param array{label: string, order: int} $position
     */
    protected function isCurrentMenuPosition(array $position): bool
    {
        if ($this->currentMenuPosition === null) {
            return false;
        }

        return $this->menuLabelsMatch((string) $position['label'], (string) $this->currentMenuPosition['label'])
            && (int) $position['order'] === (int) $this->currentMenuPosition['order'];
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

            if (($node['type'] ?? 'item') !== 'item') {
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
     * @return array{label: string, order: int}|null
     */
    protected function resolveCurrentMenuPosition(string $moduleName): ?array
    {
        $positions = $this->topLevelMenuPositions();
        $expectedLabel = Str::plural($moduleName);

        foreach ($positions as $position) {
            if ($this->menuLabelsMatch((string) $position['label'], $expectedLabel)) {
                return $position;
            }
        }

        return $this->resolveCurrentMenuPositionFromFile($moduleName);
    }

    /**
     * @return array{label: string, order: int}|null
     */
    protected function resolveCurrentMenuPositionFromFile(string $moduleName): ?array
    {
        $moduleFile = svarium_path("Modules/{$moduleName}/{$moduleName}Module.php");
        if (! File::exists($moduleFile)) {
            return null;
        }

        $content = (string) File::get($moduleFile);
        if ($content === '') {
            return null;
        }

        $order = 1;
        if (preg_match('/->order\(\s*(-?\d+)\s*\)/', $content, $orderMatch) === 1) {
            $order = (int) ($orderMatch[1] ?? 1);
        }

        $label = Str::plural($moduleName);
        if (
            preg_match('/MenuItem::make\(\s*__\(\s*[\'"]([^\'"]+)[\'"]\s*\)\s*\)/', $content, $labelMatch) === 1
            || preg_match('/MenuItem::make\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $content, $labelMatch) === 1
        ) {
            $matchedLabel = trim((string) ($labelMatch[1] ?? ''));
            if ($matchedLabel !== '') {
                $label = $matchedLabel;
            }
        }

        $label = $this->translateMenuLabel($label);

        return [
            'label' => $label,
            'order' => $order,
        ];
    }

    protected function menuLabelsMatch(string $left, string $right): bool
    {
        $leftNormalized = $this->normalizeMenuLabel($left);
        $rightNormalized = $this->normalizeMenuLabel($right);

        if ($leftNormalized === $rightNormalized) {
            return true;
        }

        $leftTranslated = $this->normalizeMenuLabel($this->translateMenuLabel($left));
        $rightTranslated = $this->normalizeMenuLabel($this->translateMenuLabel($right));

        return $leftTranslated === $rightNormalized
            || $rightTranslated === $leftNormalized
            || $leftTranslated === $rightTranslated;
    }

    protected function translateMenuLabel(string $label): string
    {
        $trimmed = trim($label);
        if ($trimmed === '') {
            return $label;
        }

        $translated = __($trimmed);

        if (! is_string($translated)) {
            return $label;
        }

        $translated = trim($translated);

        return $translated !== '' ? $translated : $label;
    }

    protected function normalizeMenuLabel(string $label): string
    {
        return mb_strtolower(trim($label));
    }

    protected function shiftExistingMenuOrders(int $fromOrder): void
    {
        $moduleFiles = glob(svarium_modules('*/').'*Module.php');

        if (! is_array($moduleFiles) || $moduleFiles === []) {
            return;
        }

        foreach ($moduleFiles as $moduleFile) {
            if (! is_string($moduleFile) || ! File::exists($moduleFile)) {
                continue;
            }

            $content = (string) File::get($moduleFile);
            if ($content === '' || ! str_contains($content, '->order(')) {
                continue;
            }

            $updated = preg_replace_callback(
                '/->order\(\s*(-?\d+)\s*\)/',
                static function (array $matches) use ($fromOrder): string {
                    $order = (int) ($matches[1] ?? 0);

                    if ($order >= $fromOrder) {
                        return '->order('.($order + 1).')';
                    }

                    return $matches[0];
                },
                $content
            );

            if (is_string($updated) && $updated !== $content) {
                File::put($moduleFile, $updated);
            }
        }
    }

    protected function resolveParentMenuFromPrompt(): ?string
    {
        $positions = $this->topLevelMenuPositions();
        if ($positions === []) {
            $this->warn('Brak pozycji menu do podpięcia. Zostanie dodana nowa pozycja główna.');
            return null;
        }

        $options = [];
        foreach ($positions as $index => $position) {
            $options["item_{$index}"] = "{$position['label']} (order: {$position['order']})";
        }

        $selected = (string) select(
            'Pod którą pozycją główną dodać moduł?',
            $options,
            array_key_first($options)
        );

        $index = (int) str_replace('item_', '', $selected);
        $parent = trim((string) ($positions[$index]['label'] ?? ''));

        return $parent !== '' ? $parent : null;
    }

    protected function resolveChildMenuOrder(string $parentMenu): int
    {
        $maxOrder = 0;

        try {
            $tree = NavigationService::make()->getRegisteredTree();
            $children = is_array($tree['children'] ?? null) ? $tree['children'] : [];

            foreach ($children as $node) {
                if (! is_array($node)) {
                    continue;
                }

                $label = trim((string) ($node['label'] ?? ''));
                if (mb_strtolower($label) !== mb_strtolower($parentMenu)) {
                    continue;
                }

                $subItems = is_array($node['children'] ?? null) ? $node['children'] : [];
                foreach ($subItems as $subItem) {
                    if (! is_array($subItem)) {
                        continue;
                    }

                    $maxOrder = max($maxOrder, (int) ($subItem['order'] ?? 0));
                }

                break;
            }
        } catch (Throwable) {
            return 1;
        }

        return $maxOrder + 1;
    }

    /**
     * @param array{
     *   enabled: bool,
     *   with_submenu?: bool,
     *   icon?: string,
     *   order?: int,
     *   parent_menu?: string|null
     * } $config
     */
    protected function buildMenuMethod(string $name, array $config): string
    {
        if (($config['enabled'] ?? false) !== true) {
            return <<<'PHP'
    public function menu(): array
    {
        return [];
    }
PHP;
        }

        $plural = Str::plural($name);
        $moduleRouteKey = Str::of($name)->snake()->toString();
        $order = (int) ($config['order'] ?? 1);
        $icon = trim((string) ($config['icon'] ?? 'lucide:users'));
        $withSubmenu = (bool) ($config['with_submenu'] ?? true);
        $parentMenu = trim((string) ($config['parent_menu'] ?? ''));
        $isCalendarEntry = $this->entryView === 'calendar';
        $isPageEntry = $this->entryView === 'page';
        $entryPathSuffix = $isCalendarEntry ? '/calendar' : ($isPageEntry ? '/page' : '');
        $entryLabel = $isCalendarEntry ? 'Calendar' : ($isPageEntry ? 'Page' : 'List');

        $pluralEscaped = str_replace("'", "\\'", $plural);
        $iconEscaped = str_replace("'", "\\'", $icon);
        $moduleRouteKeyEscaped = str_replace("'", "\\'", $moduleRouteKey);
        $entryLabelEscaped = str_replace("'", "\\'", $entryLabel);
        $entryPathSuffixEscaped = str_replace("'", "\\'", $entryPathSuffix);
        $parentMenuEscaped = str_replace("'", "\\'", $parentMenu);

        if ($parentMenu !== '' && ! $withSubmenu) {
            return <<<PHP
    public function menu(): array
    {
        return [
            MenuItem::make('{$pluralEscaped}')
                ->icon('{$iconEscaped}')
                ->url('/'.ltrim(module_route('{$moduleRouteKeyEscaped}'), '/').'{$entryPathSuffixEscaped}')
                ->path(['{$parentMenuEscaped}'])
                ->order({$order}),
        ];
    }
PHP;
        }

        if ($parentMenu !== '' && $withSubmenu) {
            return <<<PHP
    public function menu(): array
    {
        return [
            MenuItem::make('{$pluralEscaped}')
                ->icon('{$iconEscaped}')
                ->path(['{$parentMenuEscaped}'])
                ->order({$order}),

            MenuItem::make('{$entryLabelEscaped}')
                ->url('/'.ltrim(module_route('{$moduleRouteKeyEscaped}'), '/').'{$entryPathSuffixEscaped}')
                ->path(['{$parentMenuEscaped}', '{$pluralEscaped}'])
                ->order({$order}),
        ];
    }
PHP;
        }

        if (! $withSubmenu) {
            return <<<PHP
    public function menu(): array
    {
        return [
            MenuItem::make('{$pluralEscaped}')
                ->icon('{$iconEscaped}')
                ->url('/'.ltrim(module_route('{$moduleRouteKeyEscaped}'), '/').'{$entryPathSuffixEscaped}')
                ->order({$order}),
        ];
    }
PHP;
        }

        if ($isCalendarEntry) {
            $listOrder = $order + 1;

            return <<<PHP
    public function menu(): array
    {
        return [
            MenuItem::make('{$pluralEscaped}')
                ->icon('{$iconEscaped}')
                ->order({$order}),

            MenuItem::make('{$entryLabelEscaped}')
                ->url('/'.ltrim(module_route('{$moduleRouteKeyEscaped}'), '/').'{$entryPathSuffixEscaped}')
                ->path(['{$pluralEscaped}'])
                ->order({$order}),

            MenuItem::make('List')
                ->url('/'.ltrim(module_route('{$moduleRouteKeyEscaped}'), '/'))
                ->path(['{$pluralEscaped}'])
                ->order({$listOrder}),
        ];
    }
PHP;
        }

        if ($isPageEntry) {
            return <<<PHP
    public function menu(): array
    {
        return [
            MenuItem::make('{$pluralEscaped}')
                ->icon('{$iconEscaped}')
                ->order({$order}),

            MenuItem::make('{$entryLabelEscaped}')
                ->url('/'.ltrim(module_route('{$moduleRouteKeyEscaped}'), '/').'{$entryPathSuffixEscaped}')
                ->path(['{$pluralEscaped}'])
                ->order({$order}),
        ];
    }
PHP;
        }

        return <<<PHP
    public function menu(): array
    {
        return [
            MenuItem::make('{$pluralEscaped}')
                ->icon('{$iconEscaped}')
                ->order({$order}),

            MenuItem::make('{$entryLabelEscaped}')
                ->url('/'.ltrim(module_route('{$moduleRouteKeyEscaped}'), '/'))
                ->path(['{$pluralEscaped}'])
                ->order({$order}),
        ];
    }
PHP;
    }

    protected function createEntryOperationClass(string $name, string $base): void
    {
        if ($this->entryView === 'calendar') {
            File::put(
                $base."/Panel/{$name}CalendarOperation.php",
                $this->renderStub('svarium.module.calendar.operation.php.stub', $name)
            );
        }

        if ($this->entryView === 'page') {
            File::put(
                $base."/Panel/{$name}PageOperation.php",
                $this->renderStub('svarium.module.page.operation.php.stub', $name)
            );
        }
    }

    protected function moduleSlug(string $name): string
    {
        return (string) str($name)
            ->snake()
            ->replace('_', '')
            ->plural()
            ->lower()
            ->toString();
    }

    protected function buildResourceAccessMethods(): string
    {
        if ($this->resourceMode === 'crud') {
            return '';
        }

        $create = $this->resourceActionEnabled('create');
        $preview = $this->resourceActionEnabled('preview');
        $edit = $this->resourceActionEnabled('edit');
        $duplicate = $this->resourceActionEnabled('duplicate');
        $delete = $this->resourceActionEnabled('delete');

        $template = <<<'PHP'

    public function canCreate(PanelContext $context): bool
    {
        return {{CanCreate}};
    }

    public function canEdit(PanelContext $context): bool
    {
        return {{CanEdit}};
    }

    public function canDuplicate(PanelContext $context): bool
    {
        return {{CanDuplicate}};
    }

    public function canPreview(PanelContext $context): bool
    {
        return {{CanPreview}};
    }

    public function canDelete(PanelContext $context): bool
    {
        return {{CanDelete}};
    }
PHP;

        return strtr($template, [
            '{{CanCreate}}' => $this->phpBool($create),
            '{{CanEdit}}' => $this->phpBool($edit),
            '{{CanDuplicate}}' => $this->phpBool($duplicate),
            '{{CanPreview}}' => $this->phpBool($preview),
            '{{CanDelete}}' => $this->phpBool($delete),
        ]);
    }

    protected function buildTableDefaultActionsMode(): string
    {
        if ($this->resourceMode !== 'custom' && $this->resourceMode !== 'list_delete') {
            return '';
        }

        return "            ->withoutDefaultActions()";
    }

    protected function buildTableHeaderCreateAction(): string
    {
        if (
            ($this->resourceMode === 'custom' || $this->resourceMode === 'list_delete')
            && ! $this->resourceActionEnabled('create')
        ) {
            return '';
        }

        return <<<'PHP'
            Action::create()
                ->variant('outline')
                ->size('sm'),
PHP;
    }

    protected function buildTableRowActions(): string
    {
        if ($this->resourceMode === 'custom' || $this->resourceMode === 'list_delete') {
            $actions = [];

            if ($this->resourceActionEnabled('preview')) {
                $actions[] = '            Action::view(),';
            }

            if ($this->resourceActionEnabled('edit')) {
                $actions[] = '            Action::edit(),';
            }

            if ($this->resourceActionEnabled('duplicate')) {
                $actions[] = '            Action::duplicate(),';
            }

            if ($this->resourceActionEnabled('delete')) {
                $actions[] = '            Action::delete(),';
            }

            return implode("\n", $actions);
        }

        return <<<'PHP'
            Action::view(),
            Action::edit(),
            Action::duplicate(),
            Action::delete(),
PHP;
    }

    protected function randomLucideIcon(): string
    {
        $icons = [
            'lucide:users',
            'lucide:user',
            'lucide:briefcase',
            'lucide:file-text',
            'lucide:folder',
            'lucide:building-2',
            'lucide:clipboard-list',
            'lucide:stethoscope',
            'lucide:calendar',
            'lucide:chart-column',
        ];

        return $icons[array_rand($icons)];
    }
}
