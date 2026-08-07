<?php

declare(strict_types=1);

namespace NckRtl\CloudflareCache\Contracts;

interface PurgesCloudflareUrls
{
    /**
     * Absolute URLs that should be purged from Cloudflare when this model changes.
     *
     * @return list<string>
     */
    public function cloudflareCacheUrls(): array;
}
