<?php

declare(strict_types=1);

namespace HardImpact\CloudflareCache\ResponseCache;

use Illuminate\Http\Request;
use Spatie\ResponseCache\CacheProfiles\CacheAllSuccessfulGetRequests;

/**
 * Origin-level cache profile for Inertia + SSR (spatie/laravel-responsecache).
 *
 * Unlike the default profile, AJAX/Inertia GET navigations are eligible so
 * SSR HTML document visits and subsequent Inertia JSON visits can both be
 * cached at the origin — each under a distinct hash (see InertiaCacheHasher).
 *
 * @see https://flareapp.io/blog/caching-inertias-ssr-responses
 */
class InertiaCacheProfile extends CacheAllSuccessfulGetRequests
{
    public function shouldCacheRequest(Request $request): bool
    {
        if ($this->isRunningInConsole()) {
            return false;
        }

        // Include Inertia XHR GETs (the default profile skips AJAX).
        if ($request->isMethod('get') && ($request->ajax() || $request->headers->has('X-Inertia'))) {
            return true;
        }

        return $request->isMethod('get');
    }
}
