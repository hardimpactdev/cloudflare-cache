<?php

declare(strict_types=1);

namespace HardImpact\CloudflareCache\Support;

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

    /**
     * Stable key fragment distinguishing document vs Inertia partial visits.
     * Useful for tests and any non-CDN keying — Cloudflare free/pro ignores Vary.
     */
    public static function cacheKeyFragment(Request $request): string
    {
        if (! self::isInertia($request)) {
            return 'document';
        }

        $parts = [
            'inertia',
            'partial:'.($request->header('X-Inertia-Partial-Data') ?? ''),
            'except:'.($request->header('X-Inertia-Partial-Except') ?? ''),
            'version:'.($request->header('X-Inertia-Version') ?? ''),
        ];

        return implode('|', $parts);
    }
}
