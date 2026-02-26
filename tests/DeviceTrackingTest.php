<?php

namespace Upsoftware\Svarium\Tests;

use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Upsoftware\Svarium\Services\DeviceTracking\DeviceTracking;

class DeviceTrackingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure tracking config is loaded and has expected keys.
        $config = Config::get('upsoftware.tracking', []);

        if (empty($config)) {
            Config::set('upsoftware.tracking', [
                'device_cookie' => 'device_uuid',
                'cookie_http_only' => true,
                'session_key' => 'device-tracking',
                'hijacking_detector' => \Upsoftware\Svarium\Services\DeviceTracking\DeviceHijackingDetectorDefault::class,
                'geoip_provider' => null,
            ]);
        }
    }

    protected function createRequestWithDeviceCookie(string $cookieValue = 'test-device-uuid'): void
    {
        $cookieName = Config::get('upsoftware.tracking.device_cookie', 'device_uuid');

        $symfonyRequest = HttpRequest::create(
            '/',
            'GET',
            [],
            [$cookieName => $cookieValue],
            [],
            [
                'HTTP_USER_AGENT' => 'Svarium Test Agent',
                'REMOTE_ADDR' => '127.0.0.1',
            ]
        );

        $this->app->instance('request', $symfonyRequest);
        Request::swap($symfonyRequest);
    }

    /** @test */
    public function it_generates_consistent_request_hash_for_same_request(): void
    {
        $this->createRequestWithDeviceCookie('same-device');

        $tracking = new DeviceTracking();

        $hash1 = $tracking->getRequestHash();
        $hash2 = $tracking->getRequestHash();

        $this->assertSame($hash1, $hash2);
        $this->assertNotEmpty($hash1);
    }

    /** @test */
    public function it_uses_device_cookie_value_in_request_hash(): void
    {
        $this->createRequestWithDeviceCookie('device-a');
        $trackingA = new DeviceTracking();
        $hashA = $trackingA->getRequestHash();

        $this->createRequestWithDeviceCookie('device-b');
        $trackingB = new DeviceTracking();
        $hashB = $trackingB->getRequestHash();

        $this->assertNotSame($hashA, $hashB);
    }

    /** @test */
    public function check_session_device_hash_returns_null_when_user_not_logged_in(): void
    {
        Auth::shouldReceive('guard->check')->once()->andReturn(false);

        $tracking = new DeviceTracking();

        $this->assertNull($tracking->checkSessionDeviceHash());
    }
}

