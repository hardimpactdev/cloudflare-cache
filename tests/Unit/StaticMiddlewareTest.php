<?php

declare(strict_types=1);

use HardImpact\CloudflareCache\Http\Middleware\CacheResponse;
use HardImpact\CloudflareCache\Support\StaticMiddleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

it('builds a default static middleware stack', function () {
    $stack = StaticMiddleware::defaults([
        'App\\Http\\Middleware\\HandleInertiaRequests',
    ]);

    expect($stack)->toBe([
        SubstituteBindings::class,
        'App\\Http\\Middleware\\HandleInertiaRequests',
        CacheResponse::class,
    ]);
});
