<?php

declare(strict_types=1);

namespace NckRtl\CloudflareCache\Jobs;

use NckRtl\CloudflareCache\CloudflareCacheManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WarmUrlsJob implements ShouldQueue
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
    ) {}

    public function handle(CloudflareCacheManager $cache): void
    {
        $cache->warmNow($this->urls);
    }
}
