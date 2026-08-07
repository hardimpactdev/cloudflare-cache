<?php

declare(strict_types=1);

namespace NckRtl\CloudflareCache;

use NckRtl\CloudflareCache\Client\CloudflareClient;
use NckRtl\CloudflareCache\Console\Commands\PurgeCommand;
use NckRtl\CloudflareCache\Console\Commands\WarmCommand;
use NckRtl\CloudflareCache\Http\Middleware\CacheResponse;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class CloudflareCacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cloudflare-cache.php', 'cloudflare-cache');

        $this->app->singleton(CloudflareClient::class, function (): CloudflareClient {
            return new CloudflareClient(
                zoneId: config('cloudflare-cache.zone_id'),
                apiToken: config('cloudflare-cache.api_token'),
                baseUrl: (string) config('cloudflare-cache.api_base_url', 'https://api.cloudflare.com/client/v4'),
                softFail: (bool) config('cloudflare-cache.purge.soft_fail', true),
            );
        });

        $this->app->singleton(CloudflareCacheManager::class, function (Application $app): CloudflareCacheManager {
            return new CloudflareCacheManager($app->make(CloudflareClient::class));
        });

        $this->app->alias(CloudflareCacheManager::class, 'cloudflare-cache');
    }

    public function boot(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('cloudflare.cache', CacheResponse::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/cloudflare-cache.php' => config_path('cloudflare-cache.php'),
            ], ['cloudflare-cache', 'cloudflare-cache-config']);

            $this->commands([
                PurgeCommand::class,
                WarmCommand::class,
            ]);
        }
    }
}
