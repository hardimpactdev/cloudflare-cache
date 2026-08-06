<?php

declare(strict_types=1);

namespace HardImpact\CloudflareCache\Tests;

use HardImpact\CloudflareCache\CloudflareCacheServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            CloudflareCacheServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cloudflare-cache.enabled', true);
        $app['config']->set('cloudflare-cache.environments', ['testing', 'production']);
        $app['config']->set('cloudflare-cache.zone_id', 'test-zone-id');
        $app['config']->set('cloudflare-cache.api_token', 'test-api-token');
        $app['config']->set('cloudflare-cache.purge.async', false);
        $app['config']->set('cloudflare-cache.purge.soft_fail', false);
        $app['config']->set('cloudflare-cache.warm.after_purge', false);
        $app['config']->set('cloudflare-cache.warm.enabled', true);
    }
}
