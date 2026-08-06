# Cloudflare Cache for Laravel

Easy Cloudflare edge caching for Laravel apps:

- **Middleware** that sets safe `Cache-Control` headers for public pages
- **Purge** one URL, many URLs, or an entire zone (sync or queued)
- **Optional warming** after purge
- **Model trait** to purge when Filament / Eloquent content changes

Designed for brochure sites and marketing pages (lindaretel, SRPM, etc.) behind Cloudflare — including Laravel Cloud’s Cloudflare edge when you manage the zone yourself for per-URL purge.

## Installation

```bash
composer require hardimpactdev/cloudflare-cache
```

Publish config (optional):

```bash
php artisan vendor:publish --tag=cloudflare-cache-config
```

## Environment

```env
CLOUDFLARE_CACHE_ENABLED=true
CLOUDFLARE_CACHE_ENVIRONMENTS=production,staging

CLOUDFLARE_ZONE_ID=your-zone-id
CLOUDFLARE_API_TOKEN=your-scoped-token

# Optional
CLOUDFLARE_CACHE_S_MAXAGE=3600
CLOUDFLARE_CACHE_MAX_AGE=0
CLOUDFLARE_CACHE_STALE_WHILE_REVALIDATE=86400
CLOUDFLARE_CACHE_PURGE_ASYNC=true
CLOUDFLARE_CACHE_WARM_AFTER_PURGE=false
CLOUDFLARE_CACHE_QUEUE=
```

Create a Cloudflare API token with **Zone → Cache Purge** limited to the site zone.

## Cacheable routes (critical)

Cloudflare will **not** cache responses that set cookies. Do **not** put this middleware only on the default `web` stack.

### With Waymaker (recommended)

Keep a single Waymaker-generated routes file. Opt pages into a cookie-free stack with a **middleware group**:

```php
// bootstrap/app.php
use HardImpact\CloudflareCache\Support\StaticMiddleware;
use HardImpact\Waymaker\Facades\Waymaker;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php', // optional non-Waymaker web routes
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Load Waymaker outside the forced `web` wrapper so `static` is top-level
            Waymaker::routes();
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->group('static', StaticMiddleware::defaults([
            \App\Http\Middleware\HandleInertiaRequests::class,
            // CSP / other cookie-free middleware…
        ]));
    })
    ->create();
```

```php
// app/Http/Controllers/ProjectsController.php
use HardImpact\Waymaker\Get;

class ProjectsController extends Controller
{
    public static string $middlewareGroup = 'static';

    #[Get(uri: '/', name: 'projects')]
    public function index(): Response { ... }
}
```

Per-route override (same controller can mix groups):

```php
#[Get(uri: '/pricing', name: 'pricing', middlewareGroup: 'static')]
public function pricing(): Response { ... }

#[Get(uri: '/account', name: 'account', middlewareGroup: 'web', middleware: 'auth')]
public function account(): Response { ... }
```

`StaticMiddleware::defaults()` is:

1. `SubstituteBindings`
2. Your `$before` stack (Inertia share, etc.)
3. `CacheResponse` (this package)
4. Your `$after` stack

Requires **Waymaker** with `middlewareGroup` support (see Waymaker changelog / docs).

### Without Waymaker

Alias is also registered for classic routes:

```php
Route::middleware('static')->group(function () {
    Route::get('/privacy', ...)->name('privacy');
});

// or
Route::get('/pricing', ...)->middleware('cloudflare.cache:86400');
```

Default headers:

```http
Cache-Control: public, max-age=0, s-maxage=3600, stale-while-revalidate=86400, stale-if-error=86400
```

Middleware skips: non-GET, non-2xx, authenticated users, `Set-Cookie` responses, disabled env / package.

### Cloudflare dashboard

For HTML to be eligible, ensure a **Cache Everything** (or equivalent) rule with **Browser TTL: respect origin**. Static extensions are cached by Cloudflare by default.



## Inertia + SSR (Cloudflare only)

Document visits and Inertia navigations share the same URL:

| Request | Expects | Edge cache |
|---------|---------|------------|
| Full page load | SSR HTML | Yes — `public, s-maxage=…` |
| Inertia XHR (`X-Inertia: true`) | JSON | **No** — `private, no-store` |

Cloudflare free/pro **does not vary the cache key on `X-Inertia`**. Relying on `Vary` alone will serve HTML to Inertia navigations.

This package:

1. Edge-caches **document** visits only
2. Forces **`private, no-store`** when `X-Inertia` is present
3. Still sets `Vary: Accept-Encoding, X-Inertia` for correctness elsewhere

### Required Cloudflare Cache Rule

Cache only when it is **not** an Inertia navigation:

```text
not len(http.request.headers["x-inertia"]) > 0
```

Action: **Cache Everything**, Browser TTL: **respect origin**.

Warming fetches document HTML only (no `X-Inertia` header).

There is **no** second origin cache in this package — only Cloudflare edge headers, purge, and warm.


## Purge

```php
use HardImpact\CloudflareCache\Facades\CloudflareCache;

CloudflareCache::purge('https://example.com/');
CloudflareCache::purge([
    'https://example.com/',
    'https://example.com/projecten/sion',
]);

// Sync (CLI / tests)
CloudflareCache::purge($urls, async: false);

// Purge then warm
CloudflareCache::purge($urls, warm: true);

// Whole zone
CloudflareCache::purgeEverything();
```

### Eloquent / Filament

```php
use HardImpact\CloudflareCache\Concerns\PurgesCloudflareCache;
use HardImpact\CloudflareCache\Contracts\PurgesCloudflareUrls;
use Illuminate\Database\Eloquent\Model;

class PortfolioItem extends Model implements PurgesCloudflareUrls
{
    use PurgesCloudflareCache;

    public function cloudflareCacheUrls(): array
    {
        return array_values(array_filter([
            route('projects', absolute: true),
            route('portfolio-item', $this->slug, absolute: true),
            $this->wasChanged('slug')
                ? route('portfolio-item', $this->getOriginal('slug'), absolute: true)
                : null,
        ]));
    }
}
```

Or call from Filament:

```php
protected function afterSave(): void
{
    CloudflareCache::purge($this->record, warm: true);
}
```

## Artisan

```bash
php artisan cloudflare-cache:purge https://example.com/ https://example.com/studio
php artisan cloudflare-cache:purge --all --sync
php artisan cloudflare-cache:purge https://example.com/ --sync --warm

php artisan cloudflare-cache:warm https://example.com/ --sync
```

## Warming

Off after purge by default (`CLOUDFLARE_CACHE_WARM_AFTER_PURGE=false`). Enable per call with `warm: true`, or set the env flag globally. Warming issues cookie-less GET requests so the edge can re-fill quickly after invalidation.

## Inertia + Cloudflare (required Cache Rules)

Inertia serves **HTML** and **JSON** on the same URL (`X-Inertia: true` for partials). Cloudflare free/pro does **not** vary the cache key on that header, so a cached document will be served to Inertia XHRs unless you bypass.

This package sets `private, no-store` on Inertia responses (store-side). You also need a **lookup-side** Cache Rule on a zone that actually proxies traffic:

1. **Bypass cache** when `any(http.request.headers["x-inertia"][*] == "true")`
2. **Eligible for cache / Cache Everything** for other `GET`s (respect origin `Cache-Control`)

DNS for the site must be **proxied** (orange cloud) on that zone. Grey-cloud DNS (DNS only) means your Cache Rules never run — traffic hits Laravel Cloud’s managed edge instead, which cannot set per-header bypass rules.

## Laravel Cloud note

Laravel Cloud’s built-in edge purge API clears the **whole environment**. This package targets **your Cloudflare zone** for precise URL purge (Filament saves). Use both: deploy purge from Cloud, content purge from this package.

If the custom domain is DNS-only to Laravel Cloud, HTML may still be edge-cached by Cloud’s Cloudflare when you send `public, s-maxage`. Without Cache Rules you control, Inertia navigations will get that HTML — either orange-cloud through your zone with the rules above, or stop sharing HTML at the edge (`private`) for those pages.

## Testing

```bash
composer test:unit
composer test
```

## License

MIT
