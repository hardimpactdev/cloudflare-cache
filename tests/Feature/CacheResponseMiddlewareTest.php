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
        ->toContain('max-age=0');
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
