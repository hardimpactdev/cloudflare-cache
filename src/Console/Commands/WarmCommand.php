<?php

declare(strict_types=1);

namespace HardImpact\CloudflareCache\Console\Commands;

use HardImpact\CloudflareCache\CloudflareCacheManager;
use Illuminate\Console\Command;

class WarmCommand extends Command
{
    protected $signature = 'cloudflare-cache:warm
                            {urls* : Absolute URLs to warm}
                            {--sync : Run synchronously instead of queueing}';

    protected $description = 'Warm Cloudflare cache by requesting URLs from origin';

    public function handle(CloudflareCacheManager $cache): int
    {
        $argument = $this->argument('urls');
        /** @var list<string> $urls */
        $urls = array_values(array_filter(is_array($argument) ? $argument : []));

        if ($urls === []) {
            $this->warn('Pass one or more absolute URLs to warm.');

            return self::FAILURE;
        }

        $sync = (bool) $this->option('sync');
        $cache->warm($urls, async: ! $sync);

        $this->info($sync
            ? 'Warmed '.count($urls).' URL(s).'
            : 'Queued warm for '.count($urls).' URL(s).');

        return self::SUCCESS;
    }
}
