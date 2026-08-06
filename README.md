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



## Inertia + SSR

Document visits and Inertia XHR share the same URL. Cloudflare free/pro **does not vary** the cache key on `X-Inertia`, so caching both would mix HTML and JSON.

This package therefore:

1. **Edge-caches only full document visits** (`Cache-Control: public, s-maxage=…`)
2. **Forces `private, no-store` on Inertia partials** (`X-Inertia: true`) so they never reuse the document entry
3. Sets `Vary: Accept-Encoding, X-Inertia` (useful for other caches; Cloudflare still needs the rule below)

### Cloudflare Cache Rule expression

Only cache when the request is **not** an Inertia navigation:

```text
not len(http.request.headers["x-inertia"]) > 0
```

With **Cache Everything** + **Browser TTL: respect origin**.

Warming only fetches document HTML (no `X-Inertia` header).

### Optional origin SSR cache (Flare approach)

For in-app caching of SSR HTML *and* Inertia JSON (separate keys), install Spatie Response Cache and point config at this package:

```bash
composer require spatie/laravel-responsecache
```

```php
// config/responsecache.php
'cache_profile' => \HardImpact\CloudflareCache\ResponseCache\InertiaCacheProfile::class,
'hasher' => \HardImpact\CloudflareCache\ResponseCache\InertiaCacheHasher::class,
```

Add Spatie`s `CacheResponse` middleware on the same static routes (in addition to this package`s edge headers middleware). The hasher keys on:

- document vs Inertia
- partial data / except headers
- Inertia version

Inspired by [Caching Inertia`s SSR responses (Flare)](https://flareapp.io/blog/caching-inertias-ssr-responses).

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

## Laravel Cloud note

Laravel Cloud’s built-in edge purge API clears the **whole environment**. This package targets **your Cloudflare zone** for precise URL purge (Filament saves). Use both: deploy purge from Cloud, content purge from this package.

## Testing

```bash
composer test:unit
composer test
```

## License

MIT
