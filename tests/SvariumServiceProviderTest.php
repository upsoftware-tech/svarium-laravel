<?php

namespace Upsoftware\Svarium\Tests;

use Illuminate\Contracts\Routing\Registrar as Router;
use Upsoftware\Svarium\Http\Middleware\AuthenticateMiddleware;
use Upsoftware\Svarium\Services\DeviceTracking\DeviceTracking;
use Upsoftware\Svarium\Services\LayoutService;

class SvariumServiceProviderTest extends TestCase
{
    /** @test */
    public function it_registers_singletons_for_layout_and_device_tracking(): void
    {
        $layout = $this->app->make('layout');
        $deviceTracking = $this->app->make('device-tracking');

        $this->assertInstanceOf(LayoutService::class, $layout);
        $this->assertInstanceOf(DeviceTracking::class, $deviceTracking);

        // Ensure they are singletons.
        $this->assertSame($layout, $this->app->make('layout'));
        $this->assertSame($deviceTracking, $this->app->make('device-tracking'));
    }

    /** @test */
    public function it_registers_auth_panel_middleware_alias(): void
    {
        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app->make(Router::class);

        $middleware = $router->getMiddleware();

        $this->assertArrayHasKey('auth.panel', $middleware);
        $this->assertSame(AuthenticateMiddleware::class, $middleware['auth.panel']);
    }
}

