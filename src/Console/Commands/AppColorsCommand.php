<?php

namespace Upsoftware\Svarium\Console\Commands;

use RuntimeException;
use Upsoftware\Svarium\Traits\HasTailwindColor;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

class AppColorsCommand extends CoreCommand
{
    use HasTailwindColor;

    /**
     * @var array{light_color: string, light_shade: int, dark_color: string, dark_shade: int}|null
     */
    protected ?array $lastPrimarySelection = null;

    protected $signature = 'svarium:app.colors
        {--file= : Ścieżka do app.css}
        {--initialize : Utwórz/Nadpisz app.css ze stuba i ustaw PRIMARY/PRIMARY_DARK}
        {--force : Wymuś nadpisanie pliku przy --initialize}
        {--skip-primary : Pomiń zmianę PRIMARY/PRIMARY_DARK}
        {--primary-color= : Kolor primary (light), np. amber}
        {--primary-shade= : Odcień primary (light), np. 500}
        {--primary-dark-color= : Kolor primary (dark), np. amber}
        {--primary-dark-shade= : Odcień primary (dark), np. 500}
        {--tone= : Tonacja neutralna (slate|gray|zinc|neutral|stone|taupe|mauve|mist|olive)}';

    protected $description = 'Zmienia neutralną tonację kolorów (OKLCH) w app.css dla :root i .dark';

    public function handle(): int
    {
        $cssPath = $this->resolveCssPath((string) $this->option('file'));
        $initialized = false;

        $initializeResult = true;
        if ((bool) $this->option('initialize')) {
            $initializeResult = $this->initializeCssFromStub($cssPath);
            $initialized = $initializeResult === true;
        }

        if ($initializeResult === false) {
            return self::FAILURE;
        }

        if ($initializeResult === null) {
            return self::SUCCESS;
        }

        if (! is_file($cssPath)) {
            $this->error('Nie znaleziono pliku CSS: '.$cssPath);

            return self::FAILURE;
        }

        $original = (string) file_get_contents($cssPath);
        $updated = $original;

        if (! $initialized) {
            $primarySelection = $this->resolvePrimarySelection();
            if ($primarySelection === false) {
                return self::FAILURE;
            }

            if (is_array($primarySelection)) {
                $updated = $this->replaceTokensInSelector($updated, ':root', [
                    'primary' => $primarySelection['light'],
                ]);

                $updated = $this->replaceTokensInSelector($updated, '.dark', [
                    'primary' => $primarySelection['dark'],
                ]);
            }
        }

        $availableTones = $this->toneLabels();
        $tone = strtolower(trim((string) $this->option('tone')));
        $defaultTone = $this->defaultTone();

        if ($tone === '' || ! array_key_exists($tone, $availableTones)) {
            if ($this->input->isInteractive()) {
                $tone = (string) select(
                    'Wybierz tonację kolorystyczną',
                    $availableTones,
                    $defaultTone
                );
            } else {
                $tone = $defaultTone;
            }
        }

        if (! array_key_exists($tone, $availableTones)) {
            $this->error('Nieprawidłowa tonacja: '.$tone);

            return self::FAILURE;
        }

        $scale = $this->toneScale($tone);
        $tokens = $this->buildTokenMap($scale);

        $updated = $this->replaceTokensInSelector($updated, ':root', $tokens['root']);
        $updated = $this->replaceTokensInSelector($updated, '.dark', $tokens['dark']);

        if ($updated === $original) {
            $this->persistColorDefaults($cssPath, $tone);
            $this->info('Brak zmian w pliku (tokeny już mają wybraną tonację).');
            $this->line('Tonacja: '.$tone);
            $this->line('Plik: '.$cssPath);

            return self::SUCCESS;
        }

        file_put_contents($cssPath, $updated);
        $this->persistColorDefaults($cssPath, $tone);

        $this->info('Zaktualizowano kolory neutralne w app.css.');
        $this->line('Tonacja: '.$tone);
        $this->line('Plik: '.$cssPath);

        return self::SUCCESS;
    }

    /**
     * @return array{light: string, dark: string}|false|null
     */
    protected function resolvePrimarySelection(): array|false|null
    {
        if ((bool) $this->option('skip-primary')) {
            return null;
        }

        $palette = $this->tailwindPalette();
        $colors = $this->tailwindColors();
        $shades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];
        $defaults = $this->defaultPrimaryConfig();

        $optionPrimaryColor = strtolower(trim((string) $this->option('primary-color')));
        $optionPrimaryShade = $this->normalizeShadeOption((string) $this->option('primary-shade'));
        $optionPrimaryDarkColor = strtolower(trim((string) $this->option('primary-dark-color')));
        $optionPrimaryDarkShade = $this->normalizeShadeOption((string) $this->option('primary-dark-shade'));

        $hasPrimaryOptions = $optionPrimaryColor !== '' || $optionPrimaryShade !== null || $optionPrimaryDarkColor !== '' || $optionPrimaryDarkShade !== null;

        if (! $hasPrimaryOptions && ! $this->input->isInteractive()) {
            return null;
        }

        if (! $hasPrimaryOptions) {
            $changePrimary = confirm('Czy chcesz zmienić kolor PRIMARY?', true, 'Tak', 'Nie');
            if (! $changePrimary) {
                return null;
            }
        }

        $lightColor = $optionPrimaryColor;
        if ($lightColor === '') {
            if ($this->input->isInteractive()) {
                $lightColor = (string) select(
                    'Wybierz kolor primary jasny (light)',
                    $colors,
                    $defaults['light_color']
                );
            } else {
                $lightColor = $defaults['light_color'];
            }
        }

        if (! isset($palette[$lightColor])) {
            $this->error("Nieprawidłowy kolor primary (light): {$lightColor}");

            return false;
        }

        $lightShade = $optionPrimaryShade;
        if ($lightShade === null) {
            if ($this->input->isInteractive()) {
                $lightShade = (int) select(
                    'Wybierz odcień primary jasny (light)',
                    $shades,
                    $defaults['light_shade']
                );
            } else {
                $lightShade = $defaults['light_shade'];
            }
        }

        if (! isset($palette[$lightColor][$lightShade])) {
            $this->error("Nieprawidłowy odcień primary (light): {$lightShade}");

            return false;
        }

        $sameDark = false;
        if ($optionPrimaryDarkColor === '' && $optionPrimaryDarkShade === null && $this->input->isInteractive()) {
            $sameDark = confirm('Czy ten sam kolor dodać jako primary ciemny?', true, 'Tak', 'Nie');
        }

        if ($sameDark) {
            $darkColor = $lightColor;
            $darkShade = $lightShade;
        } else {
            $darkColor = $optionPrimaryDarkColor;
            if ($darkColor === '') {
                if ($this->input->isInteractive()) {
                    $darkColor = (string) select(
                        'Wybierz kolor primary ciemny (dark)',
                        $colors,
                        $defaults['dark_color']
                    );
                } else {
                    $darkColor = $defaults['dark_color'];
                }
            }

            if (! isset($palette[$darkColor])) {
                $this->error("Nieprawidłowy kolor primary (dark): {$darkColor}");

                return false;
            }

            $darkShade = $optionPrimaryDarkShade;
            if ($darkShade === null) {
                if ($this->input->isInteractive()) {
                    $darkShade = (int) select(
                        'Wybierz odcień primary ciemny (dark)',
                        $shades,
                        $defaults['dark_shade']
                    );
                } else {
                    $darkShade = $defaults['dark_shade'];
                }
            }

            if (! isset($palette[$darkColor][$darkShade])) {
                $this->error("Nieprawidłowy odcień primary (dark): {$darkShade}");

                return false;
            }
        }

        $light = (string) $palette[$lightColor][$lightShade];
        $dark = (string) $palette[$darkColor][$darkShade];
        $this->lastPrimarySelection = [
            'light_color' => $lightColor,
            'light_shade' => (int) $lightShade,
            'dark_color' => $darkColor,
            'dark_shade' => (int) $darkShade,
        ];

        $this->info('PRIMARY light: '.$lightColor.' ('.$lightShade.') - '.$light);
        $this->info('PRIMARY dark: '.$darkColor.' ('.$darkShade.') - '.$dark);

        return [
            'light' => $light,
            'dark' => $dark,
        ];
    }

    protected function normalizeShadeOption(string $value): ?int
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        $parsed = (int) $raw;
        $allowed = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

        return in_array($parsed, $allowed, true) ? $parsed : null;
    }

    protected function initializeCssFromStub(string $cssPath): bool|null
    {
        $stubPath = __DIR__.'/../../stubs/app.css.stub';

        if (! is_file($stubPath)) {
            $this->error('Nie znaleziono stuba CSS: '.$stubPath);

            return false;
        }

        if (is_file($cssPath) && ! (bool) $this->option('force')) {
            $overwrite = confirm('Czy nadpisać plik: '.$cssPath, false, 'Tak', 'Nie');
            if (! $overwrite) {
                $this->info('Pominięto nadpisywanie app.css.');

                return null;
            }
        }

        $palette = $this->tailwindPalette();
        $colors = $this->tailwindColors();
        $shades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];
        $defaults = $this->defaultPrimaryConfig();

        if ($this->input->isInteractive()) {
            $tailwindColor = (string) select(
                'Wybierz kolor podstawowy jasny (primary)',
                $colors,
                $defaults['light_color']
            );
            $tailwindColorPalette = (int) select(
                'Wybierz odcień',
                $shades,
                $defaults['light_shade']
            );
        } else {
            $tailwindColor = $defaults['light_color'];
            $tailwindColorPalette = $defaults['light_shade'];
        }
        $primary = (string) ($palette[$tailwindColor][$tailwindColorPalette] ?? '');

        if ($primary === '') {
            $this->error('Nie udało się ustalić koloru PRIMARY.');

            return false;
        }

        $sameColor = false;
        if ($this->input->isInteractive()) {
            $sameColor = confirm('Czy ten sam kolor dodać jako kolor ciemny?', $defaults['dark_matches_light'], 'Tak', 'Nie');
        }
        if ($sameColor) {
            $primaryDark = $primary;
            $tailwindColorDark = $tailwindColor;
            $tailwindColorDarkPalette = $tailwindColorPalette;
        } else {
            if ($this->input->isInteractive()) {
                $tailwindColorDark = (string) select(
                    'Wybierz kolor podstawowy ciemny (primary)',
                    $colors,
                    $defaults['dark_color']
                );
                $tailwindColorDarkPalette = (int) select(
                    'Wybierz odcień',
                    $shades,
                    $defaults['dark_shade']
                );
            } else {
                $tailwindColorDark = $defaults['dark_color'];
                $tailwindColorDarkPalette = $defaults['dark_shade'];
            }
            $primaryDark = (string) ($palette[$tailwindColorDark][$tailwindColorDarkPalette] ?? '');

            if ($primaryDark === '') {
                $this->error('Nie udało się ustalić koloru PRIMARY_DARK.');

                return false;
            }
        }

        $this->info('Kolor podstawowy (jasny/light): '.$tailwindColor.' ('.$tailwindColorPalette.') - '.$primary);
        $this->info('Kolor podstawowy (ciemny/dark): '.$tailwindColorDark.' ('.$tailwindColorDarkPalette.') - '.$primaryDark);
        $this->lastPrimarySelection = [
            'light_color' => $tailwindColor,
            'light_shade' => (int) $tailwindColorPalette,
            'dark_color' => $tailwindColorDark,
            'dark_shade' => (int) $tailwindColorDarkPalette,
        ];

        $stubContent = file_get_contents($stubPath);
        if (! is_string($stubContent) || $stubContent === '') {
            $this->error('Nie udało się odczytać zawartości stuba app.css.');

            return false;
        }

        $cssContent = strtr($stubContent, [
            '{{PRIMARY}}' => $primary,
            '{{PRIMARY_DARK}}' => $primaryDark,
        ]);

        $directory = dirname($cssPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($cssPath, $cssContent);
        $this->info('Utworzono/Nadpisano plik: '.$cssPath);

        return true;
    }

    protected function persistColorDefaults(string $cssPath, string $tone): void
    {
        $this->addConfigKey('upsoftware.php', 'colors.css_file', $this->pathForConfig($cssPath), true);
        $this->addConfigKey('upsoftware.php', 'colors.tone', $tone, true);

        if (is_array($this->lastPrimarySelection)) {
            $this->addConfigKey('upsoftware.php', 'colors.primary.light.color', $this->lastPrimarySelection['light_color'], true);
            $this->addConfigKey('upsoftware.php', 'colors.primary.light.shade', $this->lastPrimarySelection['light_shade'], true);
            $this->addConfigKey('upsoftware.php', 'colors.primary.dark.color', $this->lastPrimarySelection['dark_color'], true);
            $this->addConfigKey('upsoftware.php', 'colors.primary.dark.shade', $this->lastPrimarySelection['dark_shade'], true);
        }
    }

    protected function pathForConfig(string $cssPath): string
    {
        $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (str_starts_with($cssPath, $base)) {
            return ltrim(substr($cssPath, strlen($base)), DIRECTORY_SEPARATOR);
        }

        return $cssPath;
    }

    protected function resolveCssPath(string $rawPath): string
    {
        $path = trim($rawPath);

        if ($path === '') {
            $path = $this->defaultCssFile();
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }

    protected function defaultCssFile(): string
    {
        $path = trim((string) config('upsoftware.colors.css_file', 'resources/css/app.css'));

        return $path !== '' ? $path : 'resources/css/app.css';
    }

    protected function defaultTone(): string
    {
        $labels = $this->toneLabels();
        $configured = strtolower(trim((string) config('upsoftware.colors.tone', 'zinc')));

        if (array_key_exists($configured, $labels)) {
            return $configured;
        }

        return array_key_exists('zinc', $labels) ? 'zinc' : (string) array_key_first($labels);
    }

    /**
     * @return array{light_color: string, light_shade: int, dark_color: string, dark_shade: int, dark_matches_light: bool}
     */
    protected function defaultPrimaryConfig(): array
    {
        $palette = $this->tailwindPalette();
        $fallbackColor = isset($palette['blue']) ? 'blue' : (string) array_key_first($palette);
        $fallbackColor = $fallbackColor !== '' ? $fallbackColor : 'blue';

        $lightColor = strtolower(trim((string) config('upsoftware.colors.primary.light.color', $fallbackColor)));
        if ($lightColor === '' || ! isset($palette[$lightColor])) {
            $lightColor = $fallbackColor;
        }

        $lightShade = $this->normalizeShadeOption((string) config('upsoftware.colors.primary.light.shade', 500)) ?? 500;
        if (! isset($palette[$lightColor][$lightShade])) {
            $lightShade = isset($palette[$lightColor][500]) ? 500 : (int) array_key_first($palette[$lightColor]);
        }

        $darkColor = strtolower(trim((string) config('upsoftware.colors.primary.dark.color', $lightColor)));
        if ($darkColor === '' || ! isset($palette[$darkColor])) {
            $darkColor = $lightColor;
        }

        $darkShade = $this->normalizeShadeOption((string) config('upsoftware.colors.primary.dark.shade', $lightShade)) ?? $lightShade;
        if (! isset($palette[$darkColor][$darkShade])) {
            $darkShade = isset($palette[$darkColor][500]) ? 500 : (int) array_key_first($palette[$darkColor]);
        }

        return [
            'light_color' => $lightColor,
            'light_shade' => $lightShade,
            'dark_color' => $darkColor,
            'dark_shade' => $darkShade,
            'dark_matches_light' => $lightColor === $darkColor && $lightShade === $darkShade,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function toneLabels(): array
    {
        return [
            'slate' => 'slate',
            'gray' => 'gray',
            'zinc' => 'zinc',
            'neutral' => 'neutral',
            'stone' => 'stone',
            'taupe' => 'taupe',
            'mauve' => 'mauve',
            'mist' => 'mist',
            'olive' => 'olive',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function toneScale(string $tone): array
    {
        $palette = $this->tailwindPalette();

        if (isset($palette[$tone]) && is_array($palette[$tone])) {
            return $palette[$tone];
        }

        return match ($tone) {
            'taupe' => [
                50 => 'oklch(98.6% 0.002 40)',
                100 => 'oklch(97.1% 0.003 40)',
                200 => 'oklch(92.5% 0.006 40)',
                300 => 'oklch(87.2% 0.01 40)',
                400 => 'oklch(70.8% 0.017 40)',
                500 => 'oklch(55.7% 0.02 40)',
                600 => 'oklch(44.8% 0.018 40)',
                700 => 'oklch(37.6% 0.015 40)',
                800 => 'oklch(27.5% 0.01 40)',
                900 => 'oklch(21.1% 0.008 40)',
                950 => 'oklch(14.5% 0.005 40)',
            ],
            'mauve' => [
                50 => 'oklch(98.6% 0.003 315)',
                100 => 'oklch(97.0% 0.006 315)',
                200 => 'oklch(92.4% 0.012 315)',
                300 => 'oklch(87.1% 0.019 315)',
                400 => 'oklch(71.0% 0.03 315)',
                500 => 'oklch(55.9% 0.034 315)',
                600 => 'oklch(45.0% 0.03 315)',
                700 => 'oklch(37.7% 0.025 315)',
                800 => 'oklch(27.7% 0.018 315)',
                900 => 'oklch(21.2% 0.014 315)',
                950 => 'oklch(14.6% 0.009 315)',
            ],
            'mist' => [
                50 => 'oklch(98.7% 0.003 240)',
                100 => 'oklch(97.1% 0.006 240)',
                200 => 'oklch(92.6% 0.012 240)',
                300 => 'oklch(87.4% 0.019 240)',
                400 => 'oklch(71.1% 0.03 240)',
                500 => 'oklch(56.0% 0.034 240)',
                600 => 'oklch(45.1% 0.031 240)',
                700 => 'oklch(37.8% 0.027 240)',
                800 => 'oklch(27.8% 0.02 240)',
                900 => 'oklch(21.3% 0.016 240)',
                950 => 'oklch(14.7% 0.011 240)',
            ],
            'olive' => [
                50 => 'oklch(98.6% 0.004 120)',
                100 => 'oklch(97.0% 0.008 120)',
                200 => 'oklch(92.4% 0.016 120)',
                300 => 'oklch(87.1% 0.026 120)',
                400 => 'oklch(70.8% 0.04 120)',
                500 => 'oklch(55.6% 0.045 120)',
                600 => 'oklch(44.8% 0.04 120)',
                700 => 'oklch(37.5% 0.033 120)',
                800 => 'oklch(27.5% 0.024 120)',
                900 => 'oklch(21.1% 0.018 120)',
                950 => 'oklch(14.6% 0.012 120)',
            ],
            default => throw new RuntimeException('Brak skali kolorów dla tonacji: '.$tone),
        };
    }

    /**
     * @param  array<int, string>  $scale
     * @return array{root: array<string, string>, dark: array<string, string>}
     */
    protected function buildTokenMap(array $scale): array
    {
        $root = [
            'background' => 'oklch(1 0 0)',
            'foreground' => $scale[950],
            'card' => 'oklch(1 0 0)',
            'card-foreground' => $scale[950],
            'popover' => 'oklch(1 0 0)',
            'popover-foreground' => $scale[950],
            'secondary' => $scale[100],
            'secondary-foreground' => $scale[900],
            'muted' => $scale[100],
            'muted-foreground' => $scale[500],
            'accent' => $scale[100],
            'accent-foreground' => $scale[900],
            'border' => $scale[200],
            'input' => $scale[200],
            'ring' => $scale[500],
            'sidebar' => $scale[50],
            'sidebar-foreground' => $scale[950],
            'sidebar-primary' => $scale[900],
            'sidebar-primary-foreground' => 'oklch(0.985 0 0)',
            'sidebar-accent' => $scale[100],
            'sidebar-accent-foreground' => $scale[900],
            'sidebar-border' => $scale[200],
            'sidebar-ring' => $scale[500],
        ];

        $dark = [
            'background' => $scale[950],
            'foreground' => 'oklch(0.985 0 0)',
            'card' => $scale[900],
            'card-foreground' => 'oklch(0.985 0 0)',
            'popover' => $scale[900],
            'popover-foreground' => 'oklch(0.985 0 0)',
            'secondary' => $scale[800],
            'secondary-foreground' => 'oklch(0.985 0 0)',
            'muted' => $scale[800],
            'muted-foreground' => $scale[400],
            'accent' => $scale[800],
            'accent-foreground' => 'oklch(0.985 0 0)',
            'border' => 'oklch(1 0 0 / 10%)',
            'input' => 'oklch(1 0 0 / 15%)',
            'ring' => $scale[400],
            'sidebar' => $scale[900],
            'sidebar-foreground' => 'oklch(0.985 0 0)',
            'sidebar-primary' => $scale[600],
            'sidebar-primary-foreground' => 'oklch(0.985 0 0)',
            'sidebar-accent' => $scale[800],
            'sidebar-accent-foreground' => 'oklch(0.985 0 0)',
            'sidebar-border' => 'oklch(1 0 0 / 10%)',
            'sidebar-ring' => $scale[400],
        ];

        return [
            'root' => $root,
            'dark' => $dark,
        ];
    }

    /**
     * @param  array<string, string>  $tokens
     */
    protected function replaceTokensInSelector(string $css, string $selector, array $tokens): string
    {
        $pattern = '/(^[ \t]*'.preg_quote($selector, '/').'\s*\{)(.*?)(^[ \t]*\})/ms';

        $result = preg_replace_callback($pattern, function (array $matches) use ($tokens): string {
            $opening = (string) ($matches[1] ?? '');
            $body = (string) ($matches[2] ?? '');
            $closing = (string) ($matches[3] ?? '');
            $indent = $this->detectVariableIndentation($body);

            foreach ($tokens as $token => $value) {
                $body = $this->replaceOrAppendCssVariable($body, $token, $value, $indent);
            }

            return $opening.$body.$closing;
        }, $css, 1, $count);

        if (! is_string($result) || $count < 1) {
            throw new RuntimeException("Nie znaleziono sekcji CSS: {$selector}");
        }

        return $result;
    }

    protected function detectVariableIndentation(string $body): string
    {
        if (preg_match('/\n([ \t]+)--[a-z0-9\-]+\s*:/i', $body, $matches) === 1) {
            return (string) ($matches[1] ?? '    ');
        }

        return '    ';
    }

    protected function replaceOrAppendCssVariable(string $body, string $variable, string $value, string $indent): string
    {
        $pattern = '/(^[ \t]*--'.preg_quote($variable, '/').'\s*:\s*)([^;]+)(;[ \t]*$)/mi';
        $replacement = '$1'.$value.'$3';
        $updated = preg_replace($pattern, $replacement, $body, 1, $count);

        if (is_string($updated) && $count > 0) {
            return $updated;
        }

        if (! str_ends_with($body, "\n")) {
            $body .= "\n";
        }

        return $body.$indent.'--'.$variable.': '.$value.";\n";
    }
}
