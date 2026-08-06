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

Cloudflare will **not** cache responses that set cookies. Do **not** put this middleware on the default `web` stack.

Register a **stateless** middleware group and only put public GET pages there:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->group('static', [
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        // Your Inertia shared-props middleware if it does not need sessions
        \HardImpact\CloudflareCache\Http\Middleware\CacheResponse::class,
    ]);
})
```

```php
// routes/static.php
Route::get('/', [ProjectsController::class, 'index'])->name('projects');
Route::get('/studio', ...)->name('studio');
Route::get('/projecten/{slug}', ...)->name('portfolio-item');
```

```php
// bootstrap/app.php routing
then: function () {
    Route::middleware('static')
        ->group(base_path('routes/static.php'));
},
```

Alias is also registered:

```php
Route::get('/privacy', ...)->middleware('cloudflare.cache');
Route::get('/pricing', ...)->middleware('cloudflare.cache:86400'); // s-maxage
Route::get('/news', ...)->middleware('cloudflare.cache:300,0'); // s-maxage, max-age
```

Default headers:

```http
Cache-Control: public, max-age=0, s-maxage=3600, stale-while-revalidate=86400, stale-if-error=86400
```

Middleware skips: non-GET, non-2xx, authenticated users, `Set-Cookie` responses, disabled env / package.

### Cloudflare dashboard

For HTML to be eligible, ensure a **Cache Everything** (or equivalent) rule with **Browser TTL: respect origin**. Static extensions are cached by Cloudflare by default.

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
