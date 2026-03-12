<?php

namespace Upsoftware\Svarium\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Upsoftware\Svarium\Http\Middleware\AuthenticateMiddleware;

class AuthPerPanelRoutingTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('upsoftware.panel.name', 'app');
        $app['config']->set('upsoftware.panel.prefix', '');
        $app['config']->set('upsoftware.panel.route_prefix', 'panel.auth');
        $app['config']->set('upsoftware.panel.auth.per_panel', true);
        $app['config']->set('upsoftware.panel.auth.default_panel', 'app');

        $panelsDir = $app->basePath('app/Svarium');
        if (! is_dir($panelsDir)) {
            mkdir($panelsDir, 0777, true);
        }

        file_put_contents($panelsDir.'/panels.php', <<<'PHP'
<?php

use Upsoftware\Svarium\Panel\Panel;

return [
    Panel::make('app')->noPrefix(),
    Panel::make('admin')->prefix('admin'),
];
PHP
        );
    }

    /** @test */
    public function it_registers_auth_routes_per_panel_with_compat_alias_for_default_panel(): void
    {
        $this->assertTrue(Route::has('panel.app.auth.login'));
        $this->assertTrue(Route::has('panel.admin.auth.login'));
        $this->assertTrue(Route::has('panel.auth.login'));

        $this->assertSame('/auth/login', parse_url(route('panel.app.auth.login'), PHP_URL_PATH));
        $this->assertSame('/admin/auth/login', parse_url(route('panel.admin.auth.login'), PHP_URL_PATH));
        $this->assertSame('/auth/login', parse_url(route('panel.auth.login'), PHP_URL_PATH));
    }

    /** @test */
    public function it_redirects_unauthenticated_request_to_login_for_detected_panel(): void
    {
        $middleware = new AuthenticateMiddleware;
        $request = Request::create('/admin/private', 'GET');

        $response = $middleware->handle($request, static fn () => response('ok'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/admin/auth/login', parse_url((string) $response->headers->get('Location'), PHP_URL_PATH));
    }

    /** @test */
    public function it_treats_panel_auth_login_path_as_public_to_prevent_redirect_loop(): void
    {
        $middleware = new AuthenticateMiddleware;
        $request = Request::create('/admin/auth/login', 'GET');
        $called = false;

        $response = $middleware->handle($request, static function () use (&$called) {
            $called = true;

            return response('ok');
        });

        $this->assertTrue($called);
        $this->assertSame(200, $response->getStatusCode());
    }
}

