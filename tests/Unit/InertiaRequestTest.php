<?php

declare(strict_types=1);

use HardImpact\CloudflareCache\Support\InertiaRequest;
use Illuminate\Http\Request;

it('detects inertia partial requests', function () {
    $request = Request::create('/', 'GET');
    $request->headers->set('X-Inertia', 'true');

    expect(InertiaRequest::isInertia($request))->toBeTrue();
});

it('treats document visits as non-inertia', function () {
    $request = Request::create('/', 'GET');

    expect(InertiaRequest::isInertia($request))->toBeFalse();
});

it('builds distinct cache key fragments for document vs inertia vs partials', function () {
    $document = Request::create('/projects', 'GET');
    $inertia = Request::create('/projects', 'GET');
    $inertia->headers->set('X-Inertia', 'true');
    $inertia->headers->set('X-Inertia-Version', 'v1');

    $partial = Request::create('/projects', 'GET');
    $partial->headers->set('X-Inertia', 'true');
    $partial->headers->set('X-Inertia-Partial-Data', 'nav,seo');
    $partial->headers->set('X-Inertia-Version', 'v1');

    expect(InertiaRequest::cacheKeyFragment($document))->toBe('document');
    expect(InertiaRequest::cacheKeyFragment($inertia))->not->toBe('document');
    expect(InertiaRequest::cacheKeyFragment($partial))
        ->not->toBe(InertiaRequest::cacheKeyFragment($inertia))
        ->toContain('nav,seo');
});
