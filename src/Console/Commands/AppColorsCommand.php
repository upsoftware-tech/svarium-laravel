<?php

namespace Upsoftware\Svarium\Console\Commands;

use RuntimeException;
use Upsoftware\Svarium\Traits\HasTailwindColor;
use function Laravel\Prompts\select;

class AppColorsCommand extends CoreCommand
{
    use HasTailwindColor;

    protected $signature = 'svarium:app.colors
        {--file=resources/css/app.css : Ścieżka do app.css}
        {--tone= : Tonacja neutralna (slate|gray|zinc|neutral|stone|taupe|mauve|mist|olive)}';

    protected $description = 'Zmienia neutralną tonację kolorów (OKLCH) w app.css dla :root i .dark';

    public function handle(): int
    {
        $cssPath = $this->resolveCssPath((string) $this->option('file'));

        if (! is_file($cssPath)) {
            $this->error('Nie znaleziono pliku CSS: '.$cssPath);

            return self::FAILURE;
        }

        $availableTones = $this->toneLabels();
        $tone = strtolower(trim((string) $this->option('tone')));

        if ($tone === '' || ! array_key_exists($tone, $availableTones)) {
            $tone = (string) select(
                'Wybierz tonację kolorystyczną',
                $availableTones,
                'zinc'
            );
        }

        if (! array_key_exists($tone, $availableTones)) {
            $this->error('Nieprawidłowa tonacja: '.$tone);

            return self::FAILURE;
        }

        $scale = $this->toneScale($tone);
        $tokens = $this->buildTokenMap($scale);

        $original = (string) file_get_contents($cssPath);
        $updated = $this->replaceTokensInSelector($original, ':root', $tokens['root']);
        $updated = $this->replaceTokensInSelector($updated, '.dark', $tokens['dark']);

        if ($updated === $original) {
            $this->info('Brak zmian w pliku (tokeny już mają wybraną tonację).');
            $this->line('Tonacja: '.$tone);
            $this->line('Plik: '.$cssPath);

            return self::SUCCESS;
        }

        file_put_contents($cssPath, $updated);

        $this->info('Zaktualizowano kolory neutralne w app.css.');
        $this->line('Tonacja: '.$tone);
        $this->line('Plik: '.$cssPath);

        return self::SUCCESS;
    }

    protected function resolveCssPath(string $rawPath): string
    {
        $path = trim($rawPath);

        if ($path === '') {
            $path = 'resources/css/app.css';
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
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

