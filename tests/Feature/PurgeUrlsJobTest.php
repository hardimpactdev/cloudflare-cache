<?php

declare(strict_types=1);

use HardImpact\CloudflareCache\CloudflareCacheManager;
use HardImpact\CloudflareCache\Jobs\PurgeUrlsJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('warms synchronously after purge instead of queueing a second job', function () {
    Queue::fake();

    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true], 200),
        'lindaretel.nl/*' => Http::response('ok', 200),
    ]);

    $job = new PurgeUrlsJob(['https://lindaretel.nl/'], warm: true);
    $job->handle(app(CloudflareCacheManager::class));

    Queue::assertNothingPushed();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://lindaretel.nl/'
            && $request->method() === 'GET';
    });
});

it('does not warm from the job when warming is disabled via config', function () {
    Queue::fake();
    config(['cloudflare-cache.warm.enabled' => false]);

    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true], 200),
        'lindaretel.nl/*' => Http::response('ok', 200),
    ]);

    $job = new PurgeUrlsJob(['https://lindaretel.nl/'], warm: true);
    $job->handle(app(CloudflareCacheManager::class));

    Queue::assertNothingPushed();
    Http::assertSentCount(1);
    Http::assertNotSent(fn ($request) => $request->url() === 'https://lindaretel.nl/');
});

it('purges everything through the manager', function () {
    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    $job = new PurgeUrlsJob([], warm: false, purgeEverything: true);
    $job->handle(app(CloudflareCacheManager::class));

    Http::assertSent(fn ($request) => ($request['purge_everything'] ?? false) === true);
});
