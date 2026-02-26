<?php

namespace Upsoftware\Svarium\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Upsoftware\Svarium\Providers\SvariumServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * Load package service provider.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            SvariumServiceProvider::class,
        ];
    }

    /**
     * Set up the environment.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Load default package config so tests can rely on it.
        $configPath = __DIR__ . '/../src/config/upsoftware.php';

        if (is_file($configPath)) {
            $app['config']->set('upsoftware', require $configPath);
        }
    }
}

