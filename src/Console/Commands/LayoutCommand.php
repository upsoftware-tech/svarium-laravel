<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Upsoftware\Svarium\Traits\HasTailwindColor;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class LayoutCommand extends Command
{
    use HasTailwindColor;

    protected $settingModel;
    protected $navigationModel;

    public function __construct()
    {
        parent::__construct();
        $this->settingModel = config('svarium.models.setting', \Upsoftware\Svarium\Models\Setting::class);
        $this->navigationModel = config('svarium.models.navigation', \Upsoftware\Svarium\Models\Navigation::class);
    }

    protected $signature = 'svarium:layout';

    protected $description = '(Re)konfiguracja układu panelu';

    protected function selectColors($label = 'Wybierz kolor'): string
    {
        $tailwindColors = $this->tailwindColors();
        return select($label, $tailwindColors);
    }

    protected function getComponent($label, array $component = []) {
        $componentName = text($label, '', $component["name"] ?? '');

        $props = [];
        if ($componentName === 'NavigationVertical') {
            $navigations = $this->navigationModel::whereNull('parent_id')->orderBy('label')->get()->mapWithKeys(function($item) { return [$item->id => $item->label]; })->toArray();
            $props['navigation_id'] = select('Wybierz menu nawigacyjne', array_merge(['' => 'Pomiń - nie dodawaj menu'], $navigations), $component["props"]["navigation_id"] ?? '');
        }

        if (isset($component["props"])) {
            foreach ($component["props"] as $key => $value) {
                if ($key !== "navigation_id") {
                    $newProps = text("Parametr: " . $key, "", "{$key}: {$value}");
                    $parts = explode(":", $newProps);
                    $props[trim($parts[0])] = trim($parts[1]);
                }
            }
        }

        $stop = false;
        while (!$stop) {
            if (!confirm('Dodać kolejne parametry do komponentu "'.$componentName.'"?', false, 'Tak', 'Nie')) {
                $stop = true;
            } else {
                $newProps = text("Wprowadź nowy parametr klucz: wartość (np. enabled: true)");
                $parts = explode(":", $newProps);
                $props[trim($parts[0])] = trim($parts[1]);
            }
        }

        return [
            'name' => $componentName,
            'props' => $props
        ];
    }

    public function handle()
    {
        $config_directory = svarium_config();
        $layout_directory = svarium_config('Layout');
        $areas = ['Panel', 'Web'];
        $components = ['Header', 'Content', 'Footer', 'Sidebar'];
        foreach ($areas as $area) {
            foreach ($components as $component) {
                $componentDir = svarium_config('Layout/' . $area . '/' . $component);
                if (!File::isDirectory($componentDir)) {
                    File::makeDirectory($componentDir, 0755, true);
                }
            }
        }

        $setting = $this->settingModel::getSettingGlobal('layout');

        $layout['theme']['enabled'] = confirm('Włączyć tryb jasny i ciemny?', $setting['theme']['enabled'] ?? false, 'Tak', 'Nie');

        $layout['logo']['default']['light'] = text('Ściezka do logo (dla trybu jasnego)', '', $setting['logo']['default']['light'] ?? '');
        $layout['logo']['default']['dark'] = text('Ściezka do logo (dla trybu ciemnego)', '', $setting['logo']['default']['dark'] ?? $layout['logo']['default']['light']);

        $layout['logo']['small']['light'] = text('Ściezka do logo pomniejszonego (dla trybu jasnego)', '', $setting['logo']['small']['light'] ?? $layout['logo']['default']['light']);
        $layout['logo']['small']['dark'] = text('Ściezka do logo pomniejszonego (dla trybu ciemnego)', '', $setting['logo']['small']['dark'] ?? $layout['logo']['default']['dark']);

        $layout['sidebar']['enabled'] = confirm('Włączyć sidebar?', $setting['sidebar']['enabled'] ?? true, 'Tak', 'Nie');

        if ($layout['sidebar']['enabled']) {
            $layout['sidebar']['width'] = (int) text('Szerokość sidebara (px)', '', $setting['sidebar']['width'] ?? 320);
            $layout['sidebar']['themeToggle'] = confirm('Pozwolić na zwijanie sidebara do wersji minimum tylko z ikonkami?', $setting['sidebar']['themeToggle'] ?? true, 'Tak', 'Nie');
            $layout['sidebar']['minimal'] = confirm('Domyślna wersja sidebaru', $setting['sidebar']['minimal'] ?? true, 'Zwinięta (minimalna)', 'Rozwinięta (pełna)');
            $layout['sidebar']['component'] = $this->getComponent('Komponent główny',$setting['sidebar']['component'] ?? ['name' => 'NavigationVertical']);

            $layout['sidebar']['position'] = select(
                'Pozycja sidebara',
                ['left' => 'Lewa strona', 'right' => 'Prawa strona'],
                $layout['sidebar']['position'] ?? 'left'
            );

            $layout['sidebar']['header']['enabled'] = confirm('Włączyć nagłówek w sidebarze?', $setting['sidebar']['header']['enabled'] ?? true, 'Tak', 'Nie');
            $layout['sidebar']['footer']['enabled'] = confirm('Włączyć stopkę w sidebarze?', $setting['sidebar']['footer']['enabled'] ?? true, 'Tak', 'Nie');
            if ($layout['sidebar']['footer']['enabled']) {
                $layout['sidebar']['footer']['component'] = $this->getComponent('Komponent w stopce w SideBar?', $setting['sidebar']['footer']['component'] ?? ['name' => 'SidebarUser']);
            }

            $layout['sidebar']['top']['enabled'] = confirm('Włączyć dodatkową strefę pod nagłowkiem w sidebarze?', $setting['sidebar']['top']['enabled'] ?? false, 'Tak', 'Nie');
            $layout['sidebar']['bottom']['enabled'] = confirm('Włączyć dodatkową strefę nad stopką w sidebarze?', $setting['sidebar']['bottom']['enabled'] ?? false, 'Tak', 'Nie');
            if ($layout['sidebar']['bottom']['enabled']) {
                $layout['sidebar']['bottom']['component'] = $this->getComponent('Komponent w dodatkowej strefie w SideBar', $setting['sidebar']['component'] ?? ['name' => 'NavigationVertical']);
            }
        }

        $layout['aside']['enabled'] = confirm('Włączyć dodatkowy panel boczny?', false, 'Tak', 'Nie');
        if ($layout['aside']['enabled']) {
            $layout['aside']['width'] = (int) text('Szerokość panelu bocznego (px)', 64);

            $layout['aside']['header']['enabled'] = confirm('Włączyć nagłówek w panelu bocznym?', true, 'Tak', 'Nie');
            $layout['aside']['footer']['enabled'] = confirm('Włączyć stopkę w panelu bocznym?', true, 'Tak', 'Nie');
        }

        $layout['header']['enabled'] = confirm('Włączyć nagłówek strony (górny pasek)?', false, 'Tak', 'Nie');

        $layout['content']['header']['enabled'] = confirm('Włączyć nagłówek nad treścią?', true, 'Tak', 'Nie');

        $layout['content']['footer']['enabled'] = confirm('Włączyć stopkę treści?', false, 'Tak', 'Nie');

        $layout['content']['appearance'] = [
            'border'  => confirm('Dodać obramowanie treści?', true, 'Tak', 'Nie'),
            'rounded' => confirm('Zaokrąglić rogi treści?', true, 'Tak', 'Nie'),
            'margin'  => confirm('Dodać marginesy wokół treści?', true, 'Tak', 'Nie'),
        ];

        $layout['footer']['enabled'] = confirm('Włączyć stopkę strony (dół strony)?', false, 'Tak', 'Nie');

        print_r($layout);
        $this->settingModel::setSettingGlobal('layout', $layout);
    }
}
