<?php

namespace Upsoftware\Svarium\Console\Commands;

use Upsoftware\Svarium\Traits\HasTailwindColor;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class InitCommand extends CoreCommand
{
    use HasTailwindColor;

    protected $signature = 'svarium:init';

    protected $description = 'Iniciuje aplikację (dodaje niezbędną konfigurację)';

    public function updateAppBootstrap(): void
    {
        $path = base_path('bootstrap/app.php');
        $content = file_get_contents($path);

        $content = preg_replace('/use App\\\\Http\\\\Middleware\\\\HandleInertiaRequests( as BaseHandleInertiaRequests)?;\n/', '', $content);
        $content = preg_replace('/use Upsoftware\\\\Svarium\\\\Http\\\\Middleware\\\\HandleInertiaRequests;\n/', '', $content);

        $newImports = "\nuse Upsoftware\Svarium\Http\Middleware\HandleInertiaRequests;\n" .
            "use App\Http\Middleware\HandleInertiaRequests as BaseHandleInertiaRequests;";
        $content = preg_replace('/(?<=<?php\n)/', $newImports, $content);

        $content = preg_replace('/^\s*(Base)?HandleInertiaRequests::class,?\n/m', '', $content);

        $replacement = "append: [\n            BaseHandleInertiaRequests::class,\n            HandleInertiaRequests::class,";

        if (str_contains($content, 'append: [')) {
            $content = preg_replace('/append: \[\s*/', $replacement . "\n            ", $content, 1);
        }

        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        file_put_contents($path, $content);
    }

    public function updateUserModel(): void
    {
        $path = app_path('Models/User.php');
        if (!file_exists($path)) return;

        $lines = file($path);
        $traits = [
            'HasRoles'   => 'Spatie\Permission\Traits\HasRoles',
            'HasSetting' => 'Upsoftware\Svarium\Traits\HasSetting',
            'UseDevices' => 'Upsoftware\Svarium\Traits\UseDevices',
        ];

        foreach ($traits as $name => $namespace) {
            $importExists = false;
            $traitExists = false;
            $classLineIndex = -1;
            $lastUseIndex = -1;

            foreach ($lines as $index => $line) {
                if (str_contains($line, "use {$namespace};")) $importExists = true;
                if (str_contains($line, "class User")) $classLineIndex = $index;
                if ($classLineIndex === -1 && str_starts_with(trim($line), "use ")) $lastUseIndex = $index;

                if ($classLineIndex !== -1 && preg_match("/\buse\b[^;]*\b{$name}\b/", $line)) {
                    $traitExists = true;
                }
            }

            if (!$importExists) {
                $insertAt = ($lastUseIndex !== -1) ? $lastUseIndex + 1 : 2;
                array_splice($lines, $insertAt, 0, ["use {$namespace};\n"]);
                $classLineIndex++;
            }

            if (!$traitExists) {
                $traitAdded = false;
                for ($i = $classLineIndex; $i < count($lines); $i++) {
                    if (preg_match('/^\s*use\s+([^;]+);/', $lines[$i], $matches)) {
                        $lines[$i] = str_replace(';', ", {$name};", $lines[$i]);
                        $traitAdded = true;
                        break;
                    }
                }

                if (!$traitAdded) {
                    for ($i = $classLineIndex; $i < count($lines); $i++) {
                        if (str_contains($lines[$i], '{')) {
                            array_splice($lines, $i + 1, 0, ["    use {$name};\n"]);
                            break;
                        }
                    }
                }
            }
        }

        file_put_contents($path, implode("", $lines));
    }

    protected function addLoginConfiguration() {
        $config = [];

        $config['title'] = $this->ask('Tytuł strony logowania', 'Welcome back!');
        $config['subtitle'] = $this->ask('Podtytuł strony logowania', 'Enter your email address and password');
        $config['submitLabel'] = $this->ask('Tytuł buttona logowania', 'Log in with your email address');
        if ($this->confirm('Czy chcesz dodać rejestrację uzytkownika?', true)) {
            $config['showRegisterLink'] = true;
            $config['registerLabel'] = $this->ask('Tytuł rejestracji', 'If you don’t have an account');
            $config['registerLinkLabel'] = $this->ask('Tytuł linku rejestracji', 'sign up here');
            $config['resetLink'] = $this->ask('Link do rejestracji', 'panel.auth.reset');
        } else {
            $config['showRegisterLink'] = false;
            $config['registerLabel'] = '';
            $config['registerLinkLabel'] = '';
            $config['resetLink'] = '';
        }

        if ($this->confirm('Czy chcesz dodać reset hasła uzytkownika?', true)) {
            $config['showResetLink'] = true;
            $config['resetLabel'] = $this->ask('Tytuł linku resetu hasła', 'Forgot your password?');
            $config['registerLink'] = $this->ask('Link do resetu hasła', 'panel.auth.register');
        } else {
            $config['showResetLink'] = false;
            $config['resetLabel'] = '';
            $config['registerLink'] = '';
        }

        $this->settingModel::setSettingGlobal('login.config', $config);
    }

    public function resources() {
        $component_ts_stub = __DIR__ . '/../../stubs/components.ts.stub';
        $app_ts_stub = __DIR__ . '/../../stubs/app.ts.stub';
        $resolver_ts_stub = __DIR__ . '/../../stubs/resolver.ts.stub';
        $app_css_stub = __DIR__ . '/../../stubs/app.css.stub';
        $routes_web_stub = __DIR__ . '/../../stubs/routes.web.stub';
        $app_blade_php_stub = __DIR__ . '/../../stubs/app.blade.php.stub';

        $APP_NAME = env('APP_NAME');

        $resource_js = resource_path('js');
        $resource_css = resource_path('css');
        $resource_views = resource_path('views');
        $routes = base_path('routes');


        if(file_exists($component_ts_stub)) {
            $component_ts_content = file_get_contents($component_ts_stub);
            $component_ts_path = $resource_js . '/components.ts';
            if (file_exists($component_ts_path)) {
                $force = confirm('Czy nadpisać plik: '.$component_ts_path, false, 'Tak', 'Nie');
                if ($force) {
                    $this->info('Nadpisany plik: '.$component_ts_path);
                    file_put_contents($component_ts_path, $component_ts_content);
                }
            } else {
                $this->info('Utworzono plik: '.$component_ts_path);
                file_put_contents($component_ts_path, $component_ts_content);
            }
        }

        if(file_exists($app_ts_stub)) {
            $save = true;
            $app_ts_content = file_get_contents($app_ts_stub);
            $app_ts_path = $resource_js . '/app.ts';
            if (file_exists($app_ts_path)) {
                $force = confirm('Czy nadpisać plik: '.$app_ts_path, false, 'Tak', 'Nie');
                if (!$force) {
                    $save = false;
                }
            }

            if ($save) {
                $PREFIX = text('Podaj nazwę prefix dla komponentow', '', 'Sv');
                $app_ts_content = strtr($app_ts_content, ['{{PREFIX}}' => $PREFIX, '{{APP_NAME}}' => $APP_NAME]);
                $this->info('Utworzyłem plik: '.$app_ts_path);
                file_put_contents($app_ts_path, $app_ts_content);
            }
        }

        if(file_exists($resolver_ts_stub)) {
            $save = true;
            $resolver_ts_content = file_get_contents($resolver_ts_stub);
            $resolver_ts_path = $resource_js . '/resolver.ts';
            if (file_exists($resolver_ts_path)) {
                $force = confirm('Czy nadpisać plik: '.$resolver_ts_path, false, 'Tak', 'Nie');
                if (!$force) {
                    $save = false;
                }
            }

            if ($save) {
                $this->info('Utworzyłem plik: '.$resolver_ts_path);
                file_put_contents($resolver_ts_path, $resolver_ts_content);
            }
        }

        if(file_exists($app_css_stub)) {
            $app_css_path = $resource_css . '/app.css';
            $save = true;
            if (file_exists($app_css_path)) {
                $force = confirm('Czy nadpisać plik: '.$app_css_path, false, 'Tak', 'Nie');
                if (!$force) {
                    $save = false;
                }
            }

            if ($save) {
                $tailwindColor = select('Wybierz kolor podstawowy jasny (primary)', $this->tailwindColors());
                $tailwindColorPalette = select('Wybierz odcień', [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]);
                $palette = $this->tailwindPalette();
                $PRIMARY = $palette[$tailwindColor][$tailwindColorPalette];

                $sameColor = confirm('Czy ten sam kolor dodać jako kolor ciemny?', true, 'Tak', 'Nie');
                if ($sameColor) {
                    $PRIMARY_DARK = $PRIMARY;
                    $tailwindColorDark = $tailwindColor;
                    $tailwindColorDarkPalette = $tailwindColorPalette;
                } else {
                    $tailwindColorDark = select('Wybierz kolor podstawowy ciemny (primary)', $this->tailwindColors());
                    $tailwindColorDarkPalette = select('Wybierz odcień', [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]);
                    $PRIMARY_DARK = $palette[$tailwindColorDark][$tailwindColorDarkPalette];
                }

                $this->info('Kolor podstawowy (jasny/light): ' . $tailwindColor . ' (' . $tailwindColorPalette . ') - ' . $PRIMARY);
                $this->info('Kolor podstawowy (ciemny/dark): ' . $tailwindColorDark . ' (' . $tailwindColorDarkPalette . ') - ' . $PRIMARY_DARK);
                $app_css_content = file_get_contents($app_css_stub);
                $app_css_content = strtr($app_css_content, ['{{PRIMARY}}' => $PRIMARY, '{{PRIMARY_DARK}}' => $PRIMARY_DARK]);

                $this->info('Utworzyłem plik: ' . $app_css_path);
                file_put_contents($app_css_path, $app_css_content);
            }
        }

        if(file_exists($routes_web_stub)) {
            $routes_web_content = file_get_contents($routes_web_stub);
            $routes_web_path = $routes . '/web.php';
            if (file_exists($routes_web_path)) {
                $force = confirm('Czy nadpisać plik: '.$routes_web_path, false, 'Tak', 'Nie');
                if ($force) {
                    $this->info('Nadpisany plik: '.$routes_web_path);
                    file_put_contents($routes_web_path, $routes_web_content);
                }
            } else {
                $this->info('Utworzyłem plik: '.$routes_web_path);
                file_put_contents($routes_web_path, $routes_web_content);
            }
        }

        if(file_exists($app_blade_php_stub)) {
            $app_blade_php_content = file_get_contents($app_blade_php_stub);
            $app_blade_php_path = $resource_views . '/app.blade.php';
            if (file_exists($app_blade_php_path)) {
                $force = confirm('Czy nadpisać plik: '.$app_blade_php_path, false, 'Tak', 'Nie');
                if ($force) {
                    $this->info('Nadpisany plik: '.$app_blade_php_path);
                    file_put_contents($app_blade_php_path, $app_blade_php_content);
                }
            } else {
                $this->info('Utworzyłem plik: '.$app_blade_php_path);
                file_put_contents($app_blade_php_path, $app_blade_php_content);
            }
        }
    }

    public function handle()
    {
        $this->updateUserModel();
        $this->updateAppBootstrap();
        $this->resources();

        passthru('php artisan ide-helper:generate');
        passthru('php artisan ide-helper:models -N');
        passthru('php artisan ide-helper:meta');

        $this->info('Publikowanie Spatie Permission...');
        $this->call('vendor:publish', [
            '--provider' => "Spatie\\Permission\\PermissionServiceProvider"
        ]);

        $this->info('Publikowanie Laravel Lang...');
        $this->call('vendor:publish', [
            '--provider' => "LaravelLang\Config\ServiceProvider"
        ]);

        $this->info('Publikowanie Hashids...');
        $this->call('vendor:publish', [
            '--provider' => "Vinkla\Hashids\HashidsServiceProvider"
        ]);

        passthru('php artisan vendor:publish --tag=upsoftware');
        passthru('php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"');
        passthru('php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"');
        passthru('php artisan vendor:publish --provider="hisorange\BrowserDetect\ServiceProvider"');

        $this->addConfigKey('activitylog.php', 'activity_model', '\Upsoftware\Svarium\Models\Activity::class', true);
        $this->addConfigKey('browser-detect.php', 'cache.interval', 0, true);

        if ($this->confirm('Czy opublikować zasoby konfiguracyjne Tenancy?', false)) {
            $this->info('Publikowanie Hashids...');
            $this->call('vendor:publish', [
                '--provider' => "Stancl\Tenancy\TenancyServiceProvider"
            ]);

            if ($this->addConfigKey('tenancy.php', 'enabled', true)) {
                $this->info('Dodano klucz "enabled" => true do config/tenancy.php');
            }
        }


        passthru('php artisan migrate');
        passthru("php artisan native:install");

        $currentLocale = config('app.locale');
        $selectedLocale = $this->ask('Podaj domyślny język aplikacji (APP_LOCALE)', $currentLocale);
        $this->info("Instalowanie plików językowych dla: $selectedLocale ...");
        passthru("php artisan lang:add $selectedLocale");

        if ($selectedLocale !== $currentLocale) {
            $this->addEnvKey('APP_LOCALE', $selectedLocale, true);
            $this->info("Zaktualizowano APP_LOCALE w pliku .env na: $selectedLocale");

            config(['app.locale' => $selectedLocale]);
        }

        while (true) {
            if (! $this->confirm('Czy chcesz dodać język (lub kolejny)?', true)) {
                break;
            }

            while (true) {
                $code = $this->ask('Wpisz kod języka (np. pl, en, de, es)');

                if (empty($code)) {
                    $this->warn('Nie podano kodu języka. Spróbuj ponownie.');
                    continue;
                }

                $this->info("Dodawanie języka: $code ...");
                passthru("php artisan svarium:lang.add $code");
                $this->newLine();

                break;
            }
        }

        $this->call('svarium:login.socials');

        $app_name = $this->ask('Nazwa aplikacji', env('APP_NAME'));
        $this->addEnvKey('APP_NAME', $app_name);

        $this->addLoginConfiguration();

        $this->info('Gotowe!');
    }
}
