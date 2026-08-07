<?php

declare(strict_types=1);

use NckRtl\CloudflareCache\Support\UrlNormalizer;

it('normalizes and deduplicates absolute urls', function () {
    $urls = UrlNormalizer::normalizeMany([
        'https://lindaretel.nl/projecten/sion#top',
        'https://lindaretel.nl/projecten/sion',
        'https://lindaretel.nl/',
        'not-a-url',
        '',
        'ftp://example.com/file',
    ]);

    expect($urls)->toBe([
        'https://lindaretel.nl/projecten/sion',
        'https://lindaretel.nl/',
    ]);
});

it('returns null for invalid urls', function () {
    expect(UrlNormalizer::normalize('relative/path'))->toBeNull();
});
