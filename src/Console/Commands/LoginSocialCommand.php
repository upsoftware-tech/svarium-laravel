<?php

namespace Upsoftware\Svarium\Console\Commands;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

class LoginSocialCommand extends CoreCommand
{
    protected $signature = 'svarium:login.socials';

    protected $description = 'Iniciuje aplikację (dodaje niezbędną konfigurację)';

    protected $default_services_config = [
        'client_id' => '@env(PROVIDER_CLIENT_ID)',
        'client_secret' => '@env(PROVIDER_CLIENT_SECRET)',
        'redirect' => '@env(PROVIDER_REDIRECT_URI)',
    ];

    protected array $socials = [
        'google' => ['id' => 'google', 'icon' => 'logos:google-icon', 'label' => 'Login with Google', 'provider' => 'Google'],
        'facebook' => ['id' => 'facebook', 'icon' => 'logos:facebook', 'label' => 'Login with Facebook', 'provider' => 'Facebook'],
        'apple' => ['id' => 'apple', 'icon' => 'logos:apple', 'label' => 'Login with Apple', 'provider' => 'Apple'],
        'linkedin' => ['id' => 'linkedin', 'icon' => 'logos:linkedin-icon', 'label' => 'Login with LinkedIn', 'provider' => 'LinkedIn'],
        'microsoft' => ['id' => 'microsoft', 'icon' => 'logos:microsoft-icon', 'label' => 'Login with Microsoft', 'provider' => 'Microsoft'],
        'zoom' => ['id' => 'zoom', 'icon' => 'logos:zoom-icon', 'label' => 'Login with Zoom', 'provider' => 'Zoom'],
    ];

    protected array $providerPackages = [
        'google'    => 'socialiteproviders/google',
        'microsoft' => 'socialiteproviders/microsoft',
        'facebook'  => 'socialiteproviders/facebook',
        'zoom'      => 'socialiteproviders/zoom',
        'linkedin'  => 'socialiteproviders/linkedin',
        'apple'     => 'socialiteproviders/apple',
    ];

    protected function addSocialiteListener(string $provider, string $class): void
    {
        $providerPath = base_path('app/Providers/AppServiceProvider.php');

        if (!file_exists($providerPath)) {
            $this->error("Nie znaleziono AppServiceProvider.php");
            return;
        }

        $content = file_get_contents($providerPath);

        if (!str_contains($content, 'use SocialiteProviders\Manager\SocialiteWasCalled')) {
            $content = preg_replace(
                '/namespace\s+App\\\Providers\s*;/',
                "$0\nuse SocialiteProviders\\Manager\\SocialiteWasCalled;",
                $content,
                1
            );
        }

        if (!str_contains($content, 'use Illuminate\Support\Facades\Event')) {
            $content = preg_replace(
                '/namespace\s+App\\\Providers\s*;/',
                "$0\nuse Illuminate\\Support\\Facades\\Event;",
                $content,
                1
            );
        }

        if (str_contains($content, "extendSocialite('$provider'")) {
            $this->info("Listener dla '$provider' już istnieje w AppServiceProvider.");
            return;
        }

        $existingBlockPattern = '/(Event::listen\s*\(\s*function\s*\(\s*\\\\?SocialiteWasCalled\s+\$event\s*\)\s*\{)(.*?)(\}\s*\)\s*;)/s';

        if (preg_match($existingBlockPattern, $content)) {

            $newLine = "\n\t\t\t\$event->extendSocialite('$provider', $class);";

            $newContent = preg_replace(
                $existingBlockPattern,
                "$1$2$newLine$3",
                $content,
                1
            );

            $this->info("Dopisano '$provider' do istniejącego bloku Event::listen.");

        } else {
            if (!preg_match('/public function boot\(\)\s*:\s*void\s*{/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                if (!preg_match('/public function boot\(\)\s*{/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                    $this->error("Nie znaleziono metody boot() w AppServiceProvider");
                    return;
                }
            }

            $bootPos = strpos($content, '{', $matches[0][1]) + 1;

            $toAdd = "\n        Event::listen(function (SocialiteWasCalled \$event) {\n";
            $toAdd .= "            \$event->extendSocialite('$provider', $class);\n";
            $toAdd .= "        });\n";

            $newContent = substr($content, 0, $bootPos) . $toAdd . substr($content, $bootPos);

            $this->info("Utworzono nowy blok Event::listen dla '$provider'.");
        }

        file_put_contents($providerPath, $newContent);
    }

    protected function service_config($provider, $add = []) {
        $config = array_map(function ($value) use ($provider) {
            return str_replace('PROVIDER', strtoupper($provider), $value);
        }, $this->default_services_config);

        if ($add) {
            foreach($add as $key => $value) {
                $config[$key] = $value;
            }
        }
        return $config;
    }
    public function handle()
    {
        $allOptions = [
            'google'        => 'Google',
            'facebook'      => 'Facebook',
            'apple'         => 'Apple',
            'microsoft'     => 'Microsoft',
            'linkedin'      => 'LinkedIn',
            'zoom'          => 'Zoom',
        ];

        $setting = $this->settingModel::getSettingGlobal('login.config', []);

        $socials = $setting["socials"] ?? [];
        $cols = $setting["cols"] ?? 2;
        $minimal = isset($setting["minimal"]) ? (string)$setting["minimal"] : "false";
        $minimal = $minimal ? "true" : "false";
        $orLabel = $setting["orLabel"] ?? "or";

        $onlySocialName = $setting["onlySocialName"] ?? "false";
        $onlySocialName = $onlySocialName ? "true" : "false";

        $default = array_column($socials, 'id');

        $selectedProviders = multiselect(
            label: 'Wybierz metody logowania Social Media, które chcesz włączyć:',
            options: $allOptions,
            default: $default,
            hint: 'Użyj spacji do zaznaczania, Enter aby zatwierdzić.'
        );

        if (! empty($selectedProviders)) {
            $this->info('Wybrano: ' . implode(', ', $selectedProviders));

            $pool = $selectedProviders;

            $total = count($selectedProviders);

            for ($i = 1; $i <= $total; $i++) {

                if (count($pool) === 1) {
                    $lastItem = array_values($pool)[0];
                    $sortedProviders[] = $lastItem;
                    $this->info("Automatycznie przypisano pozycję #$i: " . $allOptions[$lastItem]);
                    break;
                }

                $optionsForStep = [];
                foreach ($pool as $key) {
                    $optionsForStep[$key] = $allOptions[$key];
                }

                $choice = select(
                    label: "Wybierz metodę dla pozycji #$i:",
                    options: $optionsForStep
                );
                $sortedProviders[] = $choice;
                $pool = array_diff($pool, [$choice]);
            }

            $selectedProviders = $sortedProviders;

            $items = [];
            foreach($selectedProviders as $provider) {
                $items[] = $this->socials[$provider];

                if (array_key_exists($provider, $this->providerPackages)) {
                    $package = $this->providerPackages[$provider];

                    $this->newLine();
                    $this->info("Dostawca '$provider' wymaga dodatkowego pakietu.");

                    if ($this->confirm("Czy zainstalować $package teraz?", false)) {
                        $this->info("Instalowanie $package...");
                        passthru("composer require $package");

                        $providerClassMap = [
                            'google'    => '\SocialiteProviders\Google\Provider::class',
                            'facebook'  => '\SocialiteProviders\Facebook\Provider::class',
                            'apple'     => '\SocialiteProviders\Apple\Provider::class',
                            'microsoft'=> '\SocialiteProviders\Microsoft\Provider::class',
                            'linkedin'  => '\SocialiteProviders\LinkedIn\Provider::class',
                            'zoom'      => '\SocialiteProviders\Zoom\Provider::class',
                        ];

                        if (isset($providerClassMap[$provider])) {
                            $this->addSocialiteListener($provider, $providerClassMap[$provider]);
                        }
                    }
                }

                $this->addEnvKey(strtoupper($provider).'_CLIENT_ID', '', false, true);
                $this->addEnvKey(strtoupper($provider).'_CLIENT_SECRET', '');
                $this->addEnvKey(strtoupper($provider).'_REDIRECT_URI', '"${APP_URL}/auth/'.$provider.'/callback"');

                $add = [];
                if ($provider === 'microsoft') {
                    $add['proxy'] = '@env(PROXY)';
                }
                $config = $this->service_config($provider);
                $this->addConfigKey('services.php', $provider, $config);
            }



            $minimal = select('Ustaw widok ikon bez tytułow (minimal)', ['true' => 'Tak', 'false' => 'Nie'], $minimal);
            if ($minimal === "false") {
                $cols = select('Wybierz liczbę kolumn', [1, 2, 3], $cols);
                $onlySocialName = select('Wstaw tylko nazwę portalu społecznościowego', ['true' => 'Tak', 'false' => 'Nie'], $onlySocialName);
            }

            $orLabel = $this->ask('Tytuł nad logami logowania z Social Media', $orLabel);

            $this->settingModel::setSettingGlobal('login.config', [
                'socials' => $items,
                'cols' => $cols,
                'minimal' => $minimal === 'true',
                'onlySocialName' => $onlySocialName === 'true',
                'orLabel' => $orLabel
            ]);
        }

        $this->info('Gotowe!');
    }
}
