<?php

declare(strict_types=1);

namespace NckRtl\CloudflareCache;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use NckRtl\CloudflareCache\Client\CloudflareClient;
use NckRtl\CloudflareCache\Contracts\PurgesCloudflareUrls;
use NckRtl\CloudflareCache\Jobs\PurgeUrlsJob;
use NckRtl\CloudflareCache\Jobs\WarmUrlsJob;
use NckRtl\CloudflareCache\Support\InertiaRequest;
use NckRtl\CloudflareCache\Support\UrlNormalizer;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CloudflareCacheManager
{
    public function __construct(
        protected readonly CloudflareClient $client,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('cloudflare-cache.enabled', true);
    }

    /**
     * Whether the current environment is allowed to set edge cache headers.
     */
    protected function environmentAllowed(): bool
    {
        $environments = config('cloudflare-cache.environments', ['production']);

        return in_array(app()->environment(), $environments, true);
    }

    /**
     * Whether the package is enabled AND allowed to run in the current environment.
     * Gates header application, purge, and warm alike so a local environment with
     * production credentials can never touch the live zone.
     */
    public function shouldOperate(): bool
    {
        return $this->enabled() && $this->environmentAllowed();
    }

    public function shouldApplyHeaders(?Request $request = null): bool
    {
        if (! $this->shouldOperate()) {
            return false;
        }

        if ($request !== null) {
            if ($request->method() !== 'GET') {
                return false;
            }

            // Full document HTML only. Inertia XHR expects JSON and must not share a CDN entry.
            if (InertiaRequest::isInertia($request)) {
                return false;
            }

            if ($this->requestIsAuthenticated($request)) {
                return false;
            }

            // Session middleware ran (web group) — StartSession/AddQueuedCookiesToResponse
            // attach cookies AFTER this middleware when it's innermost, so the response-side
            // Set-Cookie check below can run too early to catch them. Skip public headers here.
            if ($request->hasSession()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Force non-cacheable headers for Inertia partial visits.
     *
     * Cloudflare free/pro does not vary the cache key on `X-Inertia`, so a public
     * HTML document would otherwise be served for Inertia JSON requests.
     */
    public function applyInertiaNoStoreHeaders(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store');
        $this->mergeVary($response, ['Accept-Encoding', 'X-Inertia']);

        return $response;
    }

    /**
     * @param  array{max_age?: int, s_maxage?: int, stale_while_revalidate?: int|null, stale_if_error?: int|null}  $overrides
     */
    public function applyCacheHeaders(Response $response, array $overrides = []): Response
    {
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return $response;
        }

        // Never encourage CDN caching of cookie-bearing responses.
        if ($response->headers->has('Set-Cookie')) {
            return $response;
        }

        $defaults = config('cloudflare-cache.headers', []);

        $maxAge = (int) ($overrides['max_age'] ?? $defaults['max_age'] ?? 0);
        $sMaxAge = (int) ($overrides['s_maxage'] ?? $defaults['s_maxage'] ?? 3600);
        $staleWhileRevalidate = $overrides['stale_while_revalidate'] ?? $defaults['stale_while_revalidate'] ?? null;
        $staleIfError = $overrides['stale_if_error'] ?? $defaults['stale_if_error'] ?? null;

        $directives = [
            'public',
            "max-age={$maxAge}",
            "s-maxage={$sMaxAge}",
        ];

        if ($staleWhileRevalidate !== null && $staleWhileRevalidate !== '') {
            $directives[] = 'stale-while-revalidate='.(int) $staleWhileRevalidate;
        }

        if ($staleIfError !== null && $staleIfError !== '') {
            $directives[] = 'stale-if-error='.(int) $staleIfError;
        }

        $response->headers->set('Cache-Control', implode(', ', $directives));

        // Document the Inertia variant even though Cloudflare free ignores custom Vary.
        // Other CDNs / browsers may still use it; origin caches should key on X-Inertia.
        $this->mergeVary($response, ['Accept-Encoding', 'X-Inertia']);

        return $response;
    }

    /**
     * @param  list<string>  $values
     */
    protected function mergeVary(Response $response, array $values): void
    {
        $existing = $response->headers->get('Vary', '');
        $parts = array_filter(array_map('trim', explode(',', (string) $existing)));
        $merged = array_values(array_unique([...$parts, ...$values]));

        $response->headers->set('Vary', implode(', ', $merged));
    }

    /**
     * @param  string|list<string>|PurgesCloudflareUrls|Model  $urls
     */
    public function purge(string|array|PurgesCloudflareUrls|Model $urls, bool $async = true, bool $warm = false): void
    {
        if (! $this->shouldOperate()) {
            return;
        }

        $list = $this->resolveUrls($urls);

        if ($list === []) {
            return;
        }

        $shouldWarm = $warm || (bool) config('cloudflare-cache.warm.after_purge', false);
        $async = $async && (bool) config('cloudflare-cache.purge.async', true);

        if ($async) {
            $pending = PurgeUrlsJob::dispatch($list, $shouldWarm);
            $queue = config('cloudflare-cache.purge.queue');

            if (is_string($queue) && $queue !== '') {
                $pending->onQueue($queue);
            }

            return;
        }

        $this->purgeNow($list);

        if ($shouldWarm && (bool) config('cloudflare-cache.warm.enabled', true)) {
            $this->warm($list, async: false);
        }
    }

    /**
     * @param  list<string>  $urls
     */
    public function purgeNow(array $urls): void
    {
        $list = UrlNormalizer::normalizeMany($urls);

        if ($list === []) {
            return;
        }

        $this->client->purgeUrls($list);
    }

    public function purgeEverything(bool $async = true): void
    {
        if (! $this->shouldOperate()) {
            return;
        }

        if ($async && (bool) config('cloudflare-cache.purge.async', true)) {
            $pending = PurgeUrlsJob::dispatch([], warm: false, purgeEverything: true);
            $queue = config('cloudflare-cache.purge.queue');

            if (is_string($queue) && $queue !== '') {
                $pending->onQueue($queue);
            }

            return;
        }

        $this->client->purgeEverything();
    }

    /**
     * @param  string|list<string>|PurgesCloudflareUrls|Model  $urls
     */
    public function warm(string|array|PurgesCloudflareUrls|Model $urls, bool $async = true): void
    {
        if (! $this->shouldOperate() || ! (bool) config('cloudflare-cache.warm.enabled', true)) {
            return;
        }

        $list = $this->resolveUrls($urls);

        if ($list === []) {
            return;
        }

        if ($async) {
            $pending = WarmUrlsJob::dispatch($list);
            $queue = config('cloudflare-cache.purge.queue');

            if (is_string($queue) && $queue !== '') {
                $pending->onQueue($queue);
            }

            return;
        }

        $this->warmNow($list);
    }

    /**
     * @param  list<string>  $urls
     */
    public function warmNow(array $urls): void
    {
        $list = UrlNormalizer::normalizeMany($urls);

        if ($list === []) {
            return;
        }

        $timeout = (int) config('cloudflare-cache.warm.timeout', 15);
        $userAgent = (string) config('cloudflare-cache.warm.user_agent');

        foreach ($list as $url) {
            try {
                Http::withHeaders([
                    'User-Agent' => $userAgent,
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                    ->withOptions(['allow_redirects' => true])
                    ->timeout($timeout)
                    ->get($url);
            } catch (Throwable) {
                // Warming is best-effort.
            }
        }
    }

    public function client(): CloudflareClient
    {
        return $this->client;
    }

    /**
     * @param  string|list<string>|PurgesCloudflareUrls|Model  $urls
     * @return list<string>
     */
    protected function resolveUrls(string|array|PurgesCloudflareUrls|Model $urls): array
    {
        if ($urls instanceof PurgesCloudflareUrls) {
            return UrlNormalizer::normalizeMany($urls->cloudflareCacheUrls());
        }

        if ($urls instanceof Model) {
            return [];
        }

        if (is_string($urls)) {
            return UrlNormalizer::normalizeMany([$urls]);
        }

        return UrlNormalizer::normalizeMany($urls);
    }

    protected function requestIsAuthenticated(Request $request): bool
    {
        if (Auth::check()) {
            return true;
        }

        $user = $request->user();

        return $user instanceof Authenticatable;
    }
}
