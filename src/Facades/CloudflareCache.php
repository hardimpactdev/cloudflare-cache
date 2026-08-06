<?php

declare(strict_types=1);

namespace HardImpact\CloudflareCache\Facades;

use HardImpact\CloudflareCache\Client\CloudflareClient;
use HardImpact\CloudflareCache\CloudflareCacheManager;
use HardImpact\CloudflareCache\Contracts\PurgesCloudflareUrls;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\HttpFoundation\Response;

/**
 * @method static bool enabled()
 * @method static bool shouldOperate()
 * @method static bool shouldApplyHeaders(?Request $request = null)
 * @method static Response applyCacheHeaders(Response $response, array{max_age?: int, s_maxage?: int, stale_while_revalidate?: int|null, stale_if_error?: int|null} $overrides = [])
 * @method static void purge(string|list<string>|PurgesCloudflareUrls|Model $urls, bool $async = true, bool $warm = false)
 * @method static void purgeNow(list<string> $urls)
 * @method static void purgeEverything(bool $async = true)
 * @method static void warm(string|list<string>|PurgesCloudflareUrls|Model $urls, bool $async = true)
 * @method static void warmNow(list<string> $urls)
 * @method static CloudflareClient client()
 *
 * @see CloudflareCacheManager
 */
class CloudflareCache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CloudflareCacheManager::class;
    }
}
