<?php

declare(strict_types=1);

use HardImpact\CloudflareCache\Http\Middleware\CacheResponse;
use Illuminate\Support\Facades\Route;

it('sets public cache headers on successful get responses', function () {
    Route::middleware(CacheResponse::class)->get('/cached-page', fn () => response('ok'));

    $response = $this->get('/cached-page');

    $response->assertOk();
    $response->assertHeader('Cache-Control');

    $header = $response->headers->get('Cache-Control');

    expect($header)
        ->toContain('public')
        ->toContain('s-maxage=3600')
        ->toContain('max-age=0')
        ->toContain('stale-while-revalidate=0')
        ->toContain('stale-if-error=0');
});

it('allows s-maxage override via middleware parameters', function () {
    Route::middleware('cloudflare.cache:86400,60')->get('/long-cache', fn () => response('ok'));

    $response = $this->get('/long-cache');

    $header = (string) $response->headers->get('Cache-Control');

    expect($header)
        ->toContain('s-maxage=86400')
        ->toContain('max-age=60');
});

it('does not set cache headers when the response sets cookies', function () {
    Route::middleware(CacheResponse::class)->get('/cookie-page', function () {
        return response('ok')->cookie('session', 'value');
    });

    $response = $this->get('/cookie-page');

    $header = (string) $response->headers->get('Cache-Control');

    expect($header)->not->toContain('s-maxage=');
});

it('does not set cache headers for non-get requests', function () {
    Route::middleware(CacheResponse::class)->post('/form', fn () => response('ok'));

    $response = $this->post('/form');

    $header = (string) $response->headers->get('Cache-Control');

    expect($header)->not->toContain('s-maxage=');
});

it('does not set cache headers for error responses', function () {
    Route::middleware(CacheResponse::class)->get('/missing', fn () => response('nope', 404));

    $response = $this->get('/missing');

    $header = (string) $response->headers->get('Cache-Control');

    expect($header)->not->toContain('s-maxage=');
});

it('skips headers when package is disabled', function () {
    config(['cloudflare-cache.enabled' => false]);

    Route::middleware(CacheResponse::class)->get('/off', fn () => response('ok'));

    $response = $this->get('/off');
    $header = (string) $response->headers->get('Cache-Control');

    expect($header)->not->toContain('s-maxage=');
});

it('forces private no-store for Inertia partial visits', function () {
    Route::middleware(CacheResponse::class)->get('/inertia-page', fn () => response()->json([
        'component' => 'home',
        'props' => [],
        'url' => '/',
        'version' => 'abc',
    ]));

    $response = $this->get('/inertia-page', [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertOk();
    $header = (string) $response->headers->get('Cache-Control');

    expect($header)
        ->toContain('private')
        ->toContain('no-store')
        ->not->toContain('s-maxage=');

    expect((string) $response->headers->get('Vary'))->toContain('X-Inertia');
});

it('sets public headers for document visits and varies on X-Inertia', function () {
    Route::middleware(CacheResponse::class)->get('/doc-page', fn () => response('<html>ok</html>'));

    $response = $this->get('/doc-page');

    expect((string) $response->headers->get('Cache-Control'))->toContain('public');
    expect((string) $response->headers->get('Vary'))->toContain('X-Inertia');
});

it('does not rewrite Cache-Control for Inertia requests when the package is disabled', function () {
    config(['cloudflare-cache.enabled' => false]);

    Route::middleware(CacheResponse::class)->get('/inertia-disabled', fn () => response()->json([
        'component' => 'home',
        'props' => [],
        'url' => '/',
        'version' => 'abc',
    ]));

    $response = $this->get('/inertia-disabled', [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertOk();

    expect((string) $response->headers->get('Cache-Control'))->not->toContain('no-store');
});

it('does not rewrite Cache-Control for non-GET Inertia requests when the package is disabled', function () {
    config(['cloudflare-cache.enabled' => false]);

    Route::middleware(CacheResponse::class)->post('/inertia-disabled-post', fn () => response()->json([
        'component' => 'home',
        'props' => [],
        'url' => '/',
        'version' => 'abc',
    ]));

    $response = $this->post('/inertia-disabled-post', [], [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    expect((string) $response->headers->get('Cache-Control'))->not->toContain('no-store');
});

it('does not rewrite Cache-Control for Inertia requests when the environment is not allowed', function () {
    config(['cloudflare-cache.environments' => ['production']]);

    Route::middleware(CacheResponse::class)->get('/inertia-env-off', fn () => response()->json([
        'component' => 'home',
        'props' => [],
        'url' => '/',
        'version' => 'abc',
    ]));

    $response = $this->get('/inertia-env-off', [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    expect((string) $response->headers->get('Cache-Control'))->not->toContain('no-store');
});

it('does not set public cache headers when the request has an active session', function () {
    Route::middleware(['web', CacheResponse::class])->get('/session-page', fn () => response('ok'));

    $response = $this->get('/session-page');

    $header = (string) $response->headers->get('Cache-Control');

    expect($header)->not->toContain('s-maxage=');
});
