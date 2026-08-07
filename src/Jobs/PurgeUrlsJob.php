<?php

declare(strict_types=1);

namespace NckRtl\CloudflareCache\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use NckRtl\CloudflareCache\CloudflareCacheManager;

class PurgeUrlsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  list<string>  $urls
     */
    public function __construct(
        public array $urls = [],
        public bool $warm = false,
        public bool $purgeEverything = false,
    ) {}

    public function handle(CloudflareCacheManager $cache): void
    {
        if ($this->purgeEverything) {
            $cache->purgeEverything(async: false);

            return;
        }

        $cache->purgeNow($this->urls);

        // Warm inline (synchronous) rather than dispatching a second queued job.
        if ($this->warm && (bool) config('cloudflare-cache.warm.enabled', true)) {
            $cache->warmNow($this->urls);
        }
    }
}
