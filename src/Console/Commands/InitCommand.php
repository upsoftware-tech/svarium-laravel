<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LaravelLang\Locales\Facades\Locales;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class InitCommand extends CoreCommand
{
    protected $signature = 'svarium:app.init';

    protected $description = 'Initialize the application (adds required configuration)';

    protected $descriptionKey = 'app.init';

    protected function tt(string $key, string $fallback, array $replace = []): string
    {
        $translationKey = "svarium::commands.{$key}";
        $translated = __($translationKey, $replace);

        if (is_string($translated) && $translated !== '' && $translated !== $translationKey) {
            return $translated;
        }

        foreach ($replace as $name => $value) {
            $fallback = str_replace(':'.$name, (string) $value, $fallback);
        }

        return $fallback;
    }

    protected function switchConsoleLocale(string $locale): void
    {
        $normalized = trim($locale);
        if ($normalized === '') {
            return;
        }

        config(['app.locale' => $normalized]);
        app()->setLocale($normalized);
        $this->translateDescription();
    }

    /**
     * Returns selected locale code.
     */
    protected function configureApplicationLocale(): string
    {
        $currentLocale = $this->normalizeLocaleCode((string) config('app.locale', 'en')) ?? 'en';
        $selectedLocale = $this->promptForLocaleCode(
            $this->tt('app.init_ui.app_locale_prompt', 'Podaj domyślny język aplikacji (APP_LOCALE)'),
            $currentLocale
        );

        $this->info($this->tt('app.init_ui.installing_language', 'Instalowanie plików językowych dla: :locale ...', [
            'locale' => $selectedLocale,
        ]));
        $this->call('svarium:lang.add', ['lang' => [$selectedLocale]]);
        $this->persistLocaleSetting($selectedLocale);

        if ($selectedLocale !== $currentLocale) {
            $switchConsole = confirm(
                $this->tt(
                    'app.init_ui.switch_console_locale_prompt',
                    'Czy przełączyć język interfejsu konsoli na wskazany (:locale)?',
                    ['locale' => $selectedLocale]
                ),
                true,
                $this->tt('common.yes', 'Tak'),
                $this->tt('common.no', 'Nie')
            );

            $this->addEnvKey('APP_LOCALE', $selectedLocale, true);
            $this->info($this->tt('app.init_ui.app_locale_updated', 'Zaktualizowano APP_LOCALE w pliku .env na: :locale', [
                'locale' => $selectedLocale,
            ]));

            if ($switchConsole) {
                $this->switchConsoleLocale($selectedLocale);
                $this->info($this->tt('app.init_ui.console_locale_switched', 'Przełączono język interfejsu konsoli na: :locale', [
                    'locale' => $selectedLocale,
                ]));
            }
        }

        return $selectedLocale;
    }

    protected function promptForLocaleCode(string $label, ?string $default = null): string
    {
        $normalizedDefault = $this->normalizeLocaleCode((string) ($default ?? ''));

        while (true) {
            $input = trim((string) $this->ask($label, $normalizedDefault ?? ''));
            $locale = $this->normalizeLocaleCode($input);

            if ($locale === null) {
                $this->warn($this->tt('app.init_ui.empty_language_code', 'Nie podano kodu języka. Spróbuj ponownie.'));

                continue;
            }

            if ($this->findLocaleMetadata($locale) === null) {
                $this->warn($this->tt('app.init_ui.invalid_language_code', 'Nieprawidłowy kod języka [:locale]. Spróbuj ponownie.', [
                    'locale' => $locale,
                ]));

                continue;
            }

            return $locale;
        }
    }

    protected function normalizeLocaleCode(string $value): ?string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9_-]/', '', $normalized) ?? '';
        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : null;
    }

    protected function findLocaleMetadata(string $locale): ?array
    {
        $normalizedLocale = $this->normalizeLocaleCode($locale);
        if ($normalizedLocale === null) {
            return null;
        }

        foreach (Locales::available() as $availableLocale) {
            $code = $this->normalizeLocaleCode((string) ($availableLocale->code ?? ''));
            if ($code !== $normalizedLocale) {
                continue;
            }

            $data = json_decode(json_encode($availableLocale), true);
            if (! is_array($data) || $data === []) {
                return null;
            }

            if (! empty($data['regional']) && strlen((string) $data['regional']) === 5) {
                $data['flag'] = strtolower(explode('_', (string) $data['regional'])[1] ?? '');
            }

            return $data;
        }

        return null;
    }

    protected function persistLocaleSetting(string $locale): void
    {
        $localeData = $this->findLocaleMetadata($locale);
        if ($localeData === null) {
            return;
        }

        $this->settingModel::setSettingGlobal('locales', [$locale => $localeData]);
    }

    protected function ensureMysqlDatabaseConnection(): bool
    {
        $defaultConnection = trim((string) config('database.default', ''));

        if ($defaultConnection === '') {
            $this->error('Brak domyślnego połączenia bazy danych (database.default).');

            return false;
        }

        $driver = strtolower(trim((string) config("database.connections.{$defaultConnection}.driver", '')));
        if ($driver !== 'mysql') {
            $this->error("Svarium init wymaga połączenia MySQL. Aktualny driver [{$defaultConnection}]: {$driver}");

            return false;
        }

        try {
            DB::connection($defaultConnection)->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->error("Nie udało się połączyć z bazą danych [{$defaultConnection}] (mysql).");
            $this->line($e->getMessage());

            return false;
        }

        $this->info("Połączenie z bazą [{$defaultConnection}] (mysql): OK");

        return true;
    }

    protected function readEnvValue(string $key): ?string
    {
        $envPath = base_path('.env');
        if (! is_file($envPath)) {
            return null;
        }

        $content = file_get_contents($envPath);
        if (! is_string($content) || $content === '') {
            return null;
        }

        $pattern = '/^'.preg_quote($key, '/').'\s*=\s*(.*)$/mi';
        if (preg_match($pattern, $content, $matches) !== 1) {
            return null;
        }

        $value = trim((string) ($matches[1] ?? ''));
        if ($value === '') {
            return '';
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        } else {
            $value = trim((string) Str::before($value, ' #'));
        }

        return trim((string) $value);
    }

    protected function isSvariumConfigured(): bool
    {
        $marker = strtolower(trim((string) $this->readEnvValue('SVARIUM')));
        if ($marker === 'enabled') {
            return true;
        }

        $legacy = strtolower(trim((string) $this->readEnvValue('SVARIUM_ENABLED')));

        return in_array($legacy, ['1', 'true', 'yes', 'on', 'enabled'], true);
    }

    protected function markSvariumConfigured(): void
    {
        $this->addEnvKey('SVARIUM', 'enabled', false, true);
    }

    protected function resolveUserModelClass(): string
    {
        try {
            if (function_exists('get_model')) {
                $configuredSvariumModel = get_model('user');
                if (is_string($configuredSvariumModel) && trim($configuredSvariumModel) !== '') {
                    return trim($configuredSvariumModel);
                }
            }
        } catch (\Throwable) {
            // Fallback to config keys below.
        }

        $configuredUserModel = config('upsoftware.models.user');
        if (is_string($configuredUserModel) && trim($configuredUserModel) !== '') {
            return trim($configuredUserModel);
        }

        $trackingUserModel = config('upsoftware.tracking.user_model', config('upsoftware.user_model'));
        if (is_string($trackingUserModel) && trim($trackingUserModel) !== '') {
            return trim($trackingUserModel);
        }

        return \Upsoftware\Svarium\Models\User::class;
    }

    protected function resolveGuardNameForUserModel(string $userModelClass, string $fallbackGuard = 'web'): string
    {
        try {
            $user = new $userModelClass;

            if (method_exists($user, 'getDefaultGuardName')) {
                // Keep exact guard expected by Spatie (even empty string).
                return trim((string) $user->getDefaultGuardName());
            }

            if (property_exists($user, 'guard_name')) {
                return trim((string) ($user->guard_name ?? $fallbackGuard));
            }
        } catch (\Throwable) {
            // Fallback below.
        }

        return $fallbackGuard;
    }

    protected function syncAppTsAppNameFallback(string $appName): void
    {
        $appTsPath = resource_path('js/app.ts');
        if (! is_file($appTsPath)) {
            return;
        }

        $content = file_get_contents($appTsPath);
        if (! is_string($content) || $content === '') {
            return;
        }

        $normalizedAppName = str_replace(["\r", "\n"], ' ', trim($appName));
        if ($normalizedAppName === '') {
            $normalizedAppName = 'APP_NAME';
        }

        $escapedAppName = str_replace("'", "\\'", $normalizedAppName);
        $replacementLine = "const appName = import.meta.env.VITE_APP_NAME || '{$escapedAppName}';";

        $updated = preg_replace(
            '/^const\s+appName\s*=\s*import\.meta\.env\.VITE_APP_NAME\s*\|\|\s*(?:\'[^\']*\'|\"[^\"]*\")\s*;$/m',
            $replacementLine,
            $content,
            1,
            $count
        );

        if (($count ?? 0) < 1 || ! is_string($updated)) {
            return;
        }

        file_put_contents($appTsPath, $updated);
        $this->info('Zaktualizowano fallback APP_NAME w pliku: '.$appTsPath);
    }

    protected function applyAppTsPrefixFallback(string $content, string $prefix): string
    {
        $replacementLine = "prefix: '{$prefix}',";

        $updated = preg_replace(
            '/^\s*prefix\s*:\s*(?:\'[^\']*\'|"[^"]*")\s*,\s*$/m',
            '                '.$replacementLine,
            $content,
            1,
            $count
        );

        if (($count ?? 0) > 0 && is_string($updated)) {
            return $updated;
        }

        $updated = preg_replace(
            '/\.use\(Svarium,\s*\{\s*/',
            ".use(Svarium, {\n                {$replacementLine}\n                ",
            $content,
            1,
            $insertedCount
        );

        if (($insertedCount ?? 0) > 0 && is_string($updated)) {
            return $updated;
        }

        return $content;
    }

    protected function syncAppTsPrefixFallback(string $prefix): void
    {
        $appTsPath = resource_path('js/app.ts');
        if (! is_file($appTsPath)) {
            return;
        }

        $content = file_get_contents($appTsPath);
        if (! is_string($content) || $content === '') {
            return;
        }

        $escapedPrefix = str_replace("'", "\\'", trim($prefix));
        $updated = $this->applyAppTsPrefixFallback($content, $escapedPrefix);

        if (! is_string($updated) || $updated === $content) {
            return;
        }

        file_put_contents($appTsPath, $updated);
        $this->info('Zaktualizowano prefix Svarium w pliku: '.$appTsPath);
    }

    public function updateAppBootstrap(): void
    {
        $path = base_path('bootstrap/app.php');
        $content = file_get_contents($path);

        $content = preg_replace('/use App\\\\Http\\\\Middleware\\\\HandleInertiaRequests( as BaseHandleInertiaRequests)?;\n/', '', $content);
        $content = preg_replace('/use Upsoftware\\\\Svarium\\\\Http\\\\Middleware\\\\HandleInertiaRequests;\n/', '', $content);
        $content = preg_replace('/use Illuminate\\\\Http\\\\Request;\n/', '', $content);
        $content = preg_replace('/use Throwable;\n/', '', $content);

        $newImports = "\nuse Upsoftware\Svarium\Http\Middleware\HandleInertiaRequests;\n".
            "use App\Http\Middleware\HandleInertiaRequests as BaseHandleInertiaRequests;\n".
            "use Illuminate\Http\Request;";
        $content = preg_replace('/(?<=<?php\n)/', $newImports, $content);

        $content = preg_replace('/^\s*(Base)?HandleInertiaRequests::class,?\n/m', '', $content);

        $replacement = "append: [\n            BaseHandleInertiaRequests::class,\n            HandleInertiaRequests::class,";

        if (str_contains($content, 'append: [')) {
            $content = preg_replace('/append: \[\s*/', $replacement."\n            ", $content, 1);
        }

        if (! str_contains($content, "alert_warning")) {
            $exceptionHandler = <<<'PHP'
$exceptions->respond(function ($response, \Throwable $exception, Request $request) {
            if ($response->getStatusCode() !== 419 || $request->expectsJson()) {
                return $response;
            }

            $path = trim($request->path(), '/');
            $message = __('Session expired. The form has been refreshed. Please try again.');

            if (preg_match('#(^|/)auth/(login|register|reset)/verification/[^/]+(?:/resend)?$#', $path) === 1) {
                return redirect()->to($request->fullUrl(), 303)
                    ->with('alert_warning', $message);
            }

            if (preg_match('#(^|/)auth/(login|register|reset)(?:/.*)?$#', $path) === 1) {
                return redirect()->to($request->fullUrl(), 303)
                    ->with('alert_warning', $message);
            }

            return back(303)->with('alert_warning', __('Session expired. Refresh the page and try again.'));
        });
PHP;

            $content = preg_replace(
                '/->withExceptions\(function \(Exceptions \$exceptions\): void \{\s*(.*?)\s*\}\)/s',
                "->withExceptions(function (Exceptions \$exceptions): void {\n        {$exceptionHandler}\n    })",
                $content,
                1
            );
        }

        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        file_put_contents($path, $content);
    }

    public function updateUserModel(): void
    {
        $path = app_path('Models/User.php');
        if (! file_exists($path)) {
            return;
        }

        $lines = file($path);
        $traits = [
            'HasRoles' => 'Spatie\Permission\Traits\HasRoles',
            'HasSetting' => 'Upsoftware\Svarium\Traits\HasSetting',
            'UseDevices' => 'Upsoftware\Svarium\Traits\UseDevices',
        ];

        foreach ($traits as $name => $namespace) {
            $importExists = false;
            $traitExists = false;
            $classLineIndex = -1;
            $lastUseIndex = -1;

            foreach ($lines as $index => $line) {
                if (str_contains($line, "use {$namespace};")) {
                    $importExists = true;
                }
                if (str_contains($line, 'class User')) {
                    $classLineIndex = $index;
                }
                if ($classLineIndex === -1 && str_starts_with(trim($line), 'use ')) {
                    $lastUseIndex = $index;
                }

                if ($classLineIndex !== -1 && preg_match("/\buse\b[^;]*\b{$name}\b/", $line)) {
                    $traitExists = true;
                }
            }

            if (! $importExists) {
                $insertAt = ($lastUseIndex !== -1) ? $lastUseIndex + 1 : 2;
                array_splice($lines, $insertAt, 0, ["use {$namespace};\n"]);
                $classLineIndex++;
            }

            if (! $traitExists) {
                $traitAdded = false;
                for ($i = $classLineIndex; $i < count($lines); $i++) {
                    if (preg_match('/^\s*use\s+([^;]+);/', $lines[$i], $matches)) {
                        $lines[$i] = str_replace(';', ", {$name};", $lines[$i]);
                        $traitAdded = true;
                        break;
                    }
                }

                if (! $traitAdded) {
                    for ($i = $classLineIndex; $i < count($lines); $i++) {
                        if (str_contains($lines[$i], '{')) {
                            array_splice($lines, $i + 1, 0, ["    use {$name};\n"]);
                            break;
                        }
                    }
                }
            }
        }

        file_put_contents($path, implode('', $lines));
    }

    protected function addLoginConfiguration()
    {
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

    public function resources()
    {
        $component_ts_stub = __DIR__.'/../../stubs/components.ts.stub';
        $app_ts_stub = __DIR__.'/../../stubs/app.ts.stub';
        $resolver_ts_stub = __DIR__.'/../../stubs/resolver.ts.stub';
        $routes_web_stub = __DIR__.'/../../stubs/routes.web.stub';
        $app_blade_php_stub = __DIR__.'/../../stubs/app.blade.php.stub';

        $APP_NAME = env('APP_NAME');

        $resource_js = resource_path('js');
        $resource_views = resource_path('views');
        $routes = base_path('routes');

        if (file_exists($component_ts_stub)) {
            $component_ts_content = file_get_contents($component_ts_stub);
            $component_ts_path = $resource_js.'/components.ts';
            if (file_exists($component_ts_path)) {
                $force = confirm(
                    $this->tt('app.init_ui.overwrite_file_prompt', 'Czy nadpisać plik: :path', ['path' => $component_ts_path]),
                    false,
                    $this->tt('common.yes', 'Tak'),
                    $this->tt('common.no', 'Nie')
                );
                if ($force) {
                    $this->info($this->tt('app.init_ui.file_overwritten', 'Nadpisany plik: :path', ['path' => $component_ts_path]));
                    file_put_contents($component_ts_path, $component_ts_content);
                }
            } else {
                $this->info($this->tt('app.init_ui.file_created', 'Utworzono plik: :path', ['path' => $component_ts_path]));
                file_put_contents($component_ts_path, $component_ts_content);
            }
        }

        if (file_exists($app_ts_stub)) {
            $save = true;
            $app_ts_content = file_get_contents($app_ts_stub);
            $app_ts_path = $resource_js.'/app.ts';
            if (file_exists($app_ts_path)) {
                $force = confirm(
                    $this->tt('app.init_ui.overwrite_file_prompt', 'Czy nadpisać plik: :path', ['path' => $app_ts_path]),
                    false,
                    $this->tt('common.yes', 'Tak'),
                    $this->tt('common.no', 'Nie')
                );
                if (! $force) {
                    $save = false;
                }
            }

            if ($save) {
                $configuredPrefix = trim((string) config('upsoftware.components.prefix', ''));
                $escapedPrefix = str_replace("'", "\\'", $configuredPrefix);
                $escapedAppName = str_replace("'", "\\'", (string) $APP_NAME);
                $app_ts_content = strtr($app_ts_content, ['{{PREFIX}}' => $escapedPrefix, '{{APP_NAME}}' => $escapedAppName]);
                $app_ts_content = $this->applyAppTsPrefixFallback($app_ts_content, $escapedPrefix);
                $this->info($this->tt('app.init_ui.file_created', 'Utworzono plik: :path', ['path' => $app_ts_path]));
                file_put_contents($app_ts_path, $app_ts_content);
            }
        }

        if (file_exists($resolver_ts_stub)) {
            $save = true;
            $resolver_ts_content = file_get_contents($resolver_ts_stub);
            $resolver_ts_path = $resource_js.'/resolver.ts';
            if (file_exists($resolver_ts_path)) {
                $force = confirm(
                    $this->tt('app.init_ui.overwrite_file_prompt', 'Czy nadpisać plik: :path', ['path' => $resolver_ts_path]),
                    false,
                    $this->tt('common.yes', 'Tak'),
                    $this->tt('common.no', 'Nie')
                );
                if (! $force) {
                    $save = false;
                }
            }

            if ($save) {
                $this->info($this->tt('app.init_ui.file_created', 'Utworzono plik: :path', ['path' => $resolver_ts_path]));
                file_put_contents($resolver_ts_path, $resolver_ts_content);
            }
        }

        $colorsExitCode = $this->call('svarium:app.colors', [
            '--file' => 'resources/css/app.css',
            '--initialize' => true,
        ]);
        if ($colorsExitCode !== self::SUCCESS) {
            $this->warn($this->tt('app.init_ui.colors_initialize_failed', 'Nie udało się uruchomić komendy svarium:app.colors w trybie initialize.'));
        }

        if (file_exists($routes_web_stub)) {
            $routes_web_content = file_get_contents($routes_web_stub);
            $routes_web_path = $routes.'/web.php';
            if (file_exists($routes_web_path)) {
                $force = confirm(
                    $this->tt('app.init_ui.overwrite_file_prompt', 'Czy nadpisać plik: :path', ['path' => $routes_web_path]),
                    false,
                    $this->tt('common.yes', 'Tak'),
                    $this->tt('common.no', 'Nie')
                );
                if ($force) {
                    $this->info($this->tt('app.init_ui.file_overwritten', 'Nadpisany plik: :path', ['path' => $routes_web_path]));
                    file_put_contents($routes_web_path, $routes_web_content);
                }
            } else {
                $this->info($this->tt('app.init_ui.file_created', 'Utworzono plik: :path', ['path' => $routes_web_path]));
                file_put_contents($routes_web_path, $routes_web_content);
            }
        }

        if (file_exists($app_blade_php_stub)) {
            $app_blade_php_content = file_get_contents($app_blade_php_stub);
            $app_blade_php_path = $resource_views.'/app.blade.php';
            if (file_exists($app_blade_php_path)) {
                $force = confirm(
                    $this->tt('app.init_ui.overwrite_file_prompt', 'Czy nadpisać plik: :path', ['path' => $app_blade_php_path]),
                    false,
                    $this->tt('common.yes', 'Tak'),
                    $this->tt('common.no', 'Nie')
                );
                if ($force) {
                    $this->info($this->tt('app.init_ui.file_overwritten', 'Nadpisany plik: :path', ['path' => $app_blade_php_path]));
                    file_put_contents($app_blade_php_path, $app_blade_php_content);
                }
            } else {
                $this->info($this->tt('app.init_ui.file_created', 'Utworzono plik: :path', ['path' => $app_blade_php_path]));
                file_put_contents($app_blade_php_path, $app_blade_php_content);
            }
        }
    }

    protected function configureCoreOptions(): array
    {
        $this->newLine();
        $this->info($this->tt('app.init_ui.core_configuration', 'Konfiguracja podstawowa Svarium'));

        $defaultPanelName = trim((string) config('upsoftware.panel.name', env('SVARIUM_PANEL_NAME', 'admin')));
        if ($defaultPanelName === '') {
            $defaultPanelName = 'admin';
        }

        $defaultPanelPrefix = trim((string) config('upsoftware.panel.prefix', ''), '/');
        $defaultRouting = $defaultPanelPrefix === '' ? 'no_prefix' : 'prefix';

        $panelName = trim((string) text('Nazwa panelu', $defaultPanelName));
        if ($panelName === '') {
            $panelName = $defaultPanelName;
        }

        $panelRouting = select(
            'Sposób routingu panelu',
            [
                'prefix' => 'Panel z prefiksem URL (np. /admin)',
                'no_prefix' => 'Panel bez prefiksu (noPrefix)',
            ],
            $defaultRouting
        );

        $panelPrefix = '';
        if ($panelRouting === 'prefix') {
            $fallbackPrefix = $defaultPanelPrefix !== '' ? $defaultPanelPrefix : $panelName;
            $panelPrefix = trim((string) text('Prefiks panelu', $fallbackPrefix), '/');
            if ($panelPrefix === '') {
                $panelPrefix = $fallbackPrefix;
            }
        }

        $configuredAuthRoutePrefix = trim((string) config('upsoftware.panel.route_prefix', 'panel.auth'));
        $suggestedAuthRoutePrefix = trim($panelName) !== ''
            ? trim($panelName).'.auth'
            : 'panel.auth';

        $defaultAuthRoutePrefix = $configuredAuthRoutePrefix;
        if ($defaultAuthRoutePrefix === '' || $defaultAuthRoutePrefix === 'panel.auth') {
            $defaultAuthRoutePrefix = $suggestedAuthRoutePrefix;
        }

        $authRoutePrefix = trim((string) text(
            'Prefix nazw rout auth (np. app.auth)',
            $defaultAuthRoutePrefix
        ));
        if ($authRoutePrefix === '') {
            $authRoutePrefix = $defaultAuthRoutePrefix !== '' ? $defaultAuthRoutePrefix : 'panel.auth';
        }

        $componentPrefix = text(
            'Prefix komponentów Svarium (puste = bez prefixu)',
            'np. Sv',
            (string) config('upsoftware.components.prefix', '')
        );

        $apiEnabled = confirm(
            'Czy włączyć API Svarium?',
            (bool) config('upsoftware.api.enabled', true),
            'Tak',
            'Nie'
        );

        $apiPrefix = trim((string) text(
            'Prefiks API',
            (string) config('upsoftware.api.prefix', 'api/v1')
        ), '/');
        if ($apiPrefix === '') {
            $apiPrefix = 'api/v1';
        }

        $apiDriver = trim((string) text(
            'Driver API auth (SVARIUM_API_DRIVER)',
            (string) env('SVARIUM_API_DRIVER', 'sanctum')
        ));
        if ($apiDriver === '') {
            $apiDriver = 'sanctum';
        }

        $apiGuard = trim((string) text(
            'Guard API',
            (string) config('upsoftware.api.auth.guard', 'sanctum')
        ));
        if ($apiGuard === '') {
            $apiGuard = 'sanctum';
        }

        $registerEnabled = confirm(
            'Czy włączyć rejestrację użytkownika?',
            (bool) config('upsoftware.auth.register.enabled', true),
            'Tak',
            'Nie'
        );

        $registerAutoLogin = false;
        $activationMode = 'none';
        if ($registerEnabled) {
            $registerAutoLogin = confirm(
                'Automatyczne logowanie po rejestracji?',
                (bool) config('upsoftware.auth.register.auto_login', true),
                'Tak',
                'Nie'
            );

            $activationMode = select(
                'Tryb aktywacji konta po rejestracji',
                [
                    'none' => 'Brak aktywacji',
                    'email_code' => 'Kod z e-mail',
                    'email_link' => 'Link aktywacyjny',
                    'custom' => 'Własny handler',
                ],
                (string) config('upsoftware.auth.register.activation.mode', 'none')
            );
        }

        $tenancyEnabled = confirm(
            'Czy włączyć wbudowany multi-tenant Svarium?',
            (bool) config('upsoftware.tenancy.enabled', false),
            'Tak',
            'Nie'
        );

        $tenancyMode = (string) config('upsoftware.tenancy.mode', 'column');
        $tenancyDomainsEnabled = (bool) config('upsoftware.tenancy.domains.enabled', true);
        $tenancyDomainTablesEnabled = (bool) config('upsoftware.tenancy.column.model_maps.domains.enabled', true);
        $tenancyCentralDomains = [];
        $runTenantInstallCommand = false;

        if ($tenancyEnabled) {
            $runTenantInstallCommand = confirm(
                'Czy uruchomić teraz konfigurację tenancy przez komendę svarium:tenant.install?',
                true,
                'Tak',
                'Nie'
            );

            if (! $runTenantInstallCommand) {
                $tenancyMode = select(
                    'Wybierz tryb tenancy',
                    [
                        'column' => 'column (tenant_id w tabelach)',
                        'database' => 'database (osobna baza per tenant)',
                    ],
                    $tenancyMode
                );

                $tenancyDomainTablesEnabled = confirm(
                    'Czy włączyć domeny tenantów (tabele domains/model_has_domains)?',
                    $tenancyDomainTablesEnabled,
                    'Tak',
                    'Nie'
                );

                if ($tenancyDomainTablesEnabled) {
                    $tenancyDomainsEnabled = confirm(
                        'Czy rozpoznawać tenantów po domenie (host requestu)?',
                        $tenancyDomainsEnabled,
                        'Tak',
                        'Nie'
                    );

                    $defaultDomains = config('upsoftware.tenancy.domains.central_domains', []);
                    $domainsString = is_array($defaultDomains) ? implode(',', $defaultDomains) : '';
                    $rawDomains = trim((string) text('Centralne domeny (po przecinku, opcjonalnie)', $domainsString));
                    if ($rawDomains !== '') {
                        $tenancyCentralDomains = array_values(array_filter(array_map(
                            static fn ($domain) => trim((string) $domain),
                            explode(',', $rawDomains)
                        )));
                    }
                } else {
                    $tenancyDomainsEnabled = false;
                    $tenancyCentralDomains = [];
                }
            }
        }

        $subscriptionsEnabled = confirm(
            'Czy aktywować moduł subskrypcji?',
            (bool) config('upsoftware.modules.builtin.subscriptions', false),
            'Tak',
            'Nie'
        );

        return [
            'panel_name' => $panelName,
            'panel_prefix' => $panelPrefix,
            'auth_route_prefix' => $authRoutePrefix,
            'component_prefix' => (string) $componentPrefix,
            'api_enabled' => $apiEnabled,
            'api_prefix' => $apiPrefix,
            'api_driver' => $apiDriver,
            'api_guard' => $apiGuard,
            'register_enabled' => $registerEnabled,
            'register_auto_login' => $registerAutoLogin,
            'register_activation_mode' => $activationMode,
            'tenancy_enabled' => $tenancyEnabled,
            'tenancy_mode' => $tenancyMode,
            'tenancy_domain_tables_enabled' => $tenancyDomainTablesEnabled,
            'tenancy_domains_enabled' => $tenancyDomainsEnabled,
            'tenancy_central_domains' => $tenancyCentralDomains,
            'run_tenant_install_command' => $runTenantInstallCommand,
            'subscriptions_enabled' => $subscriptionsEnabled,
        ];
    }

    protected function ensurePanelsFile(string $panelName, string $panelPrefix): void
    {
        $panelsPath = base_path('app/Svarium/panels.php');

        if (File::exists($panelsPath)) {
            return;
        }

        $directory = dirname($panelsPath);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true, true);
        }

        $panelLine = $panelPrefix === ''
            ? "    Panel::make('{$panelName}')->noPrefix(),"
            : "    Panel::make('{$panelName}')->prefix('{$panelPrefix}'),";

        $content = <<<PHP
<?php

use Upsoftware\\Svarium\\Panel\\Panel;

return [
{$panelLine}
];
PHP;

        File::put($panelsPath, $content);
        $this->info('Utworzono domyślny plik app/Svarium/panels.php');
    }

    protected function applyCoreConfiguration(array $config): void
    {
        $panelName = trim((string) ($config['panel_name'] ?? 'admin'));
        $panelPrefix = trim((string) ($config['panel_prefix'] ?? ''), '/');
        $authRoutePrefix = trim((string) ($config['auth_route_prefix'] ?? 'panel.auth'));
        $authRoutePrefix = $authRoutePrefix !== '' ? $authRoutePrefix : 'panel.auth';
        $componentPrefix = trim((string) ($config['component_prefix'] ?? ''));

        $apiEnabled = (bool) ($config['api_enabled'] ?? true);
        $apiPrefix = trim((string) ($config['api_prefix'] ?? 'api/v1'), '/');
        $apiPrefix = $apiPrefix !== '' ? $apiPrefix : 'api/v1';
        $apiDriver = trim((string) ($config['api_driver'] ?? 'sanctum'));
        $apiDriver = $apiDriver !== '' ? $apiDriver : 'sanctum';
        $apiGuard = trim((string) ($config['api_guard'] ?? 'sanctum'));
        $apiGuard = $apiGuard !== '' ? $apiGuard : 'sanctum';

        $registerEnabled = (bool) ($config['register_enabled'] ?? true);
        $registerAutoLogin = (bool) ($config['register_auto_login'] ?? true);
        $registerActivationMode = trim((string) ($config['register_activation_mode'] ?? 'none'));
        $registerActivationMode = $registerActivationMode !== '' ? $registerActivationMode : 'none';

        $tenancyEnabled = (bool) ($config['tenancy_enabled'] ?? false);
        $tenancyMode = trim((string) ($config['tenancy_mode'] ?? 'column'));
        $tenancyMode = in_array($tenancyMode, ['column', 'database'], true) ? $tenancyMode : 'column';
        $tenancyDomainTablesEnabled = (bool) ($config['tenancy_domain_tables_enabled'] ?? true);
        $tenancyDomainsEnabled = (bool) ($config['tenancy_domains_enabled'] ?? true);
        $tenancyCentralDomains = is_array($config['tenancy_central_domains'] ?? null)
            ? array_values(array_filter(array_map(static fn ($domain) => trim((string) $domain), $config['tenancy_central_domains'])))
            : [];

        $this->addEnvKey('SVARIUM_PANEL_NAME', $panelName);
        $this->addEnvKey('SVARIUM_API_DRIVER', $apiDriver);
        $this->addEnvKey('SVARIUM_TENANCY_ENABLED', $tenancyEnabled ? 'true' : 'false');
        $this->addEnvKey('SVARIUM_TENANCY_MODE', $tenancyMode);
        $this->addEnvKey('SVARIUM_TENANCY_DOMAINS_ENABLED', $tenancyDomainsEnabled ? 'true' : 'false');

        $this->addConfigKey('upsoftware.php', 'panel.enabled', true, true);
        $this->addConfigKey('upsoftware.php', 'panel.name', $panelName, true);
        $this->addConfigKey('upsoftware.php', 'panel.prefix', $panelPrefix, true);
        $this->addConfigKey('upsoftware.php', 'panel.route_prefix', $authRoutePrefix, true);
        $this->addConfigKey('upsoftware.php', 'components.prefix', $componentPrefix, true);

        $this->addConfigKey('upsoftware.php', 'api.enabled', $apiEnabled, true);
        $this->addConfigKey('upsoftware.php', 'api.prefix', $apiPrefix, true);
        $this->addConfigKey('upsoftware.php', 'api.auth.guard', $apiGuard, true);
        $this->addConfigKey('upsoftware.php', 'api.auth.middleware', ["auth:{$apiGuard}"], true);

        $this->addConfigKey('upsoftware.php', 'auth.register.enabled', $registerEnabled, true);
        $this->addConfigKey('upsoftware.php', 'auth.register.auto_login', $registerAutoLogin, true);
        $this->addConfigKey('upsoftware.php', 'auth.register.activation.mode', $registerActivationMode, true);
        $this->addConfigKey('upsoftware.php', 'auth.register.login_redirect_route', "{$authRoutePrefix}.login", true);

        $this->addConfigKey('upsoftware.php', 'tenancy.enabled', $tenancyEnabled, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.mode', $tenancyMode, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.domains.enabled', $tenancyDomainsEnabled, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.column.model_maps.domains.enabled', $tenancyDomainTablesEnabled, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.owner.enabled', false, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.owner.type_column', 'owner_type', true);
        $this->addConfigKey('upsoftware.php', 'tenancy.owner.id_column', 'owner_id', true);
        $this->addConfigKey('upsoftware.php', 'tenancy.owner.map', [], true);
        $this->addConfigKey('upsoftware.php', 'tenancy.profile.enabled', true, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.profile.table', 'tenant_profiles', true);
        $this->addConfigKey('upsoftware.php', 'tenancy.profile.foreign_key', 'tenant_id', true);
        $this->addConfigKey('upsoftware.php', 'tenancy.profile.model', '\Upsoftware\Svarium\Models\TenantProfile', true);
        $this->addConfigKey('upsoftware.php', 'tenancy.paths.tenant_migrations', app_path('Svarium/Tenancy/Migrations'), true);
        $this->addConfigKey('upsoftware.php', 'tenancy.paths.migrations', app_path('Svarium/Tenancy/Migrations'), true);
        $this->addConfigKey('upsoftware.php', 'tenancy.paths.tenant_seeders', app_path('Svarium/Tenancy/Seeders'), true);
        $this->addConfigKey('upsoftware.php', 'tenancy.paths.seeders', app_path('Svarium/Tenancy/Seeders'), true);
        $this->addConfigKey('upsoftware.php', 'tenancy.seeders.namespace', 'App\\Svarium\\Tenancy\\Seeders', true);
        $this->addConfigKey('upsoftware.php', 'tenancy.domains.central_domains', $tenancyCentralDomains, true);

        if ($tenancyDomainTablesEnabled) {
            $this->addConfigKey('upsoftware.php', 'tenancy.column.model_maps.domains.table', 'model_has_domains', true);
            $this->addConfigKey('upsoftware.php', 'tenancy.column.model_maps.domains.domain_key', 'domain_id', true);
        }

        $migrationsDir = app_path('Svarium/Tenancy/Migrations');
        $seedersDir = app_path('Svarium/Tenancy/Seeders');

        if (! File::isDirectory($migrationsDir)) {
            File::makeDirectory($migrationsDir, 0755, true, true);
        }

        if (! File::isDirectory($seedersDir)) {
            File::makeDirectory($seedersDir, 0755, true, true);
        }

        if (
            $tenancyEnabled
            && (bool) ($config['run_tenant_install_command'] ?? false)
        ) {
            $this->call('svarium:tenant.install');
        }

        $subscriptionsEnabled = (bool) ($config['subscriptions_enabled'] ?? false);
        $this->call('svarium:subscription.install', [
            '--enable' => $subscriptionsEnabled ? 'true' : 'false',
            '--migrate' => $subscriptionsEnabled ? 'true' : 'false',
        ]);

        $this->ensurePanelsFile($panelName, $panelPrefix);
    }

    protected function configureRolesAndAdmin(): array
    {
        $this->newLine();
        $this->info('Konfiguracja ról i konta');

        $manageRoles = confirm(
            'Czy dodać role do systemu?',
            true,
            'Tak',
            'Nie'
        );

        $roles = ['Administrator', 'Superadministrator'];
        if ($manageRoles) {
            while (confirm(
                'Czy dodać kolejną rolę?',
                false,
                'Tak',
                'Nie'
            )) {
                while (true) {
                    $roleName = trim((string) text(
                        'Nazwa roli',
                        ''
                    ));

                    if ($roleName === '') {
                        $this->warn('Nazwa roli nie może być pusta.');

                        continue;
                    }

                    if (in_array($roleName, $roles, true)) {
                        $this->warn('Ta rola jest już dodana.');

                        continue;
                    }

                    $roles[] = $roleName;

                    break;
                }
            }
        }

        $roleOptions = [];
        foreach ($roles as $roleName) {
            $roleOptions[$roleName] = $roleName;
        }

        $defaultBypassRoles = array_values(array_filter(
            $roles,
            static fn (string $roleName): bool => strtolower(trim($roleName)) === 'superadministrator'
        ));

        $bypassRoles = $this->input->isInteractive()
            ? array_values(array_filter(array_map(
                static fn (mixed $value): string => trim((string) $value),
                (array) multiselect(
                    label: 'Wybierz role z bypass tenancy',
                    options: $roleOptions,
                    default: $defaultBypassRoles
                )
            ), static fn (string $value): bool => $value !== ''))
            : $defaultBypassRoles;

        $createAccount = confirm(
            'Czy utworzyć konto?',
            true,
            'Tak',
            'Nie'
        );

        $accountName = 'Administrator';
        $accountEmail = '';
        $accountPassword = '';
        $accountRoles = ['Administrator'];

        if ($createAccount) {
            $accountName = trim((string) text('Nazwa konta', 'Administrator'));
            if ($accountName === '') {
                $accountName = 'Administrator';
            }

            $accountEmail = trim((string) text(
                'E-mail konta (puste = losowy)',
                ''
            ));

            $accountPassword = (string) text(
                'Hasło konta (puste = losowe)',
                ''
            );

            while (true) {
                $selectedRoles = $this->input->isInteractive()
                    ? array_values(array_filter(array_map(
                        static fn (mixed $value): string => trim((string) $value),
                        (array) multiselect(
                            label: 'Wybierz role',
                            options: $roleOptions,
                            default: $accountRoles
                        )
                    ), static fn (string $value): bool => $value !== ''))
                    : $accountRoles;

                if ($selectedRoles !== []) {
                    $accountRoles = array_values(array_unique($selectedRoles));
                    break;
                }

                $this->warn('Wybierz przynajmniej jedną rolę.');
            }
        }

        return [
            'manage_roles' => $manageRoles,
            'roles' => $roles,
            'bypass_roles' => $bypassRoles,
            'create_account' => $createAccount,
            'account_name' => $accountName,
            'account_email' => $accountEmail,
            'account_password' => $accountPassword,
            'account_roles' => $accountRoles,
        ];
    }

    protected function applyRolesAndAdmin(array $config): void
    {
        $roles = array_values(array_unique(array_filter(array_map(
            static fn (string $role) => trim($role),
            (array) ($config['roles'] ?? [])
        ))));

        if (! in_array('Administrator', $roles, true)) {
            array_unshift($roles, 'Administrator');
        }
        if (! in_array('Superadministrator', $roles, true)) {
            $roles[] = 'Superadministrator';
        }

        $guard = trim((string) config('auth.defaults.guard', 'web'));
        if ($guard === '') {
            $guard = 'web';
        }

        $userModelClass = $this->resolveUserModelClass();
        if (class_exists($userModelClass)) {
            $guard = $this->resolveGuardNameForUserModel($userModelClass, $guard);
        }

        $roleModelClass = (string) config('permission.models.role', \Spatie\Permission\Models\Role::class);
        $roleNameIsJson = $this->isRoleNameJsonColumn($roleModelClass);

        $createdRoles = 0;
        if ((bool) ($config['manage_roles'] ?? false)) {
            if (! class_exists($roleModelClass)) {
                $this->warn("Nie znaleziono modelu roli: {$roleModelClass}");
            } else {
                foreach ($roles as $roleName) {
                    try {
                        [$role, $wasCreated] = $this->findOrCreateRole(
                            $roleModelClass,
                            $roleName,
                            $guard,
                            $roleNameIsJson
                        );

                        if ($wasCreated) {
                            $createdRoles++;
                        }
                    } catch (\Throwable $e) {
                        $this->warn("Nie udało się utworzyć roli {$roleName}: {$e->getMessage()}");
                    }
                }

                $this->info('Role gotowe. Utworzono nowych: '.$createdRoles);
            }
        }

        $bypassRoleKeys = array_values(array_filter(array_map(
            fn (mixed $roleName): string => $this->resolveRoleKey((string) $roleName),
            (array) ($config['bypass_roles'] ?? [])
        ), static fn (string $value): bool => $value !== ''));

        $bypassRoleKeys = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            $bypassRoleKeys
        ))));

        $this->addConfigKey('upsoftware.php', 'auth.tenant_bypass_role_keys', $bypassRoleKeys, true);

        if (! (bool) ($config['create_account'] ?? false)) {
            return;
        }

        if (! class_exists($userModelClass)) {
            $this->warn("Nie znaleziono modelu użytkownika: {$userModelClass}");

            return;
        }

        try {
            $this->createUserWithRoles(
                userModelClass: $userModelClass,
                roleModelClass: $roleModelClass,
                roleNameIsJson: $roleNameIsJson,
                guard: $guard,
                roleNames: (array) ($config['account_roles'] ?? ['Administrator']),
                userName: (string) ($config['account_name'] ?? 'Administrator'),
                userEmail: (string) ($config['account_email'] ?? ''),
                userPassword: (string) ($config['account_password'] ?? ''),
                generatedEmailPrefix: 'account',
                accountLabel: 'konta'
            );
        } catch (\Throwable $e) {
            $this->warn('Nie udało się utworzyć konta: '.$e->getMessage());
        }
    }

    protected function createUserWithRoles(
        string $userModelClass,
        string $roleModelClass,
        bool $roleNameIsJson,
        string $guard,
        array $roleNames,
        string $userName,
        string $userEmail,
        string $userPassword,
        string $generatedEmailPrefix,
        string $accountLabel
    ): void {
        $userName = trim($userName);
        if ($userName === '') {
            $userName = 'User';
        }

        $userEmail = trim($userEmail);
        $generatedEmail = false;
        if ($userEmail === '') {
            $slug = Str::of((string) env('APP_NAME', 'app'))->lower()->slug('-')->toString();
            if ($slug === '') {
                $slug = 'app';
            }

            $prefix = trim($generatedEmailPrefix) !== '' ? trim($generatedEmailPrefix) : 'user';
            $userEmail = $prefix.'+'.Str::lower(Str::random(8)).'@'.$slug.'.local';
            $generatedEmail = true;
        }

        $userPassword = (string) $userPassword;
        $generatedPassword = false;
        if (trim($userPassword) === '') {
            $userPassword = Str::random(20);
            $generatedPassword = true;
        }

        $userPrototype = new $userModelClass;
        $usersTable = $userPrototype->getTable();

        $emailColumnExists = Schema::hasColumn($usersTable, 'email');
        $passwordColumnExists = Schema::hasColumn($usersTable, 'password');

        if (! $emailColumnExists || ! $passwordColumnExists) {
            throw new \RuntimeException("Tabela {$usersTable} nie ma wymaganych kolumn email/password.");
        }

        $user = $userModelClass::query()->firstOrNew(['email' => $userEmail]);

        if (Schema::hasColumn($usersTable, 'name')) {
            $user->name = $userName;
        }

        if (Schema::hasColumn($usersTable, 'email_verified_at') && empty($user->email_verified_at)) {
            $user->email_verified_at = now();
        }

        $user->password = Hash::make($userPassword);
        $user->save();

        $roleNames = array_values(array_unique(array_filter(array_map(
            static fn (mixed $roleName): string => trim((string) $roleName),
            $roleNames
        ), static fn (string $roleName): bool => $roleName !== '')));

        if ($roleNames === []) {
            $roleNames = ['Administrator'];
        }

        foreach ($roleNames as $roleName) {
            [$role] = $this->findOrCreateRole(
                $roleModelClass,
                $roleName,
                $guard,
                $roleNameIsJson
            );

            $this->attachRoleToUser($user, $role);
        }

        $this->info('Konto '.$accountLabel.' jest gotowe.');
        $this->line('Email: '.$userEmail);
        $this->line('Hasło: '.$userPassword);
        $this->line('Role: '.implode(', ', $roleNames));

        if ($generatedEmail) {
            $this->warn('Email został wygenerowany automatycznie.');
        }

        if ($generatedPassword) {
            $this->warn('Hasło zostało wygenerowane automatycznie.');
        }
    }

    protected function attachRoleToUser(object $user, object $role): void
    {
        try {
            if (method_exists($user, 'syncRoles')) {
                $user->syncRoles([$role]);

                return;
            }

            if (method_exists($user, 'assignRole')) {
                $user->assignRole($role);

                return;
            }
        } catch (\Throwable $e) {
            $this->warn('Przypisanie roli przez Spatie nie powiodło się. Używam fallback model_has_roles. Powód: '.$e->getMessage());
        }

        $this->attachRoleToUserPivot($user, $role);
    }

    protected function attachRoleToUserPivot(object $user, object $role): void
    {
        $table = (string) config('permission.table_names.model_has_roles', 'model_has_roles');
        $modelKeyColumn = (string) config('permission.column_names.model_morph_key', 'model_id');
        $userKey = method_exists($user, 'getKey') ? $user->getKey() : ($user->{$modelKeyColumn} ?? $user->id ?? null);
        $roleId = $role->id ?? null;
        $userModelType = svarium_model_type($user);

        if (! Schema::hasTable($table)) {
            $this->warn("Brak tabeli {$table}. Nie mogę przypisać roli administratora.");

            return;
        }

        if ($userKey === null || $roleId === null) {
            $this->warn('Brak identyfikatora użytkownika lub roli. Pomijam przypisanie roli.');

            return;
        }

        $attributes = [
            'role_id' => $roleId,
            'model_type' => $userModelType,
            $modelKeyColumn => $userKey,
        ];

        $values = [];

        if (Schema::hasColumn($table, 'status')) {
            $values['status'] = 1;
        }

        if (Schema::hasColumn($table, 'tenant_id') && ! array_key_exists('tenant_id', $attributes)) {
            $values['tenant_id'] = null;
        }

        $modelHasRoleClass = (string) config('upsoftware.models.model_has_role', \Upsoftware\Svarium\Models\ModelHasRole::class);
        $connection = null;

        if (class_exists($modelHasRoleClass)) {
            $connection = (new $modelHasRoleClass)->getConnectionName();
        }

        $query = is_string($connection) && $connection !== ''
            ? DB::connection($connection)->table($table)
            : DB::table($table);

        $query->updateOrInsert($attributes, $values);

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    protected function isRoleNameJsonColumn(string $roleModelClass): bool
    {
        if (! class_exists($roleModelClass)) {
            return false;
        }

        try {
            $roleModel = new $roleModelClass;
            $table = $roleModel->getTable();

            if (! Schema::hasColumn($table, 'name')) {
                return false;
            }

            $type = strtolower((string) Schema::getColumnType($table, 'name'));

            return in_array($type, ['json', 'jsonb'], true);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function findOrCreateRole(
        string $roleModelClass,
        string $roleName,
        string $guard,
        bool $roleNameIsJson
    ): array {
        $roleKey = $this->resolveRoleKey($roleName);
        $hasRoleKeyColumn = $this->roleTableHasRoleKeyColumn($roleModelClass);

        if ($hasRoleKeyColumn && $roleKey !== '') {
            $existingByKey = $roleModelClass::query()
                ->where('guard_name', $guard)
                ->where('role_key', $roleKey)
                ->first();

            if ($existingByKey) {
                return [$existingByKey, false];
            }
        }

        if (! $roleNameIsJson) {
            $role = $roleModelClass::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => $guard,
            ]);

            if ($hasRoleKeyColumn && trim((string) $role->getAttribute('role_key')) === '') {
                $role->setAttribute('role_key', $roleKey);
                $role->save();
            }

            return [$role, (bool) $role->wasRecentlyCreated];
        }

        $roles = $roleModelClass::query()
            ->where('guard_name', $guard)
            ->get();

        foreach ($roles as $existingRole) {
            if ($this->resolveRoleDisplayName($existingRole) === $roleName) {
                if ($hasRoleKeyColumn && trim((string) $existingRole->getAttribute('role_key')) === '') {
                    $existingRole->setAttribute('role_key', $roleKey);
                    $existingRole->save();
                }

                return [$existingRole, false];
            }
        }

        $locale = (string) app()->getLocale();
        if ($locale === '') {
            $locale = 'en';
        }

        $role = new $roleModelClass;
        $role->guard_name = $guard;

        if (method_exists($role, 'setTranslation')) {
            $role->setTranslation('name', $locale, $roleName);
        } else {
            $role->name = json_encode([$locale => $roleName], JSON_UNESCAPED_UNICODE);
        }

        if ($hasRoleKeyColumn) {
            $role->setAttribute('role_key', $roleKey);
        }

        $role->save();

        return [$role, true];
    }

    protected function resolveRoleDisplayName(object $role): string
    {
        $locale = (string) app()->getLocale();

        if (method_exists($role, 'getTranslation')) {
            try {
                $translated = $role->getTranslation('name', $locale, false);
                if (is_string($translated) && trim($translated) !== '') {
                    return trim($translated);
                }
            } catch (\Throwable) {
                // fallback below
            }
        }

        $name = $role->name ?? null;

        if (is_string($name)) {
            $decoded = json_decode($name, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $candidate = $decoded[$locale] ?? reset($decoded);

                return is_string($candidate) ? trim($candidate) : '';
            }

            return trim($name);
        }

        if (is_array($name)) {
            $candidate = $name[$locale] ?? reset($name);

            return is_string($candidate) ? trim($candidate) : '';
        }

        return '';
    }

    protected function roleTableHasRoleKeyColumn(string $roleModelClass): bool
    {
        if (! class_exists($roleModelClass)) {
            return false;
        }

        try {
            $roleModel = new $roleModelClass;
            $table = $roleModel->getTable();

            return Schema::hasTable($table) && Schema::hasColumn($table, 'role_key');
        } catch (\Throwable) {
            return false;
        }
    }

    protected function resolveRoleKey(string $roleName): string
    {
        $normalized = Str::of($roleName)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        if (in_array($normalized, ['superadministrator', 'super_admin', 'superadmin'], true)) {
            return 'superadmin';
        }

        if (in_array($normalized, ['administrator', 'admin'], true)) {
            return 'admin';
        }

        return $normalized !== '' ? $normalized : 'role';
    }

    public function handle()
    {
        if (! $this->ensureMysqlDatabaseConnection()) {
            return self::FAILURE;
        }

        if ($this->isSvariumConfigured()) {
            $shouldReconfigure = confirm(
                $this->tt(
                    'app.init_ui.reconfigure_prompt',
                    'Wykryto aktywną konfigurację Svarium (SVARIUM=enabled). Czy chcesz ponownie rekonfigurować?'
                ),
                false,
                $this->tt('common.yes', 'Tak'),
                $this->tt('common.no', 'Nie')
            );

            if (! $shouldReconfigure) {
                $this->info($this->tt('app.init_ui.reconfigure_cancelled', 'Przerwano. Konfiguracja nie została zmieniona.'));

                return self::SUCCESS;
            }
        }

        $this->call('vendor:publish', [
            '--provider' => 'Spatie\\Permission\\PermissionServiceProvider',
        ]);

        $this->call('vendor:publish', [
            '--provider' => "LaravelLang\Config\ServiceProvider",
        ]);

        $this->call('vendor:publish', [
            '--provider' => "Vinkla\Hashids\HashidsServiceProvider",
        ]);

        passthru('php artisan vendor:publish --tag=upsoftware');
        passthru('php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"');
        passthru('php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"');
        passthru('php artisan vendor:publish --provider="hisorange\BrowserDetect\ServiceProvider"');
        passthru('SVARIUM_ALLOW_MIGRATE=1 php artisan migrate');

        $this->configureApplicationLocale();

        $this->updateUserModel();
        $this->updateAppBootstrap();
        $this->resources();

        $this->addConfigKey('activitylog.php', 'activity_model', '\Upsoftware\Svarium\Models\Activity::class', true);
        $this->addConfigKey('browser-detect.php', 'cache.interval', 0, true);
        $coreConfig = $this->configureCoreOptions();
        $this->applyCoreConfiguration($coreConfig);
        $this->syncAppTsPrefixFallback((string) ($coreConfig['component_prefix'] ?? ''));

        passthru('SVARIUM_ALLOW_MIGRATE=1 php artisan migrate');
        if (confirm(
            $this->tt('app.init_ui.native_install_prompt', 'Czy uruchomić instalację natywną (php artisan svarium:native install)?'),
            true,
            $this->tt('common.yes', 'Tak'),
            $this->tt('common.no', 'Nie')
        )) {
            $nativeExitCode = $this->call('svarium:native', [
                'action' => 'install',
            ]);

            if ($nativeExitCode !== self::SUCCESS) {
                $this->warn($this->tt('app.init_ui.native_install_failed', 'Komenda svarium:native install zakończyła się błędem.'));
            }
        }

        $rolesAndAdminConfig = $this->configureRolesAndAdmin();
        $this->applyRolesAndAdmin($rolesAndAdminConfig);

        while (true) {
            if (! confirm(
                $this->tt('app.init_ui.add_next_language_confirm', 'Czy chcesz dodać język (lub kolejny)?'),
                true,
                $this->tt('common.yes', 'Tak'),
                $this->tt('common.no', 'Nie')
            )) {
                break;
            }

            while (true) {
                $code = $this->promptForLocaleCode(
                    $this->tt('app.init_ui.enter_language_code', 'Wpisz kod języka (np. pl, en, de, es)')
                );

                $this->info($this->tt('app.init_ui.adding_language', 'Dodawanie języka: :locale ...', ['locale' => $code]));
                $this->call('svarium:lang.add', ['lang' => [$code]]);
                $this->persistLocaleSetting($code);
                $this->newLine();

                break;
            }
        }

        $this->call('svarium:auth.socials.install');

        $app_name = $this->ask('Nazwa aplikacji', env('APP_NAME'));
        $this->addEnvKey('APP_NAME', $app_name);
        $this->syncAppTsAppNameFallback((string) $app_name);

        $this->addLoginConfiguration();

        $this->markSvariumConfigured();
        $this->info($this->tt('app.init_ui.config_flag_set', 'Ustawiono flagę konfiguracji: SVARIUM=enabled'));

        $this->info($this->tt('app.init_ui.done', 'Gotowe!'));
    }
}
