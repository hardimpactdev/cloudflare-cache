<?php

declare(strict_types=1);

namespace HardImpact\CloudflareCache\Concerns;

use HardImpact\CloudflareCache\Contracts\PurgesCloudflareUrls;
use HardImpact\CloudflareCache\Facades\CloudflareCache;

/**
 * @phpstan-require-implements PurgesCloudflareUrls
 */
trait PurgesCloudflareCache
{
    public static function bootPurgesCloudflareCache(): void
    {
        static::saved(function (self $model): void {
            CloudflareCache::purge($model);
        });

        static::deleted(function (self $model): void {
            CloudflareCache::purge($model);
        });
    }
}
