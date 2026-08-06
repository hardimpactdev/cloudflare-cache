<?php

declare(strict_types=1);

namespace HardImpact\CloudflareCache\ResponseCache;

use HardImpact\CloudflareCache\Support\InertiaRequest;
use Illuminate\Http\Request;
use Spatie\ResponseCache\Hasher\DefaultHasher;

/**
 * Origin-level cache key hasher for Inertia + SSR (spatie/laravel-responsecache).
 *
 * Separates:
 * - full document HTML (no X-Inertia)
 * - Inertia JSON visits (X-Inertia)
 * - partial reloads (X-Inertia-Partial-Data / Except)
 * - asset version mismatches (X-Inertia-Version)
 *
 * Prefer this over hashing Content-Type alone: Inertia XHR often still Accepts
 * text/html, so Content-Type is not a reliable request discriminator.
 *
 * @see https://flareapp.io/blog/caching-inertias-ssr-responses
 */
class InertiaCacheHasher extends DefaultHasher
{
    public function getHashFor(Request $request): string
    {
        return parent::getHashFor($request).'-'.InertiaRequest::cacheKeyFragment($request);
    }
}
