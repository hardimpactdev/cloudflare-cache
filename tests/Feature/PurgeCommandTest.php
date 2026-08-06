<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

it('purges urls via artisan', function () {
    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    $this->artisan('cloudflare-cache:purge', [
        'urls' => ['https://lindaretel.nl/'],
        '--sync' => true,
    ])->assertSuccessful();

    Http::assertSentCount(1);
});

it('purges the entire zone via artisan', function () {
    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    $this->artisan('cloudflare-cache:purge', [
        '--all' => true,
        '--sync' => true,
    ])->assertSuccessful();

    Http::assertSent(fn ($request) => ($request['purge_everything'] ?? false) === true);
});
