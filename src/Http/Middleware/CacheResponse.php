<?php

declare(strict_types=1);

namespace HardImpact\CloudflareCache\Http\Middleware;

use Closure;
use HardImpact\CloudflareCache\CloudflareCacheManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
    public function __construct(
        protected readonly CloudflareCacheManager $cache,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     * @param  string|null  $sMaxage  Optional s-maxage override (seconds)
     * @param  string|null  $maxAge  Optional browser max-age override (seconds)
     */
    public function handle(Request $request, Closure $next, ?string $sMaxage = null, ?string $maxAge = null): Response
    {
        $response = $next($request);

        if (! $this->cache->shouldApplyHeaders($request)) {
            return $response;
        }

        $overrides = [];

        if ($sMaxage !== null && $sMaxage !== '') {
            $overrides['s_maxage'] = (int) $sMaxage;
        }

        if ($maxAge !== null && $maxAge !== '') {
            $overrides['max_age'] = (int) $maxAge;
        }

        return $this->cache->applyCacheHeaders($response, $overrides);
    }
}
