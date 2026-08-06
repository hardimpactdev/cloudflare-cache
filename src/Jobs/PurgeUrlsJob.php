<?php

declare(strict_types=1);

namespace HardImpact\CloudflareCache\Jobs;

use HardImpact\CloudflareCache\CloudflareCacheManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
            $cache->client()->purgeEverything();

            return;
        }

        $cache->purgeNow($this->urls);

        if ($this->warm) {
            $cache->warm($this->urls, async: true);
        }
    }
}
