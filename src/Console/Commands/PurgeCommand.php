<?php

declare(strict_types=1);

namespace NckRtl\CloudflareCache\Console\Commands;

use Illuminate\Console\Command;
use NckRtl\CloudflareCache\CloudflareCacheManager;

class PurgeCommand extends Command
{
    protected $signature = 'cloudflare-cache:purge
                            {urls?* : Absolute URLs to purge}
                            {--all : Purge the entire Cloudflare zone cache}
                            {--sync : Run synchronously instead of queueing}
                            {--warm : Warm URLs after purging}';

    protected $description = 'Purge one or more URLs (or the entire zone) from Cloudflare cache';

    public function handle(CloudflareCacheManager $cache): int
    {
        $sync = (bool) $this->option('sync');
        $warm = (bool) $this->option('warm');

        if ($this->option('all')) {
            $cache->purgeEverything(async: ! $sync);
            $this->info($sync ? 'Purged entire Cloudflare zone cache.' : 'Queued entire Cloudflare zone purge.');

            return self::SUCCESS;
        }

        $argument = $this->argument('urls');
        /** @var list<string> $urls */
        $urls = array_values(array_filter(is_array($argument) ? $argument : []));

        if ($urls === []) {
            $this->warn('Pass one or more URLs, or use --all.');

            return self::FAILURE;
        }

        $cache->purge($urls, async: ! $sync, warm: $warm);

        $message = $sync
            ? 'Purged '.count($urls).' URL(s) from Cloudflare.'
            : 'Queued purge for '.count($urls).' URL(s).';

        if ($warm) {
            $message .= $sync ? ' Warmed afterwards.' : ' Warming queued after purge.';
        }

        $this->info($message);

        return self::SUCCESS;
    }
}
