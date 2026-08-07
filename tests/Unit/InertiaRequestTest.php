<?php

declare(strict_types=1);

use NckRtl\CloudflareCache\Support\InertiaRequest;
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
