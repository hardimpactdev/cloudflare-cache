<?php

declare(strict_types=1);

namespace HardImpact\CloudflareCache\Client;

use HardImpact\CloudflareCache\Exceptions\CloudflareCacheException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareClient
{
    public function __construct(
        protected readonly ?string $zoneId,
        protected readonly ?string $apiToken,
        protected readonly string $baseUrl = 'https://api.cloudflare.com/client/v4',
        protected readonly bool $softFail = true,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->zoneId) && filled($this->apiToken);
    }

    /**
     * @param  list<string>  $urls
     */
    public function purgeUrls(array $urls): void
    {
        if ($urls === []) {
            return;
        }

        if (! $this->isConfigured()) {
            $this->handleMisconfiguration('Cannot purge Cloudflare cache: CLOUDFLARE_ZONE_ID and CLOUDFLARE_API_TOKEN must be set.');

            return;
        }

        // Cloudflare accepts at most 30 URLs per purge request.
        foreach (array_chunk($urls, 30) as $chunk) {
            $this->request('post', "/zones/{$this->zoneId}/purge_cache", [
                'files' => $chunk,
            ]);
        }
    }

    public function purgeEverything(): void
    {
        if (! $this->isConfigured()) {
            $this->handleMisconfiguration('Cannot purge Cloudflare cache: CLOUDFLARE_ZONE_ID and CLOUDFLARE_API_TOKEN must be set.');

            return;
        }

        $this->request('post', "/zones/{$this->zoneId}/purge_cache", [
            'purge_everything' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function request(string $method, string $path, array $payload = []): array
    {
        /** @var PendingRequest $pending */
        $pending = Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withToken((string) $this->apiToken)
            ->acceptJson()
            ->asJson()
            ->timeout(30);

        $response = $pending->{strtolower($method)}(ltrim($path, '/'), $payload);

        if ($response->failed()) {
            $message = sprintf(
                'Cloudflare API request failed [%s]: %s',
                $response->status(),
                $response->body(),
            );

            if ($this->softFail) {
                Log::warning($message);

                return [];
            }

            throw new CloudflareCacheException($message);
        }

        /** @var array{success?: bool, errors?: list<array{message?: string}>} $json */
        $json = $response->json() ?? [];

        if (($json['success'] ?? false) !== true) {
            $errors = collect($json['errors'] ?? [])
                ->pluck('message')
                ->filter()
                ->implode('; ');

            $message = 'Cloudflare API reported failure'.($errors !== '' ? ": {$errors}" : '.');

            if ($this->softFail) {
                Log::warning($message, ['payload' => $payload]);

                return $json;
            }

            throw new CloudflareCacheException($message);
        }

        return $json;
    }

    protected function handleMisconfiguration(string $message): void
    {
        if ($this->softFail) {
            Log::debug($message);

            return;
        }

        throw new CloudflareCacheException($message);
    }
}
