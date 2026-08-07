<?php

declare(strict_types=1);

namespace NckRtl\CloudflareCache\Support;

use NckRtl\CloudflareCache\Http\Middleware\CacheResponse;
use Illuminate\Routing\Middleware\SubstituteBindings;

/**
 * Helpers for building a cookie-free middleware stack for edge-cacheable pages.
 *
 * Intended to be registered as Laravel's `static` middleware group and referenced
 * from Waymaker via `public static string $middlewareGroup = 'static'`.
 */
final class StaticMiddleware
{
    /**
     * Default stack for public, cacheable HTML pages.
     *
     * Does not include session or cookie encryption — those set `Set-Cookie`
     * and prevent Cloudflare from caching HTML.
     *
     * @param  list<class-string|string>  $before  Middleware before cache headers (e.g. Inertia share)
     * @param  list<class-string|string>  $after  Middleware after cache headers
     * @return list<class-string|string>
     */
    public static function defaults(array $before = [], array $after = []): array
    {
        return array_values(array_unique([
            SubstituteBindings::class,
            ...$before,
            CacheResponse::class,
            ...$after,
        ]));
    }
}
