<?php

declare(strict_types=1);

use NckRtl\CloudflareCache\Facades\CloudflareCache;
use NckRtl\CloudflareCache\Jobs\PurgeUrlsJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('purges urls synchronously via the cloudflare api', function () {
    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    CloudflareCache::purge([
        'https://lindaretel.nl/',
        'https://lindaretel.nl/projecten/sion',
    ], async: false);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.cloudflare.com/client/v4/zones/test-zone-id/purge_cache'
            && $request['files'] === [
                'https://lindaretel.nl/',
                'https://lindaretel.nl/projecten/sion',
            ];
    });
});

it('queues purge jobs when async', function () {
    Queue::fake();

    config(['cloudflare-cache.purge.async' => true]);

    CloudflareCache::purge(['https://lindaretel.nl/'], async: true);

    Queue::assertPushed(PurgeUrlsJob::class, function (PurgeUrlsJob $job) {
        return $job->urls === ['https://lindaretel.nl/']
            && $job->warm === false
            && $job->purgeEverything === false;
    });
});

it('queues warming after purge when requested', function () {
    Queue::fake();

    config(['cloudflare-cache.purge.async' => true]);

    CloudflareCache::purge(['https://lindaretel.nl/'], async: true, warm: true);

    Queue::assertPushed(PurgeUrlsJob::class, function (PurgeUrlsJob $job) {
        return $job->warm === true;
    });
});

it('purges everything', function () {
    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    CloudflareCache::purgeEverything(async: false);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.cloudflare.com/client/v4/zones/test-zone-id/purge_cache'
            && ($request['purge_everything'] ?? false) === true;
    });
});

it('warms urls with a get request', function () {
    Http::fake([
        'lindaretel.nl/*' => Http::response('ok', 200),
    ]);

    CloudflareCache::warm(['https://lindaretel.nl/'], async: false);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://lindaretel.nl/'
            && $request->method() === 'GET';
    });
});

it('chunks purges into batches of 30', function () {
    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    $urls = array_map(
        fn (int $i) => "https://lindaretel.nl/page-{$i}",
        range(1, 35),
    );

    CloudflareCache::purge($urls, async: false);

    Http::assertSentCount(2);
});

it('no-ops when disabled', function () {
    Http::fake();
    config(['cloudflare-cache.enabled' => false]);

    CloudflareCache::purge(['https://lindaretel.nl/'], async: false);

    Http::assertNothingSent();
});

it('does not purge when the current environment is not allowed', function () {
    Http::fake();
    config(['cloudflare-cache.environments' => ['production']]);

    CloudflareCache::purge(['https://lindaretel.nl/'], async: false);

    Http::assertNothingSent();
});

it('does not purge everything when the current environment is not allowed', function () {
    Http::fake();
    config(['cloudflare-cache.environments' => ['production']]);

    CloudflareCache::purgeEverything(async: false);

    Http::assertNothingSent();
});

it('does not warm when the current environment is not allowed', function () {
    Http::fake();
    config(['cloudflare-cache.environments' => ['production']]);

    CloudflareCache::warm(['https://lindaretel.nl/'], async: false);

    Http::assertNothingSent();
});

it('does not purge urls for a model that does not implement PurgesCloudflareUrls', function () {
    Http::fake();

    $model = new class extends Model {};

    CloudflareCache::purge($model, async: false);

    Http::assertNothingSent();
});
