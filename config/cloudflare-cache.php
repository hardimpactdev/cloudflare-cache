<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enable package behaviour
    |--------------------------------------------------------------------------
    |
    | When disabled, middleware will not set cache headers and purge/warm
    | calls become no-ops (useful in local development).
    |
    */

    'enabled' => (bool) env('CLOUDFLARE_CACHE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Environments that may set edge cache headers
    |--------------------------------------------------------------------------
    */

    'environments' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CLOUDFLARE_CACHE_ENVIRONMENTS', 'production,staging')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Cloudflare API credentials
    |--------------------------------------------------------------------------
    |
    | Create a scoped API token with Zone → Cache Purge for this zone only.
    |
    */

    'zone_id' => env('CLOUDFLARE_ZONE_ID'),

    'api_token' => env('CLOUDFLARE_API_TOKEN'),

    'api_base_url' => env('CLOUDFLARE_API_BASE_URL', 'https://api.cloudflare.com/client/v4'),

    /*
    |--------------------------------------------------------------------------
    | Default Cache-Control directives (middleware)
    |--------------------------------------------------------------------------
    |
    | max_age applies to browsers. s_maxage applies to shared caches (CDN).
    | Prefer max_age=0 so browsers revalidate while the edge keeps content.
    |
    */

    'headers' => [
        'max_age' => (int) env('CLOUDFLARE_CACHE_MAX_AGE', 0),
        's_maxage' => (int) env('CLOUDFLARE_CACHE_S_MAXAGE', 3600),
        'stale_while_revalidate' => (int) env('CLOUDFLARE_CACHE_STALE_WHILE_REVALIDATE', 0),
        'stale_if_error' => (int) env('CLOUDFLARE_CACHE_STALE_IF_ERROR', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Purge behaviour
    |--------------------------------------------------------------------------
    */

    'purge' => [
        // Dispatch purge work to the queue by default (Filament-friendly).
        'queue' => env('CLOUDFLARE_CACHE_QUEUE'),
        'async' => (bool) env('CLOUDFLARE_CACHE_PURGE_ASYNC', true),
        // Soft-fail in non-production when credentials are missing.
        'soft_fail' => (bool) env('CLOUDFLARE_CACHE_SOFT_FAIL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Warm behaviour
    |--------------------------------------------------------------------------
    */

    'warm' => [
        'enabled' => (bool) env('CLOUDFLARE_CACHE_WARM_ENABLED', true),
        'after_purge' => (bool) env('CLOUDFLARE_CACHE_WARM_AFTER_PURGE', false),
        'timeout' => (int) env('CLOUDFLARE_CACHE_WARM_TIMEOUT', 15),
        'user_agent' => env(
            'CLOUDFLARE_CACHE_WARM_USER_AGENT',
            'HardImpact-CloudflareCache/1.0 (+https://github.com/hardimpactdev/cloudflare-cache)',
        ),
    ],

];
