<?php

declare(strict_types=1);

namespace NckRtl\CloudflareCache\Support;

use Illuminate\Http\Request;

/**
 * Detect Inertia document vs partial (XHR) visits.
 *
 * Full document visits get SSR HTML. Subsequent Inertia navigations send
 * `X-Inertia: true` and expect JSON. Those must never share a CDN cache entry
 * with the HTML document (Cloudflare free/pro ignores Vary: X-Inertia).
 *
 * @see https://flareapp.io/blog/caching-inertias-ssr-responses
 */
final class InertiaRequest
{
    public static function isInertia(Request $request): bool
    {
        return $request->headers->has('X-Inertia');
    }
}
